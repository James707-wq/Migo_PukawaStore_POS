<?php
require_once 'config.php';
requireLogin();
$pageTitle = 'Point of Sale';

$db = getDB();
$categories = $db->query(
    "SELECT c.*, COUNT(p.product_id) AS cnt
     FROM categories c
     LEFT JOIN products p ON p.category_id=c.category_id AND p.is_active=1 AND p.stock_quantity>0
     GROUP BY c.category_id
     ORDER BY c.category_name"
)->fetchAll();

require_once 'includes/header.php';
?>

<div class="pos-layout">

  <!-- ══ LEFT PANEL ══════════════════════════════════════ -->
  <div class="pos-left">

    <!-- Search / Scan bar -->
    <div class="pos-search-bar">
      <button class="btn btn-brand d-flex align-items-center gap-2"
              data-bs-toggle="modal" data-bs-target="#scannerModal"
              title="Scan Barcode">
        <i class="bi bi-camera-fill"></i>
        <span class="d-none d-md-inline">Scan</span>
      </button>
      <input type="text" id="posSearch" class="form-control"
             placeholder="🔍 Scan or type barcode / product name…"
             autocomplete="off" autofocus/>
      <button class="btn btn-outline-secondary" onclick="clearSearch()" title="Clear search">
        <i class="bi bi-x-lg"></i>
      </button>
      <!-- Category filter -->
      <select id="catFilter" class="form-select">
        <option value="">All Categories</option>
        <?php foreach($categories as $c): ?>
        <option value="<?=$c['category_id']?>"><?=htmlspecialchars($c['category_name'])?></option>
        <?php endforeach; ?>
      </select>
      <!-- Mobile Cart Toggle -->
      <button class="btn btn-brand d-md-none d-flex align-items-center gap-2"
              data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas"
              title="View Cart">
        <i class="bi bi-cart-fill"></i>
        <span class="badge badge-danger" id="cartCountMobile">0</span>
      </button>
    </div>

    <!-- Product grid -->
    <div class="pos-products" id="productGrid">
      <div class="d-flex align-items-center justify-content-center w-100 py-5">
        <div class="spinner-border text-secondary" role="status"></div>
      </div>
    </div>
  </div>

  <!-- ══ RIGHT PANEL – CART ════════════════════════════════ -->
  <div class="pos-right">
    <div class="cart-header">
      <h6><i class="bi bi-receipt"></i> Current Order
        <span class="badge badge-teal ms-auto" id="cartCount">0</span>
      </h6>
    </div>

    <div class="cart-items" id="cartItems">
      <div class="cart-empty" id="cartEmpty">
        <i class="bi bi-cart-x"></i>
        <span>Cart is empty</span>
        <small>Scan a barcode or search a product</small>
      </div>
    </div>

    <div class="cart-totals">
      <div class="totals-row"><span>Subtotal</span><span id="subtotalDisplay">₱ 0.00</span></div>
      <div class="totals-row"><span>Discount</span>
        <div class="input-group input-group-sm" style="max-width:120px">
          <span class="input-group-text">₱</span>
          <input type="number" id="discountInput" class="form-control text-end"
                 value="0" min="0" step="0.01" oninput="updateTotals()"/>
        </div>
      </div>
      <div class="totals-row grand">
        <span>TOTAL</span><span id="grandTotalDisplay">₱ 0.00</span>
      </div>
    </div>

    <div class="payment-section">
      <label class="form-label d-block mb-1">Payment Amount</label>
      <div class="mb-2">
        <div class="input-group">
          <span class="input-group-text fw-bold">₱</span>
          <input type="number" id="paymentInput" class="form-control payment-input"
                 min="0" step="0.01" placeholder="0.00" oninput="updateChange()"/>
        </div>
      </div>
      <!-- Quick denominations -->
      <div class="d-flex flex-wrap gap-1 mb-2" id="denomBtns">
        <?php foreach([20,50,100,200,500,1000] as $d): ?>
        <button class="btn btn-sm btn-outline-secondary"
                onclick="addDenom(<?=$d?>)">+₱<?=$d?></button>
        <?php endforeach; ?>
        <button class="btn btn-sm btn-outline-danger" onclick="clearPayment()">
          <i class="bi bi-x"></i>
        </button>
      </div>
      <!-- Change display -->
      <div class="change-display mb-2">
        <span class="change-label">Change</span>
        <span class="change-amount" id="changeDisplay">₱ 0.00</span>
      </div>
      <!-- Payment method -->
      <div class="d-flex gap-2 mb-2">
        <select id="paymentMethod" class="form-select form-select-sm">
          <option value="cash">Cash</option>
          <option value="gcash">GCash</option>
          <option value="card">Card</option>
        </select>
      </div>
      <button class="checkout-btn" id="checkoutBtn" disabled onclick="checkout()">
        <i class="bi bi-check-circle-fill me-2"></i>Checkout
      </button>
      <button class="btn btn-outline-danger w-100 mt-2" onclick="clearCart()">
        <i class="bi bi-trash me-1"></i>Clear Cart
      </button>
    </div>
  </div>
</div>

<!-- ═══════ SCANNER MODAL ═══════════════════════════════ -->
<div class="modal fade" id="scannerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-camera me-2"></i>Barcode Scanner</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="reader"></div>
        <p class="text-muted text-center mt-2 mb-0" style="font-size:12px">
          Point camera at barcode. It will auto-detect.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ═══════ RECEIPT MODAL ════════════════════════════════ -->
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header no-print">
        <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Transaction Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"
                onclick="newTransaction()"></button>
      </div>
      <div class="modal-body" id="receiptModalBody">
        <div id="receiptContent"></div>
      </div>
      <div class="modal-footer no-print">
        <button class="btn btn-outline-secondary" onclick="printReceipt()">
          <i class="bi bi-printer me-1"></i>Print Receipt
        </button>
        <button class="btn btn-brand" onclick="newTransaction()">
          <i class="bi bi-plus-lg me-1"></i>New Transaction
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ═══════ MOBILE CART DRAWER ════════════════════════════ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas">
  <div class="offcanvas-header" style="background: linear-gradient(135deg, #e8f6fa, #fff); border-bottom: 1px solid #e4ecef;">
    <h5 class="offcanvas-title">
      <i class="bi bi-receipt me-2"></i>Current Order
      <span class="badge badge-teal ms-2" id="cartCountOffcanvas">0</span>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0 d-flex flex-column">
    <div class="cart-items" id="cartItemsOffcanvas" style="flex: 1; overflow-y: auto;">
      <div class="cart-empty" style="height: 100%;">
        <i class="bi bi-cart-x"></i>
        <span>Cart is empty</span>
        <small>Scan a barcode or search a product</small>
      </div>
    </div>

    <!-- Cart Summary in Offcanvas -->
    <div class="cart-totals" style="border-top: 1px solid #e4ecef;">
      <div class="totals-row"><span>Subtotal</span><span id="subtotalDisplayOffcanvas">₱ 0.00</span></div>
      <div class="totals-row"><span>Discount</span>
        <div class="input-group input-group-sm" style="max-width:100%;">
          <span class="input-group-text">₱</span>
          <input type="number" id="discountInputOffcanvas" class="form-control text-end"
                 value="0" min="0" step="0.01" oninput="updateTotals()"/>
        </div>
      </div>
      <div class="totals-row grand">
        <span>TOTAL</span><span id="grandTotalDisplayOffcanvas">₱ 0.00</span>
      </div>
    </div>

    <!-- Payment in Offcanvas -->
    <div class="payment-section" style="border-top: 1px solid #e4ecef;">
      <label class="form-label d-block mb-1">Payment Amount</label>
      <div class="mb-2">
        <div class="input-group">
          <span class="input-group-text fw-bold">₱</span>
          <input type="number" id="paymentInputOffcanvas" class="form-control payment-input"
                 min="0" step="0.01" placeholder="0.00" oninput="updateChange()"/>
        </div>
      </div>
      <!-- Quick denominations -->
      <div class="d-flex flex-wrap gap-1 mb-2" id="denomBtnsOffcanvas">
        <?php foreach([20,50,100,200,500,1000] as $d): ?>
        <button class="btn btn-sm btn-outline-secondary"
                onclick="addDenom(<?=$d?>)">+₱<?=$d?></button>
        <?php endforeach; ?>
        <button class="btn btn-sm btn-outline-danger" onclick="clearPayment()">
          <i class="bi bi-x"></i>
        </button>
      </div>
      <!-- Change display -->
      <div class="change-display mb-2">
        <span class="change-label">Change</span>
        <span class="change-amount" id="changeDisplayOffcanvas">₱ 0.00</span>
      </div>
      <!-- Payment method -->
      <div class="d-flex gap-2 mb-2">
        <select id="paymentMethodOffcanvas" class="form-select form-select-sm">
          <option value="cash">Cash</option>
          <option value="gcash">GCash</option>
          <option value="card">Card</option>
        </select>
      </div>
      <button class="checkout-btn" id="checkoutBtnOffcanvas" disabled onclick="checkout()">
        <i class="bi bi-check-circle-fill me-2"></i>Checkout
      </button>
      <button class="btn btn-outline-danger w-100 mt-2" onclick="clearCart()">
        <i class="bi bi-trash me-1"></i>Clear Cart
      </button>
    </div>
  </div>
</div>

<?php
$extraJS = '
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="' . BASE_URL . 'js/pos.js"></script>';
require_once 'includes/footer.php';
?>
