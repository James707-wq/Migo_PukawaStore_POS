<?php
// api/returns.php - API endpoints for returns/refunds
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? 'list';

switch ($action) {
  
  // ── Search transactions ─────────────────────────────────
  case 'search_txn':
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 3) {
      echo json_encode(['success' => false, 'message' => 'Search term too short']);
      break;
    }
    
    $query = $db->prepare(
      "SELECT t.transaction_id, t.transaction_no, t.total_amount, t.transaction_date,
              COUNT(ti.item_id) AS items
       FROM transactions t
       LEFT JOIN transaction_items ti ON t.transaction_id = ti.transaction_id
       WHERE t.status = 'completed' 
         AND (t.transaction_no LIKE ? OR t.transaction_id = ?)
       GROUP BY t.transaction_id
       ORDER BY t.transaction_date DESC
       LIMIT 10"
    );
    $query->execute(["%$q%", (int)$q]);
    $transactions = $query->fetchAll();
    
    echo json_encode(['success' => true, 'transactions' => $transactions]);
    break;

  // ── Get transaction items ───────────────────────────────
  case 'get_items':
    $txnId = (int)($_GET['txn_id'] ?? 0);
    if (!$txnId) {
      echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
      break;
    }
    
    $items = $db->prepare(
      "SELECT product_id, product_name, barcode, quantity, unit_price, 
              (quantity * unit_price) AS subtotal
       FROM transaction_items
       WHERE transaction_id = ?"
    );
    $items->execute([$txnId]);
    $result = $items->fetchAll();
    
    echo json_encode(['success' => true, 'items' => $result]);
    break;

  // ── Get return details ──────────────────────────────────
  case 'get_return':
    $returnId = (int)($_GET['return_id'] ?? 0);
    if (!$returnId) {
      echo json_encode(['success' => false, 'message' => 'Invalid return ID']);
      break;
    }
    
    $return = $db->prepare(
      "SELECT r.*, t.transaction_no FROM returns r
       JOIN transactions t ON r.transaction_id = t.transaction_id
       WHERE r.return_id = ?"
    );
    $return->execute([$returnId]);
    $ret = $return->fetch();
    
    if (!$ret) {
      echo json_encode(['success' => false, 'message' => 'Return not found']);
      break;
    }
    
    $items = $db->prepare(
      "SELECT product_name, quantity, unit_price, refund_amount
       FROM return_items
       WHERE return_id = ?"
    );
    $items->execute([$returnId]);
    $ret['items'] = $items->fetchAll();
    
    echo json_encode(['success' => true, 'return' => $ret]);
    break;

  default:
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
