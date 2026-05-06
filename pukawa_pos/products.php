<?php
require_once 'config.php';
requireAdmin();
$pageTitle = 'Product Inventory';
$db = getDB();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $db->prepare("UPDATE products SET is_active=0 WHERE product_id=?");
    $stmt->execute([(int)$_GET['delete']]);
    header('Location: products.php?msg=deleted');
    exit;
}

// Handle add / edit
$editProduct = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editProduct = $db->prepare(
        "SELECT * FROM products WHERE product_id=? AND is_active=1"
    );
    $editProduct->execute([(int)$_GET['edit']]);
    $editProduct = $editProduct->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['product_id'] ?? 0);
    $data    = [
        'barcode'         => trim($_POST['barcode'] ?? ''),
        'product_name'    => trim($_POST['product_name'] ?? ''),
        'category_id'     => (int)($_POST['category_id'] ?? 0),
        'price'           => (float)($_POST['price'] ?? 0),
        'cost_price'      => (float)($_POST['cost_price'] ?? 0),
        'stock_quantity'  => min(9999, max(0, (int)($_POST['stock_quantity'] ?? 0))),
        'low_stock_level' => min(9999, max(1, (int)($_POST['low_stock_level'] ?? 5))),
        'expiration_date' => $_POST['expiration_date'] ?: null,
    ];

    if ($id) {
        $stmt = $db->prepare(
            "UPDATE products SET barcode=:barcode, product_name=:product_name,
             category_id=:category_id, price=:price, cost_price=:cost_price,
             stock_quantity=:stock_quantity, low_stock_level=:low_stock_level,
             expiration_date=:expiration_date
             WHERE product_id=$id"
        );
    } else {
        $stmt = $db->prepare(
            "INSERT INTO products (barcode,product_name,category_id,price,cost_price,
             stock_quantity,low_stock_level,expiration_date)
             VALUES (:barcode,:product_name,:category_id,:price,:cost_price,
             :stock_quantity,:low_stock_level,:expiration_date)"
        );
    }
    $stmt->execute($data);
    header('Location: products.php?msg=' . ($id ? 'updated' : 'added'));
    exit;
}

// ── Filters ───────────────────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$catFilter = (int)($_GET['cat'] ?? 0);
$lowOnly   = isset($_GET['low']);

$where = ['p.is_active=1'];
$params = [];
if ($search)    { $where[] = '(p.product_name LIKE ? OR p.barcode LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($catFilter) { $where[] = 'p.category_id=?'; $params[] = $catFilter; }
if ($lowOnly)   { $where[] = 'p.stock_quantity <= p.low_stock_level'; }

$sql = "SELECT p.*, c.category_name FROM products p
        JOIN categories c ON p.category_id=c.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.product_name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();

require_once 'includes/header.php';
?>

<?php if (isset($_GET['msg'])): $msgs=['added'=>['success','Product added successfully!'],'updated'=>['success','Product updated.'],'deleted'=>['warning','Product deactivated.']]; $m=$msgs[$_GET['msg']]??null; if($m): ?>
<div class="alert alert-<?=$m[0]?> d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-check-circle-fill"></i><?=$m[1]?>
</div>
<?php endif; endif; ?>

<!-- ── Add/Edit Modal ───────────────────────────────────── -->
<div class="modal fade" id="productModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="product_id" id="modal_product_id"/>
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add Product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Barcode</label>
              <div class="input-group">
                <input type="text" class="form-control" name="barcode" id="modal_barcode" placeholder="Scan or type barcode"/>
                <button type="button" class="btn btn-outline-secondary" onclick="startBarcodeModal()">
                  <i class="bi bi-camera"></i>
                </button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Product Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="product_name" id="modal_name" required/>
            </div>
            <div class="col-md-4">
              <label class="form-label">Category <span class="text-danger">*</span></label>
              <select class="form-select" name="category_id" id="modal_cat" required>
                <option value="">Select...</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?=$c['category_id']?>"><?=htmlspecialchars($c['category_name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Selling Price (₱) <span class="text-danger">*</span></label>
              <input type="number" class="form-control" name="price" id="modal_price" step="0.01" min="0" required/>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cost Price (₱)</label>
              <input type="number" class="form-control" name="cost_price" id="modal_cost" step="0.01" min="0" value="0"/>
            </div>
            <div class="col-md-4">
              <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
              <input type="number" class="form-control" name="stock_quantity" id="modal_stock" min="0" max="9999" oninput="this.value = Math.min(this.value, 9999)" required/>
            </div>
            <div class="col-md-4">
              <label class="form-label">Low Stock Level</label>
              <input type="number" class="form-control" name="low_stock_level" id="modal_lowstock" min="1" max="9999" oninput="this.value = Math.min(this.value, 9999)" value="5"/>
            </div>
            <div class="col-md-4">
              <label class="form-label">Expiration Date</label>
              <input type="date" class="form-control" name="expiration_date" id="modal_expiry"/>
            </div>
          </div>
          <!-- Inline barcode scanner for modal -->
          <div id="modal-scanner-wrap" class="mt-3" style="display:none">
            <div id="modal-reader" style="width:100%;max-height:240px;overflow:hidden;border-radius:10px;"></div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="stopBarcodeModal()">
              <i class="bi bi-x-circle"></i> Cancel Scan
            </button>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-brand px-4">Save Product</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Toolbar ───────────────────────────────────────────── -->
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <form class="d-flex gap-2 flex-wrap flex-grow-1" method="GET">
    <input type="text" name="search" class="form-control" style="max-width:240px"
           placeholder="Search product or barcode..." value="<?=htmlspecialchars($search)?>"/>
    <select name="cat" class="form-select" style="max-width:180px">
      <option value="">All Categories</option>
      <?php foreach($categories as $c): ?>
      <option value="<?=$c['category_id']?>" <?=$catFilter==$c['category_id']?'selected':''?>>
        <?=htmlspecialchars($c['category_name'])?>
      </option>
      <?php endforeach; ?>
    </select>
    <div class="form-check d-flex align-items-center gap-1 ms-1">
      <input class="form-check-input" type="checkbox" name="low" id="lowCheck" <?=$lowOnly?'checked':''?>>
      <label class="form-check-label fw-semibold text-danger" for="lowCheck" style="font-size:12.5px">Low Stock Only</label>
    </div>
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    <a href="products.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
  </form>
  <button class="btn btn-brand" onclick="openAddModal()">
    <i class="bi bi-plus-lg me-1"></i>Add Product
  </button>
</div>

<!-- ── Table ─────────────────────────────────────────────── -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h6 class="mb-0">Products <span class="badge badge-teal ms-2"><?=count($products)?></span></h6>
    <small class="text-muted"><?=$lowOnly ? '📊 Low Stock Items' : '📦 All Products'?></small>
  </div>
  <div class="table-responsive">
    <table id="productsTable" class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th style="width: 12%">Barcode</th>
          <th style="width: 22%">Product Name</th>
          <th style="width: 13%">Category</th>
          <th style="width: 12%; text-align: right">Selling Price</th>
          <th style="width: 12%; text-align: right">Cost Price</th>
          <th style="width: 12%; text-align: center">Stock Level</th>
          <th style="width: 12%">Expiration</th>
          <th style="width: 5%; text-align: center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
        <tr>
          <td colspan="8" class="text-center text-muted py-4">
            <i class="bi bi-inbox" style="font-size: 28px; opacity: 0.5;"></i>
            <div class="mt-2">No products found</div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach($products as $p): 
          $isLow = $p['stock_quantity'] <= $p['low_stock_level'];
          $isExpired = $p['expiration_date'] && strtotime($p['expiration_date']) < strtotime('now');
          $expiring = $p['expiration_date'] && strtotime($p['expiration_date']) <= strtotime('+30 days') && !$isExpired;
          $profit = $p['price'] - $p['cost_price'];
          $margin = $p['cost_price'] > 0 ? round(($profit / $p['cost_price']) * 100) : 0;
        ?>
        <tr class="<?=$isLow || $isExpired ? 'table-warning' : ''?>">
          <td>
            <code style="background: #f0f4f8; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
              <?=htmlspecialchars($p['barcode'] ?: '—')?>
            </code>
          </td>
          <td>
            <div class="fw-semibold"><?=htmlspecialchars($p['product_name'])?></div>
            <?php if($isLow): ?>
              <span class="badge bg-danger bg-opacity-75">
                <i class="bi bi-exclamation-triangle-fill"></i> Low Stock
              </span>
            <?php endif; ?>
            <?php if($isExpired): ?>
              <span class="badge bg-dark">
                <i class="bi bi-clock-history"></i> Expired
              </span>
            <?php elseif($expiring): ?>
              <span class="badge bg-warning text-dark">
                <i class="bi bi-calendar-x"></i> Expiring Soon
              </span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge bg-info bg-opacity-75">
              <?=htmlspecialchars($p['category_name'])?>
            </span>
          </td>
          <td class="fw-semibold text-end">
            <span style="color: #3a8fa3;">₱<?=number_format($p['price'], 2)?></span>
          </td>
          <td class="text-muted text-end">
            ₱<?=number_format($p['cost_price'], 2)?>
            <br/>
            <small style="color: #2d7a99; font-weight: 500;">
              <?=$margin > 0 ? '+' : ''?><?=$margin?>%
            </small>
          </td>
          <td class="text-center">
            <div class="d-flex flex-column gap-1">
              <div class="<?=$isLow ? 'fw-bold text-danger' : ''?>">
                <?=$p['stock_quantity'] > 9999 ? '9999+' : $p['stock_quantity']?> units
              </div>
              <small class="text-muted">Min: <?=$p['low_stock_level']?></small>
            </div>
          </td>
          <td>
            <?php if($p['expiration_date']): ?>
              <span class="<?=$isExpired ? 'text-danger fw-bold' : ($expiring ? 'text-warning fw-semibold' : '')?>">
                <?=date('M d, Y', strtotime($p['expiration_date']))?>
              </span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <div class="btn-group btn-group-sm" role="group">
              <button class="btn btn-outline-secondary" 
                      onclick='editProduct(<?=json_encode($p)?>)'
                      title="Edit product">
                <i class="bi bi-pencil"></i>
              </button>
              <a href="products.php?delete=<?=$p['product_id']?>"
                 class="btn btn-outline-danger"
                 onclick="return confirm('Deactivate this product?')"
                 title="Deactivate product">
                <i class="bi bi-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extraJS = '
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
// Initialize DataTable
$(document).ready(function() {
  $(\"#productsTable\").DataTable({
    responsive: true,
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, \"All\"]],
    order: [[1, \"asc\"]],
    columnDefs: [
      { orderable: false, targets: 7 } // Disable sorting on Actions column
    ],
    language: {
      search: \"_INPUT_\",
      searchPlaceholder: \"Search products...\",
      lengthMenu: \"Show _MENU_ entries\",
      info: \"Showing _START_ to _END_ of _TOTAL_ products\",
      paginate: {
        first: \"First\",
        last: \"Last\",
        next: \"Next\",
        previous: \"Previous\"
      }
    },
    dom: \"<\\\"info\\\"l>tr<\\\"bottom\\\"fp>\"
  });
});

let modalScanner = null;

function openAddModal() {
  document.getElementById("modalTitle").textContent = "Add Product";
  document.getElementById("modal_product_id").value = "";
  document.getElementById("modal_barcode").value   = "";
  document.getElementById("modal_name").value      = "";
  document.getElementById("modal_cat").value       = "";
  document.getElementById("modal_price").value     = "";
  document.getElementById("modal_cost").value      = "0";
  document.getElementById("modal_stock").value     = "";
  document.getElementById("modal_lowstock").value  = "5";
  document.getElementById("modal_expiry").value    = "";
  new bootstrap.Modal(document.getElementById("productModal")).show();
}

function editProduct(p) {
  document.getElementById("modalTitle").textContent = "Edit Product";
  document.getElementById("modal_product_id").value = p.product_id;
  document.getElementById("modal_barcode").value   = p.barcode || "";
  document.getElementById("modal_name").value      = p.product_name;
  document.getElementById("modal_cat").value       = p.category_id;
  document.getElementById("modal_price").value     = p.price;
  document.getElementById("modal_cost").value      = p.cost_price;
  document.getElementById("modal_stock").value     = p.stock_quantity;
  document.getElementById("modal_lowstock").value  = p.low_stock_level;
  document.getElementById("modal_expiry").value    = p.expiration_date || "";
  new bootstrap.Modal(document.getElementById("productModal")).show();
}

function startBarcodeModal() {
  document.getElementById("modal-scanner-wrap").style.display = "block";
  modalScanner = new Html5Qrcode("modal-reader");
  modalScanner.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: { width: 250, height: 80 } },
    (code) => {
      document.getElementById("modal_barcode").value = code;
      stopBarcodeModal();
    }
  ).catch(err => alert("Camera error: " + err));
}

function stopBarcodeModal() {
  if (modalScanner) {
    modalScanner.stop().catch(()=>{});
    modalScanner = null;
  }
  document.getElementById("modal-scanner-wrap").style.display = "none";
}

// Auto-open modal if ?action=add
if (new URLSearchParams(window.location.search).get("action") === "add") openAddModal();
</script>';
require_once 'includes/footer.php';
?>
