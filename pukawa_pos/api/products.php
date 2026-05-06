<?php
// api/products.php  –  JSON API for product lookup
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');
$db     = getDB();
$action = $_GET['action'] ?? 'list';

switch ($action) {

  // ── List all active products ────────────────────────────
  case 'list':
    $products = $db->query(
        "SELECT p.product_id, p.barcode, p.product_name,
                p.category_id, c.category_name,
                p.price, p.stock_quantity, p.low_stock_level
         FROM products p
         JOIN categories c ON p.category_id=c.category_id
         WHERE p.is_active=1
         ORDER BY p.product_name"
    )->fetchAll();
    echo json_encode(['success' => true, 'products' => $products]);
    break;

  // ── Lookup by barcode ───────────────────────────────────
  case 'barcode':
    $code = trim($_GET['code'] ?? '');
    if (!$code) { echo json_encode(['success'=>false,'message'=>'No barcode']); break; }
    $stmt = $db->prepare(
        "SELECT p.product_id, p.barcode, p.product_name,
                p.category_id, c.category_name,
                p.price, p.stock_quantity, p.low_stock_level
         FROM products p
         JOIN categories c ON p.category_id=c.category_id
         WHERE p.barcode=? AND p.is_active=1 LIMIT 1"
    );
    $stmt->execute([$code]);
    $product = $stmt->fetch();
    if ($product) {
      echo json_encode(['success'=>true, 'product'=>$product]);
    } else {
      echo json_encode(['success'=>false, 'message'=>'Product not found']);
    }
    break;

  // ── Search ──────────────────────────────────────────────
  case 'search':
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $db->prepare(
        "SELECT p.product_id, p.barcode, p.product_name,
                p.category_id, c.category_name,
                p.price, p.stock_quantity
         FROM products p
         JOIN categories c ON p.category_id=c.category_id
         WHERE p.is_active=1 AND (p.product_name LIKE ? OR p.barcode LIKE ?)
         ORDER BY p.product_name LIMIT 30"
    );
    $stmt->execute([$q, $q]);
    echo json_encode(['success'=>true, 'products'=>$stmt->fetchAll()]);
    break;

  default:
    echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
