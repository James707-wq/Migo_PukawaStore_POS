<?php
// includes/header.php  –  HTML head + open <body> + sidebar
$currentUser = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — Pukawa Store POS</title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <!-- Bootstrap Icons -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <!-- Google Fonts: Nunito + Outfit -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"/>
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>css/app.css"/>
  <!-- DataTables CSS -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"/>
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css"/>
</head>
<body>

<!-- ═══════════════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════════════ -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <img src="<?= BASE_URL ?>img/logo.png" alt="Pukawa Store" class="brand-logo-img"/>
    <div>
      <div class="brand-name">Pukawa<span> Store</span></div>
      <div class="brand-sub">POS System</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>

    <a href="<?= BASE_URL ?>dashboard.php"
       class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
      <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
    </a>

    <a href="<?= BASE_URL ?>pos.php"
       class="nav-item <?= $currentPage==='pos'?'active':'' ?>">
      <i class="bi bi-cart4"></i><span>Point of Sale</span>
    </a>

    <?php if ($currentUser['role'] === 'admin'): ?>
    <div class="nav-section-label">Manage</div>

    <a href="<?= BASE_URL ?>products.php"
       class="nav-item <?= $currentPage==='products'?'active':'' ?>">
      <i class="bi bi-box-seam-fill"></i><span>Products</span>
    </a>

    <a href="<?= BASE_URL ?>returns.php"
       class="nav-item <?= $currentPage==='returns'?'active':'' ?>">
      <i class="bi bi-arrow-counterclockwise"></i><span>Returns & Refunds</span>
    </a>

    <a href="<?= BASE_URL ?>reports.php"
       class="nav-item <?= $currentPage==='reports'?'active':'' ?>">
      <i class="bi bi-bar-chart-line-fill"></i><span>Sales Reports</span>
    </a>

    <a href="<?= BASE_URL ?>users.php"
       class="nav-item <?= $currentPage==='users'?'active':'' ?>">
      <i class="bi bi-people-fill"></i><span>Users</span>
    </a>
    <?php endif; ?>

    <div class="nav-section-label">Account</div>

    <a href="<?= BASE_URL ?>logout.php" class="nav-item text-danger-nav">
      <i class="bi bi-box-arrow-left"></i><span>Logout</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-badge">
      <div class="user-avatar">
        <?= strtoupper(substr($currentUser['full_name'], 0, 1)) ?>
      </div>
      <div>
        <div class="user-name"><?= htmlspecialchars($currentUser['full_name']) ?></div>
        <div class="user-role"><?= ucfirst($currentUser['role']) ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     MAIN WRAPPER
═══════════════════════════════════════════════ -->
<div class="main-wrapper" id="mainWrapper">

  <!-- Top bar -->
  <div class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></div>
    <div class="topbar-right">
      <span class="store-clock" id="storeClock"></span>
      <span class="badge-role badge-role--<?= $currentUser['role'] ?>">
        <?= ucfirst($currentUser['role']) ?>
      </span>
    </div>
  </div>

  <!-- Page content starts here -->
  <div class="page-content">
