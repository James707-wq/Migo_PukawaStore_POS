<?php
require_once 'config.php';
requireAdmin();
$pageTitle = 'Returns & Refunds';
$db = getDB();

// Handle approve/reject
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $returnId = (int)($_GET['return_id'] ?? 0);
    
    if ($action === 'approve' && $returnId) {
        $return = $db->prepare("SELECT * FROM returns WHERE return_id=?");
        $return->execute([$returnId]);
        $ret = $return->fetch();
        
        if ($ret && $ret['status'] === 'pending') {
            try {
                $db->beginTransaction();
                
                // Get all return items
                $items = $db->prepare("SELECT * FROM return_items WHERE return_id=?");
                $items->execute([$returnId]);
                $allItems = $items->fetchAll();
                
                // Restock each item
                foreach ($allItems as $item) {
                    $restockStmt = $db->prepare(
                        "UPDATE products SET stock_quantity=stock_quantity+? WHERE product_id=?"
                    );
                    $restockStmt->execute([$item['quantity'], $item['product_id']]);
                }
                
                // Update transaction status to refunded
                $txnStmt = $db->prepare("UPDATE transactions SET status='refunded' WHERE transaction_id=?");
                $txnStmt->execute([$ret['transaction_id']]);
                
                // Update return status to approved
                $updateStmt = $db->prepare("UPDATE returns SET status='approved' WHERE return_id=?");
                $updateStmt->execute([$returnId]);
                
                $db->commit();
                header('Location: returns.php?msg=approved');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                header('Location: returns.php?msg=error');
                exit;
            }
        }
    } elseif ($action === 'reject' && $returnId) {
        $stmt = $db->prepare("UPDATE returns SET status='rejected' WHERE return_id=? AND status='pending'");
        $stmt->execute([$returnId]);
        header('Location: returns.php?msg=rejected');
        exit;
    }
}

// Handle new return submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return'])) {
    $txnId = (int)($_POST['transaction_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $items = $_POST['items'] ?? [];
    
    if (!$txnId || !$reason || empty($items)) {
        $error = 'Missing required fields';
    } else {
        // Validate transaction exists and get details
        $txn = $db->prepare("SELECT * FROM transactions WHERE transaction_id=?");
        $txn->execute([$txnId]);
        $transaction = $txn->fetch();
        
        if (!$transaction) {
            $error = 'Transaction not found';
        } else {
            try {
                $db->beginTransaction();
                
                $returnNo = 'RET-' . date('YmdHis') . '-' . uniqid();
                $uid = currentUser()['id'];
                $refundAmt = 0;
                
                // Calculate refund amount and validate items
                $validated_items = [];
                foreach ($items as $prodId => $qty) {
                    $qty = (int)$qty;
                    if ($qty <= 0) continue;
                    
                    // Get original transaction item
                    $origItem = $db->prepare(
                        "SELECT ti.*, p.stock_quantity FROM transaction_items ti
                         JOIN products p ON ti.product_id=p.product_id
                         WHERE ti.transaction_id=? AND ti.product_id=?"
                    );
                    $origItem->execute([$txnId, $prodId]);
                    $item = $origItem->fetch();
                    
                    if (!$item || $qty > $item['quantity']) {
                        throw new Exception("Invalid quantity for product ID $prodId");
                    }
                    
                    $itemRefund = $item['unit_price'] * $qty;
                    $refundAmt += $itemRefund;
                    $validated_items[] = [
                        'product_id' => $prodId,
                        'quantity' => $qty,
                        'unit_price' => $item['unit_price'],
                        'product_name' => $item['product_name'],
                        'refund_amount' => $itemRefund
                    ];
                }
                
                if (empty($validated_items)) {
                    throw new Exception('No items selected');
                }
                
                // Insert return record
                $refundMethod = $transaction['payment_method'];
                $insertReturn = $db->prepare(
                    "INSERT INTO returns (transaction_id, return_no, returned_by, refund_amount, refund_method, reason)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $insertReturn->execute([
                    $txnId, $returnNo, $uid, $refundAmt, $refundMethod, $reason
                ]);
                $newReturnId = $db->lastInsertId();
                
                // Insert return items
                $insertItem = $db->prepare(
                    "INSERT INTO return_items (return_id, product_id, product_name, quantity, unit_price, refund_amount)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                foreach ($validated_items as $item) {
                    $insertItem->execute([
                        $newReturnId, $item['product_id'], $item['product_name'],
                        $item['quantity'], $item['unit_price'], $item['refund_amount']
                    ]);
                }
                
                $db->commit();
                $success = 'Return submitted for approval!';
                $_POST = [];
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// Get all returns
$returns = $db->query(
    "SELECT r.*, t.transaction_no, t.total_amount, u.full_name
     FROM returns r
     JOIN transactions t ON r.transaction_id = t.transaction_id
     JOIN users u ON r.returned_by = u.user_id
     ORDER BY r.return_date DESC"
)->fetchAll();

// Get transaction details for modal
$txnDetails = [];
if (isset($_GET['txn_id']) && is_numeric($_GET['txn_id'])) {
    $txn = (int)$_GET['txn_id'];
    $details = $db->prepare(
        "SELECT t.*, u.full_name FROM transactions t
         JOIN users u ON t.cashier_id = u.user_id
         WHERE t.transaction_id=?"
    );
    $details->execute([$txn]);
    $txnDetails = $details->fetch();
    
    if ($txnDetails) {
        $items = $db->prepare(
            "SELECT * FROM transaction_items WHERE transaction_id=?"
        );
        $items->execute([$txn]);
        $txnDetails['items'] = $items->fetchAll();
    }
}

require_once 'includes/header.php';
?>

<?php if (isset($_GET['msg'])): $msgs=['approved'=>['success','Return approved & inventory restocked!'],'rejected'=>['warning','Return rejected.'],'error'=>['danger','An error occurred.']]; $m=$msgs[$_GET['msg']]??null; if($m): ?>
<div class="alert alert-<?=$m[0]?> d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-<?=$m[0]==='success'?'check-circle-fill':'exclamation-circle-fill'?>"></i><?=$m[1]?>
</div>
<?php endif; endif; ?>

<?php if (isset($error)): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-exclamation-circle-fill"></i><?=$error?>
</div>
<?php endif; ?>

<?php if (isset($success)): ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-check-circle-fill"></i><?=$success?>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h2><i class="bi bi-arrow-counterclockwise"></i> Returns & Refunds</h2>
  <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#returnModal">
    <i class="bi bi-plus-circle"></i> New Return
  </button>
</div>

<!-- ── NEW RETURN MODAL ────────────────────────────────── -->
<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Process Return / Refund</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="submit_return" value="1"/>
        <input type="hidden" name="transaction_id" id="selectedTxnId" value=""/>
        <div class="modal-body">
          
          <!-- Step 1: Find Transaction -->
          <div class="mb-3" id="step1">
            <label class="form-label">Search Transaction <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="txnSearch" placeholder="Enter Transaction Number (e.g., TXN-202605031430)..."/>
            <div id="searchResults" class="mt-2"></div>
          </div>

          <!-- Step 2: Select Items (hidden until txn selected) -->
          <div id="step2" style="display:none">
            <div class="alert alert-info mb-3">
              <strong>Transaction:</strong> <span id="txnNo"></span> | 
              <strong>Date:</strong> <span id="txnDate"></span> | 
              <strong>Total:</strong> ₱<span id="txnTotal"></span>
            </div>
            
            <label class="form-label">Select Items to Return <span class="text-danger">*</span></label>
            <div id="itemsList" class="border rounded p-3" style="max-height:250px;overflow-y:auto"></div>
          </div>

          <!-- Step 3: Return Reason (hidden until items selected) -->
          <div id="step3" style="display:none" class="mt-3">
            <label class="form-label">Return Reason <span class="text-danger">*</span></label>
            <select class="form-select" name="reason" required>
              <option value="">-- Select Reason --</option>
              <option value="Defective/Damaged">Defective/Damaged</option>
              <option value="Wrong Item">Wrong Item</option>
              <option value="Changed Mind">Changed Mind</option>
              <option value="Expired">Expired</option>
              <option value="Not as Described">Not as Described</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-brand" id="submitReturnBtn" style="display:none">
            <i class="bi bi-check-circle"></i> Submit Return Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── RETURNS LIST ────────────────────────────────────── -->
<div class="table-responsive">
  <table class="table table-hover">
    <thead class="table-light">
      <tr>
        <th>Return #</th>
        <th>Original TXN</th>
        <th>Refund Amount</th>
        <th>Reason</th>
        <th>Status</th>
        <th>Date</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($returns)): ?>
      <tr><td colspan="7" class="text-center text-muted py-4">No returns found.</td></tr>
      <?php else: ?>
      <?php foreach($returns as $r): 
        $statusClasses = [
          'pending' => 'warning',
          'approved' => 'success',
          'rejected' => 'danger'
        ];
        $statusClass = $statusClasses[$r['status']] ?? 'secondary';
      ?>
      <tr>
        <td><code><?=$r['return_no']?></code></td>
        <td><?=$r['transaction_no']?></td>
        <td><strong>₱<?=number_format($r['refund_amount'], 2)?></strong></td>
        <td><small><?=htmlspecialchars($r['reason'])?></small></td>
        <td><span class="badge badge-<?=$statusClass?>"><?=ucfirst($r['status'])?></span></td>
        <td><small><?=date('M d, Y H:i', strtotime($r['return_date']))?></small></td>
        <td>
          <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailModal" 
                  onclick="showReturnDetails(<?=$r['return_id']?>)">
            <i class="bi bi-eye"></i> View
          </button>
          <?php if ($r['status'] === 'pending'): ?>
          <a href="?action=approve&return_id=<?=$r['return_id']?>" class="btn btn-sm btn-success" 
             onclick="return confirm('Approve this return? Items will be restocked.')">
            <i class="bi bi-check"></i> Approve
          </a>
          <a href="?action=reject&return_id=<?=$r['return_id']?>" class="btn btn-sm btn-danger"
             onclick="return confirm('Reject this return?')">
            <i class="bi bi-x"></i> Reject
          </a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ── RETURN DETAILS MODAL ────────────────────────────── -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Return Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailContent">
        <div class="text-center"><span class="spinner-border spinner-border-sm"></span></div>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
const BASE_URL = '<?=BASE_URL?>';
let selectedTxn = null;

// Search for transaction
document.getElementById('txnSearch').addEventListener('input', debounce(async (e) => {
  const q = e.target.value.trim();
  if (!q) {
    document.getElementById('searchResults').innerHTML = '';
    return;
  }

  try {
    const res = await fetch(`${BASE_URL}api/returns.php?action=search_txn&q=${encodeURIComponent(q)}`);
    const data = await res.json();
    
    if (data.success && data.transactions.length > 0) {
      const html = data.transactions.map(t => `
        <div class="list-group-item cursor-pointer p-2" onclick="selectTransaction(${t.transaction_id}, '${escHtml(t.transaction_no)}', '${t.transaction_date}', ${t.total_amount})">
          <div class="d-flex justify-content-between">
            <strong>${escHtml(t.transaction_no)}</strong>
            <span class="text-muted">₱${fmt2(t.total_amount)}</span>
          </div>
          <small class="text-muted">${t.transaction_date} • ${t.items} items</small>
        </div>
      `).join('');
      document.getElementById('searchResults').innerHTML = `<div class="list-group">${html}</div>`;
    } else {
      document.getElementById('searchResults').innerHTML = '<div class="alert alert-warning mb-0">No transactions found</div>';
    }
  } catch (e) {
    console.error(e);
  }
}, 300));

function selectTransaction(txnId, txnNo, txnDate, txnTotal) {
  selectedTxn = { id: txnId, no: txnNo, date: txnDate, total: txnTotal };
  document.getElementById('selectedTxnId').value = txnId;
  document.getElementById('searchResults').innerHTML = '';
  document.getElementById('txnSearch').value = txnNo;
  document.getElementById('step1').style.display = 'none';
  document.getElementById('step2').style.display = 'block';
  document.getElementById('step3').style.display = 'block';
  
  document.getElementById('txnNo').textContent = txnNo;
  document.getElementById('txnDate').textContent = txnDate;
  document.getElementById('txnTotal').textContent = fmt2(txnTotal);
  
  loadReturnItems(txnId);
}

async function loadReturnItems(txnId) {
  try {
    const res = await fetch(`${BASE_URL}api/returns.php?action=get_items&txn_id=${txnId}`);
    const data = await res.json();
    
    if (data.success && data.items) {
      const html = data.items.map(item => `
        <div class="form-check p-2 border-bottom">
          <input type="checkbox" class="form-check-input" name="items[${item.product_id}]" value="1" id="item_${item.product_id}" onchange="updateReturnBtn()"/>
          <label class="form-check-label w-100" for="item_${item.product_id}">
            <div><strong>${escHtml(item.product_name)}</strong></div>
            <div class="text-muted small">
              Qty: ${item.quantity} @ ₱${fmt2(item.unit_price)} each = ₱${fmt2(item.subtotal)}
            </div>
          </label>
        </div>
      `).join('');
      document.getElementById('itemsList').innerHTML = html;
    }
  } catch (e) {
    console.error(e);
  }
}

function updateReturnBtn() {
  const hasItems = document.querySelectorAll('input[name^="items"]:checked').length > 0;
  document.getElementById('submitReturnBtn').style.display = hasItems ? 'inline-block' : 'none';
}

async function showReturnDetails(returnId) {
  try {
    const res = await fetch(`${BASE_URL}api/returns.php?action=get_return&return_id=${returnId}`);
    const data = await res.json();
    
    if (data.success && data.return) {
      const ret = data.return;
      const itemsHtml = ret.items.map(item => `
        <tr>
          <td>${escHtml(item.product_name)}</td>
          <td class="text-end">${item.quantity}</td>
          <td class="text-end">₱${fmt2(item.unit_price)}</td>
          <td class="text-end">₱${fmt2(item.refund_amount)}</td>
        </tr>
      `).join('');
      
      document.getElementById('detailContent').innerHTML = `
        <div class="mb-3">
          <p><strong>Return #:</strong> ${escHtml(ret.return_no)}</p>
          <p><strong>Transaction #:</strong> ${escHtml(ret.transaction_no)}</p>
          <p><strong>Date:</strong> ${ret.return_date}</p>
          <p><strong>Status:</strong> <span class="badge badge-${ret.status === 'approved' ? 'success' : ret.status === 'pending' ? 'warning' : 'danger'}">${ret.status}</span></p>
          <p><strong>Reason:</strong> ${escHtml(ret.reason)}</p>
          <p><strong>Refund Amount:</strong> ₱${fmt2(ret.refund_amount)}</p>
          <p><strong>Refund Method:</strong> ${ret.refund_method}</p>
        </div>
        <h6>Returned Items</h6>
        <table class="table table-sm">
          <thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Refund</th></tr></thead>
          <tbody>${itemsHtml}</tbody>
        </table>
      `;
    }
  } catch (e) {
    console.error(e);
  }
}

function debounce(fn, delay) {
  let timeout;
  return function(...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => fn(...args), delay);
  };
}
</script>

<?php require_once 'includes/footer.php'; ?>
