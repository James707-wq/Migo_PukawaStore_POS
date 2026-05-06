<?php
// api/checkout.php  –  Process a POS transaction
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success'=>false,'message'=>'Invalid request body']);
    exit;
}

$cart    = $input['cart']           ?? [];
$payment = (float)($input['payment'] ?? 0);
$grand   = (float)($input['grand']   ?? 0);
$discount= (float)($input['discount']?? 0);
$change  = (float)($input['change']  ?? 0);
$method  = in_array($input['payment_method']??'cash',['cash','gcash','card'])
             ? $input['payment_method'] : 'cash';

if (empty($cart)) {
    echo json_encode(['success'=>false,'message'=>'Cart is empty']);
    exit;
}
if ($payment < $grand) {
    echo json_encode(['success'=>false,'message'=>'Insufficient payment']);
    exit;
}

$db  = getDB();
$uid = currentUser()['id'];

try {
    $db->beginTransaction();

    // ── Validate stock & lock rows ────────────────────────
    $subtotal = 0;
    $validated = [];
    foreach ($cart as $item) {
        $pid = (int)$item['product_id'];
        $qty = (int)$item['qty'];
        $stmt = $db->prepare(
            "SELECT product_id, product_name, barcode, price, stock_quantity
             FROM products WHERE product_id=? AND is_active=1 FOR UPDATE"
        );
        $stmt->execute([$pid]);
        $p = $stmt->fetch();
        if (!$p) {
            $db->rollBack();
            echo json_encode(['success'=>false,'message'=>"Product ID $pid not found"]);
            exit;
        }
        if ($p['stock_quantity'] < $qty) {
            $db->rollBack();
            echo json_encode([
                'success' => false,
                'message' => "Insufficient stock for \"{$p['product_name']}\" (available: {$p['stock_quantity']})"
            ]);
            exit;
        }
        $lineTotal  = $p['price'] * $qty;
        $subtotal  += $lineTotal;
        $validated[] = array_merge($p, ['qty'=>$qty,'line_total'=>$lineTotal]);
    }

    $txnNo = generateTransactionNo();

    // ── Insert transaction ────────────────────────────────
    $stmt = $db->prepare(
        "INSERT INTO transactions
         (transaction_no,cashier_id,subtotal,discount_amount,total_amount,
          payment_amount,change_amount,payment_method)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        $txnNo, $uid,
        $subtotal, $discount, $grand,
        $payment, $change, $method
    ]);
    $txnId = $db->lastInsertId();

    // ── Insert items & deduct stock ───────────────────────
    $updatedStock = [];
    $itemStmt = $db->prepare(
        "INSERT INTO transaction_items
         (transaction_id,product_id,product_name,barcode,quantity,unit_price,subtotal)
         VALUES (?,?,?,?,?,?,?)"
    );
    $stockStmt = $db->prepare(
        "UPDATE products SET stock_quantity=stock_quantity-? WHERE product_id=?"
    );

    foreach ($validated as $v) {
        $itemStmt->execute([
            $txnId, $v['product_id'], $v['product_name'],
            $v['barcode'], $v['qty'], $v['price'], $v['line_total']
        ]);
        $stockStmt->execute([$v['qty'], $v['product_id']]);
        // Fetch updated stock
        $newStock = $db->prepare("SELECT stock_quantity FROM products WHERE product_id=?");
        $newStock->execute([$v['product_id']]);
        $updatedStock[] = [
            'product_id'     => $v['product_id'],
            'stock_quantity' => (int)$newStock->fetchColumn()
        ];
    }

    $db->commit();

    // ── Build receipt data ────────────────────────────────
    $cashier = $db->prepare("SELECT full_name FROM users WHERE user_id=?");
    $cashier->execute([$uid]);
    $cashierName = $cashier->fetchColumn();

    $receiptItems = array_map(fn($v) => [
        'name'       => $v['product_name'],
        'qty'        => $v['qty'],
        'unit_price' => $v['price'],
        'subtotal'   => $v['line_total'],
    ], $validated);

    echo json_encode([
        'success' => true,
        'message' => 'Transaction completed',
        'updatedStock' => $updatedStock,
        'transaction' => [
            'transaction_no' => $txnNo,
            'store_name'     => STORE_NAME,
            'store_address'  => STORE_ADDRESS,
            'store_phone'    => STORE_PHONE,
            'store_tin'      => STORE_TIN,
            'date'           => date('F d, Y  h:i:s A'),
            'cashier'        => $cashierName,
            'items'          => $receiptItems,
            'subtotal'       => $subtotal,
            'discount'       => $discount,
            'total'          => $grand,
            'payment'        => $payment,
            'change'         => $change,
            'method'         => ucfirst($method),
        ]
    ]);

} catch (Throwable $e) {
    $db->rollBack();
    echo json_encode(['success'=>false,'message'=>'Transaction failed: ' . $e->getMessage()]);
}
