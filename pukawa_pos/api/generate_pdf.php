<?php
require_once '../config.php';
requireAdmin();

// ── Get parameters ────────────────────────────────────────
$range  = $_GET['range'] ?? 'daily';
$from   = $_GET['from']  ?? date('Y-m-01');
$to     = $_GET['to']    ?? date('Y-m-d');

switch ($range) {
    case 'daily':  $from = date('Y-m-d'); $to = $from; break;
    case 'weekly': $from = date('Y-m-d',strtotime('monday this week')); $to = date('Y-m-d'); break;
    case 'monthly':$from = date('Y-m-01'); $to = date('Y-m-d'); break;
}

$db = getDB();

// ── Get summary ───────────────────────────────────────────
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

// ── Get daily breakdown ────────────────────────────────────
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

// ── Get best selling products ──────────────────────────────
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

// ── Get payment method breakdown ───────────────────────────
$methodStmt = $db->prepare(
    "SELECT payment_method, COUNT(*) AS cnt, SUM(total_amount) AS rev
     FROM transactions
     WHERE status='completed' AND DATE(transaction_date) BETWEEN ? AND ?
     GROUP BY payment_method"
);
$methodStmt->execute([$from,$to]);
$methods = $methodStmt->fetchAll();

function fmtCurrency($n) { return '₱ '.number_format((float)$n,2); }

// ── Build HTML content ─────────────────────────────────────
$bestRows = '';
if (empty($bestProducts)) {
    $bestRows = '<tr><td colspan="5" style="text-align:center; padding: 20px;">No sales data available</td></tr>';
} else {
    foreach ($bestProducts as $i => $p) {
        $bestRows .= '<tr>'
            . '<td style="padding:10px">' . ($i + 1) . '</td>'
            . '<td style="padding:10px">' . htmlspecialchars($p['product_name']) . '</td>'
            . '<td style="padding:10px"><span style="background:#e8f6fa;color:#3a8fa3;padding:4px 8px;border-radius:3px;font-size:11px">' . htmlspecialchars($p['category_name']) . '</span></td>'
            . '<td style="padding:10px;text-align:right">' . number_format($p['total_qty']) . '</td>'
            . '<td style="padding:10px;text-align:right;font-weight:bold">' . fmtCurrency($p['total_rev']) . '</td>'
            . '</tr>';
    }
}

$dailyRows_html = '';
if (empty($dailyRows)) {
    $dailyRows_html = '<tr><td colspan="3" style="text-align:center; padding:20px;">No sales data available</td></tr>';
} else {
    foreach ($dailyRows as $row) {
        $dailyRows_html .= '<tr>'
            . '<td style="padding:10px">' . date('M d, Y', strtotime($row['d'])) . '</td>'
            . '<td style="padding:10px;text-align:right">' . number_format($row['txns']) . '</td>'
            . '<td style="padding:10px;text-align:right;font-weight:bold">' . fmtCurrency($row['revenue']) . '</td>'
            . '</tr>';
    }
}

$methods_html = '';
if (empty($methods)) {
    $methods_html = '<tr><td colspan="3" style="text-align:center;padding:20px;">No payment data available</td></tr>';
} else {
    foreach ($methods as $m) {
        $methods_html .= '<tr>'
            . '<td style="padding:10px;text-transform:capitalize">' . htmlspecialchars($m['payment_method']) . '</td>'
            . '<td style="padding:10px;text-align:right">' . number_format($m['cnt']) . '</td>'
            . '<td style="padding:10px;text-align:right;font-weight:bold">' . fmtCurrency($m['rev']) . '</td>'
            . '</tr>';
    }
}

// ── Generate PDF using simple HTML that browsers can print ──
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="Sales_Report_' . date('Y-m-d_His') . '.html"');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Report - Pukawa Store POS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; 
            color: #333; 
            background: #fff; 
            padding: 0;
            margin: 0;
        }
        .container { 
            max-width: 210mm; 
            margin: 0 auto; 
            padding: 20mm; 
            background: white;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid #3a8fa3; 
            padding-bottom: 20px; 
        }
        .header h1 { 
            font-size: 28px; 
            color: #3a8fa3; 
            margin-bottom: 5px; 
            font-weight: 700;
        }
        .header p { 
            color: #666; 
            font-size: 14px; 
            margin-bottom: 10px;
        }
        .date-range { 
            text-align: center; 
            color: #666; 
            font-size: 13px; 
            margin-bottom: 30px; 
            font-weight: bold; 
        }
        
        .summary { 
            display: grid; 
            grid-template-columns: repeat(4, 1fr); 
            gap: 15px; 
            margin-bottom: 30px; 
        }
        .summary-card { 
            background: #f8fafb; 
            border-left: 4px solid #3a8fa3; 
            padding: 15px; 
            border-radius: 4px; 
            page-break-inside: avoid;
        }
        .summary-card .value { 
            font-size: 20px; 
            font-weight: bold; 
            color: #3a8fa3; 
        }
        .summary-card .label { 
            font-size: 12px; 
            color: #666; 
            margin-top: 5px; 
        }
        
        .section { 
            margin-bottom: 30px; 
            page-break-inside: avoid;
        }
        .section h2 { 
            font-size: 16px; 
            color: #3a8fa3; 
            margin-bottom: 12px; 
            border-bottom: 2px solid #e8f6fa; 
            padding-bottom: 8px; 
            font-weight: 700;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        th { 
            background: #e8f6fa; 
            color: #3a8fa3; 
            font-weight: bold; 
            padding: 12px; 
            text-align: left; 
            font-size: 13px;
            border-bottom: 2px solid #3a8fa3;
        }
        td { 
            padding: 10px 12px; 
            border-bottom: 1px solid #e8f6fa; 
            font-size: 13px; 
        }
        tr:nth-child(even) { 
            background: #f8fafb; 
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .footer { 
            margin-top: 40px; 
            text-align: center; 
            font-size: 12px; 
            color: #999; 
            border-top: 1px solid #e8f6fa; 
            padding-top: 15px; 
        }
        
        .print-hint {
            background: #e8f6fa;
            border: 1px solid #3a8fa3;
            color: #3a8fa3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
            font-size: 13px;
        }
        
        @media print { 
            body { margin: 0; padding: 0; } 
            .container { margin: 0; padding: 20mm; }
            .print-hint { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-hint">
            <strong>💾 To save as PDF:</strong> Use Ctrl+P (or Cmd+P on Mac), then select "Save as PDF" in the print dialog.
        </div>
        
        <div class="header">
            <h1>📊 Pukawa Store POS</h1>
            <p>Sales Report</p>
        </div>
        
        <div class="date-range">
            Report Period: <?= date('M d, Y', strtotime($from)) ?> to <?= date('M d, Y', strtotime($to)) ?>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary">
            <div class="summary-card">
                <div class="value"><?= number_format($summary['transactions']) ?></div>
                <div class="label">Total Transactions</div>
            </div>
            <div class="summary-card">
                <div class="value"><?= fmtCurrency($summary['revenue']) ?></div>
                <div class="label">Total Revenue</div>
            </div>
            <div class="summary-card">
                <div class="value"><?= fmtCurrency($summary['avg_sale']) ?></div>
                <div class="label">Avg. Sale</div>
            </div>
            <div class="summary-card">
                <div class="value"><?= fmtCurrency($summary['discounts']) ?></div>
                <div class="label">Total Discounts</div>
            </div>
        </div>
        
        <!-- Best Sellers -->
        <div class="section">
            <h2>📈 Best-Selling Products</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%">#</th>
                        <th style="width: 35%">Product Name</th>
                        <th style="width: 25%">Category</th>
                        <th style="width: 15%;text-align:right">Qty Sold</th>
                        <th style="width: 20%;text-align:right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $bestRows ?>
                </tbody>
            </table>
        </div>
        
        <!-- Daily Breakdown -->
        <div class="section">
            <h2>📅 Daily Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%">Date</th>
                        <th style="width: 30%;text-align:right">Transactions</th>
                        <th style="width: 30%;text-align:right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $dailyRows_html ?>
                </tbody>
            </table>
        </div>
        
        <!-- Payment Methods -->
        <div class="section">
            <h2>💳 Payment Methods</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%">Payment Method</th>
                        <th style="width: 30%;text-align:right">Transactions</th>
                        <th style="width: 30%;text-align:right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?= $methods_html ?>
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            <p>Generated on <?= date('M d, Y h:i A') ?> by Pukawa Store POS System</p>
            <p style="margin-top:10px;color:#ccc;font-size:11px">© Pukawa Store 2026. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        // Auto-open print dialog when page loads
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
