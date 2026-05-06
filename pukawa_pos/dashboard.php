<?php
require_once 'config.php';
requireLogin();
$pageTitle = 'Dashboard';

$db = getDB();

// ── Stats ─────────────────────────────────────────────────
$todaySales = $db->query(
    "SELECT COALESCE(SUM(total_amount),0) AS total, COUNT(*) AS txns
     FROM transactions
     WHERE DATE(transaction_date)=CURDATE() AND status='completed'"
)->fetch();

$totalProducts = $db->query(
    "SELECT COUNT(*) AS cnt FROM products WHERE is_active=1"
)->fetchColumn();

$lowStock = $db->query(
    "SELECT COUNT(*) AS cnt FROM products
     WHERE is_active=1 AND stock_quantity <= low_stock_level"
)->fetchColumn();

$monthRevenue = $db->query(
    "SELECT COALESCE(SUM(total_amount),0) AS total
     FROM transactions
     WHERE MONTH(transaction_date)=MONTH(CURDATE())
       AND YEAR(transaction_date)=YEAR(CURDATE())
       AND status='completed'"
)->fetchColumn();

// ── Recent Transactions ───────────────────────────────────
$recentTxns = $db->query(
    "SELECT t.*, u.full_name AS cashier_name
     FROM transactions t JOIN users u ON t.cashier_id=u.user_id
     WHERE t.status='completed'
     ORDER BY t.transaction_date DESC LIMIT 8"
)->fetchAll();

// ── Low-stock products ─────────────────────────────────────
$lowStockProducts = $db->query(
    "SELECT p.*, c.category_name FROM products p
     JOIN categories c ON p.category_id=c.category_id
     WHERE p.is_active=1 AND p.stock_quantity <= p.low_stock_level
     ORDER BY p.stock_quantity ASC LIMIT 8"
)->fetchAll();

// ── Chart data – last 7 days ──────────────────────────────
$chartStmt = $db->query(
    "SELECT DATE(transaction_date) AS d, COALESCE(SUM(total_amount),0) AS rev
     FROM transactions
     WHERE transaction_date >= CURDATE() - INTERVAL 6 DAY AND status='completed'
     GROUP BY DATE(transaction_date)
     ORDER BY d"
);
$chartRows = $chartStmt->fetchAll();
$chartLabels = []; $chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($date));
    $found = 0;
    foreach ($chartRows as $r) { if ($r['d'] === $date) { $found = $r['rev']; break; } }
    $chartData[] = $found;
}

require_once 'includes/header.php';
?>

<div class="row g-3 mb-4">
  <!-- Stat cards -->
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon teal"><i class="bi bi-currency-exchange"></i></div>
      <div>
        <div class="stat-value"><?= fmtCurrency($todaySales['total']) ?></div>
        <div class="stat-label">Today's Sales</div>
        <div class="stat-sub"><?= $todaySales['txns'] ?> transactions</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-box-seam-fill"></i></div>
      <div>
        <div class="stat-value"><?= number_format($totalProducts) ?></div>
        <div class="stat-label">Total Products</div>
        <div class="stat-sub">Active items</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon red"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div>
        <div class="stat-value"><?= $lowStock ?></div>
        <div class="stat-label">Low Stock</div>
        <div class="stat-sub">Needs restocking</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-graph-up-arrow"></i></div>
      <div>
        <div class="stat-value"><?= fmtCurrency($monthRevenue) ?></div>
        <div class="stat-label">Monthly Revenue</div>
        <div class="stat-sub"><?= date('F Y') ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Sales chart -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header">
        <h6>Sales – Last 7 Days</h6>
        <span class="badge badge-teal">Revenue</span>
      </div>
      <div class="card-body">
        <canvas id="salesChart" height="110"></canvas>
      </div>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h6>Quick Actions</h6></div>
      <div class="card-body d-flex flex-column gap-2">
        <a href="<?= BASE_URL ?>pos.php" class="btn btn-brand d-flex align-items-center gap-2">
          <i class="bi bi-cart4"></i> Open Point of Sale
        </a>
        <?php if (currentUser()['role']==='admin'): ?>
        <a href="<?= BASE_URL ?>products.php?action=add" class="btn btn-outline-secondary d-flex align-items-center gap-2">
          <i class="bi bi-plus-circle"></i> Add New Product
        </a>
        <a href="<?= BASE_URL ?>reports.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
          <i class="bi bi-bar-chart-line"></i> View Reports
        </a>
        <a href="<?= BASE_URL ?>users.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
          <i class="bi bi-people"></i> Manage Users
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Recent transactions -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <h6>Recent Transactions</h6>
        <?php if (currentUser()['role']==='admin'): ?>
        <a href="<?= BASE_URL ?>reports.php" class="btn btn-sm btn-outline-secondary">View All</a>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Txn No.</th><th>Cashier</th>
              <th>Total</th><th>Method</th><th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentTxns)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No transactions yet today.</td></tr>
            <?php else: ?>
            <?php foreach ($recentTxns as $t): ?>
            <tr>
              <td><code class="text-brand" style="color:var(--brand)"><?= htmlspecialchars($t['transaction_no']) ?></code></td>
              <td><?= htmlspecialchars($t['cashier_name']) ?></td>
              <td class="fw-semibold"><?= fmtCurrency($t['total_amount']) ?></td>
              <td><span class="badge badge-teal"><?= ucfirst($t['payment_method']) ?></span></td>
              <td class="text-muted"><?= date('h:i A', strtotime($t['transaction_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Low stock -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">
        <h6><i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>Low Stock Alerts</h6>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Product</th><th>Stock</th><th>Min</th></tr>
          </thead>
          <tbody>
            <?php if (empty($lowStockProducts)): ?>
            <tr><td colspan="3" class="text-center text-muted py-4">
              <i class="bi bi-check-circle text-success"></i> All stock levels OK!
            </td></tr>
            <?php else: ?>
            <?php foreach ($lowStockProducts as $p): ?>
            <tr>
              <td>
                <div class="fw-semibold" style="font-size:13px"><?= htmlspecialchars($p['product_name']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($p['category_name']) ?></small>
              </td>
              <td>
                <span class="low-stock-pill">
                  <i class="bi bi-exclamation-circle-fill"></i>
                  <?= $p['stock_quantity'] ?>
                </span>
              </td>
              <td class="text-muted"><?= $p['low_stock_level'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
function fmtCurrency($n) { return '₱ '.number_format((float)$n,2); }
$extraJS = '<script>
const ctx = document.getElementById("salesChart");
new Chart(ctx, {
  type: "bar",
  data: {
    labels: ' . json_encode($chartLabels) . ',
    datasets: [{
      label: "Revenue",
      data: ' . json_encode($chartData) . ',
      backgroundColor: "rgba(13,115,119,.2)",
      borderColor: "#0d7377",
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { callback: v => "₱"+v.toLocaleString() },
        grid: { color: "#f0f0f0" }
      },
      x: { grid: { display: false } }
    }
  }
});
</script>';
require_once 'includes/footer.php';
?>
