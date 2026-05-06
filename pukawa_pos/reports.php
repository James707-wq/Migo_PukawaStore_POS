<?php
require_once 'config.php';
requireAdmin();
$pageTitle = 'Sales Reports';
$db = getDB();

// ── Date range ────────────────────────────────────────────
$range  = $_GET['range'] ?? 'daily';
$from   = $_GET['from']  ?? date('Y-m-01');
$to     = $_GET['to']    ?? date('Y-m-d');

switch ($range) {
    case 'daily':  $from = date('Y-m-d'); $to = $from; break;
    case 'weekly': $from = date('Y-m-d',strtotime('monday this week')); $to = date('Y-m-d'); break;
    case 'monthly':$from = date('Y-m-01'); $to = date('Y-m-d'); break;
}

// ── Summary ───────────────────────────────────────────────
$summary = $db->prepare(
    "SELECT COUNT(*) AS transactions,
            COALESCE(SUM(total_amount),0) AS revenue,
            COALESCE(SUM(discount_amount),0) AS discounts,
            COALESCE(AVG(total_amount),0) AS avg_sale
     FROM transactions
     WHERE status='completed' AND DATE(transaction_date) BETWEEN ? AND ?"
);
$summary->execute([$from, $to]);
$summary = $summary->fetch();

// ── Daily breakdown ───────────────────────────────────────
$dailyStmt = $db->prepare(
    "SELECT DATE(transaction_date) AS d,
            COUNT(*) AS txns,
            SUM(total_amount) AS revenue
     FROM transactions
     WHERE status='completed' AND DATE(transaction_date) BETWEEN ? AND ?
     GROUP BY DATE(transaction_date) ORDER BY d"
);
$dailyStmt->execute([$from, $to]);
$dailyRows = $dailyStmt->fetchAll();

// ── Best selling products ─────────────────────────────────
$bestStmt = $db->prepare(
    "SELECT p.product_name, c.category_name,
            SUM(ti.quantity) AS total_qty,
            SUM(ti.subtotal) AS total_rev
     FROM transaction_items ti
     JOIN transactions t ON ti.transaction_id=t.transaction_id
     JOIN products p ON ti.product_id=p.product_id
     JOIN categories c ON p.category_id=c.category_id
     WHERE t.status='completed' AND DATE(t.transaction_date) BETWEEN ? AND ?
     GROUP BY ti.product_id
     ORDER BY total_qty DESC LIMIT 10"
);
$bestStmt->execute([$from,$to]);
$bestProducts = $bestStmt->fetchAll();

// ── Payment method breakdown ──────────────────────────────
$methodStmt = $db->prepare(
    "SELECT payment_method, COUNT(*) AS cnt, SUM(total_amount) AS rev
     FROM transactions
     WHERE status='completed' AND DATE(transaction_date) BETWEEN ? AND ?
     GROUP BY payment_method"
);
$methodStmt->execute([$from,$to]);
$methods = $methodStmt->fetchAll();

// Chart
$chartLabels = array_map(fn($r)=>date('M d',strtotime($r['d'])), $dailyRows);
$chartData   = array_column($dailyRows, 'revenue');

require_once 'includes/header.php';
function fmtCurrency($n){return '₱ '.number_format((float)$n,2);}
?>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
      <div>
        <label class="form-label">Quick Range</label>
        <div class="btn-group">
          <?php foreach(['daily'=>'Today','weekly'=>'This Week','monthly'=>'This Month','custom'=>'Custom'] as $k=>$v): ?>
          <a href="?range=<?=$k?>" class="btn btn-sm <?=$range===$k?'btn-brand':'btn-outline-secondary'?>"><?=$v?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if($range==='custom'): ?>
      <div>
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control form-control-sm"
               value="<?=$from?>" max="<?=date('Y-m-d')?>"/>
      </div>
      <div>
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control form-control-sm"
               value="<?=$to?>" max="<?=date('Y-m-d')?>"/>
      </div>
      <input type="hidden" name="range" value="custom"/>
      <button type="submit" class="btn btn-sm btn-brand">Apply</button>
      <?php endif; ?>
    </form>
    
    <!-- Download PDF Button -->
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e8f6fa;">
      <a href="<?= BASE_URL ?>api/generate_pdf.php?range=<?=$range?>&from=<?=$from?>&to=<?=$to?>" 
         class="btn btn-sm btn-outline-primary" target="_blank">
        <i class="bi bi-file-pdf"></i> Download PDF Report
      </a>
      <small class="text-muted ms-2">Generates a formatted PDF of this report</small>
    </div>
    
    <!-- Download PDF Button -->
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e8f6fa;">
      <a href="<?= BASE_URL ?>api/generate_pdf.php?range=<?=$range?>&from=<?=$from?>&to=<?=$to?>" 
         class="btn btn-sm btn-outline-primary" target="_blank">
        <i class="bi bi-file-pdf"></i> Download PDF Report
      </a>
      <small class="text-muted ms-2">Generates a formatted PDF of this report</small>
    </div>
  </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon teal"><i class="bi bi-cash-stack"></i></div>
      <div><div class="stat-value"><?=fmtCurrency($summary['revenue'])?></div>
           <div class="stat-label">Total Revenue</div></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-receipt"></i></div>
      <div><div class="stat-value"><?=number_format($summary['transactions'])?></div>
           <div class="stat-label">Transactions</div></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-graph-up"></i></div>
      <div><div class="stat-value"><?=fmtCurrency($summary['avg_sale'])?></div>
           <div class="stat-label">Avg. Sale</div></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon amber"><i class="bi bi-tag-fill"></i></div>
      <div><div class="stat-value"><?=fmtCurrency($summary['discounts'])?></div>
           <div class="stat-label">Total Discounts</div></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <!-- Revenue chart -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><h6>Revenue Chart</h6></div>
      <div class="card-body">
        <?php if(empty($dailyRows)): ?>
        <div class="text-center text-muted py-5">No data for selected period.</div>
        <?php else: ?>
        <canvas id="revenueChart" height="120"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <!-- Payment methods -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h6>Payment Methods</h6></div>
      <div class="card-body">
        <?php if(empty($methods)): ?>
        <div class="text-center text-muted py-4">No data.</div>
        <?php else: ?>
        <canvas id="methodChart" height="160"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Best sellers -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><h6>Best-Selling Products</h6></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php if(empty($bestProducts)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No sales data.</td></tr>
            <?php else: ?>
            <?php foreach($bestProducts as $i=>$p): ?>
            <tr>
              <td><strong><?=$i+1?></strong></td>
              <td><?=htmlspecialchars($p['product_name'])?></td>
              <td><span class="badge badge-teal"><?=htmlspecialchars($p['category_name'])?></span></td>
              <td class="fw-semibold"><?=number_format($p['total_qty'])?></td>
              <td class="fw-semibold"><?=fmtCurrency($p['total_rev'])?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Daily breakdown -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><h6>Daily Breakdown</h6></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Date</th><th>Transactions</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php if(empty($dailyRows)): ?>
            <tr><td colspan="3" class="text-center text-muted py-4">No data.</td></tr>
            <?php else: ?>
            <?php foreach($dailyRows as $r): ?>
            <tr>
              <td><?=date('M d, Y',strtotime($r['d']))?></td>
              <td><?=$r['txns']?></td>
              <td class="fw-semibold"><?=fmtCurrency($r['revenue'])?></td>
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
$mLabels = array_map(fn($r)=>ucfirst($r['payment_method']), $methods);
$mData   = array_column($methods,'cnt');
$extraJS = '<script>
' . (count($dailyRows) ? '
new Chart(document.getElementById("revenueChart"),{
  type:"line",
  data:{
    labels:'.json_encode($chartLabels).',
    datasets:[{
      label:"Revenue",
      data:'.json_encode($chartData).',
      borderColor:"#0d7377",
      backgroundColor:"rgba(13,115,119,.1)",
      fill:true,
      tension:.4,
      pointBackgroundColor:"#0d7377",
      pointRadius:4
    }]
  },
  options:{
    responsive:true,
    plugins:{legend:{display:false}},
    scales:{
      y:{ticks:{callback:v=>"₱"+v.toLocaleString()},grid:{color:"#f0f0f0"},beginAtZero:true},
      x:{grid:{display:false}}
    }
  }
});' : '') . '
' . (count($methods) ? '
new Chart(document.getElementById("methodChart"),{
  type:"doughnut",
  data:{
    labels:'.json_encode($mLabels).',
    datasets:[{
      data:'.json_encode($mData).',
      backgroundColor:["#0d7377","#f5a623","#2980b9","#27ae60"],
      borderWidth:0
    }]
  },
  options:{responsive:true,plugins:{legend:{position:"bottom"}}}
});' : '') . '
</script>';
require_once 'includes/footer.php';
?>
