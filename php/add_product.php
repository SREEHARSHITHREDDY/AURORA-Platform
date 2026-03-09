<?php
ini_set('session.cookie_samesite', 'Lax');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit();
}

$user_id      = intval($_SESSION['user_id']);
$name         = mysqli_real_escape_string($conn, trim($_POST['name']     ?? ''));
$category     = mysqli_real_escape_string($conn, trim($_POST['category'] ?? ''));
$price        = floatval($_POST['price']        ?? 0);
$target_sales = intval($_POST['target_sales']   ?? 100);
$stock_qty    = intval($_POST['stock_qty']      ?? 0);
$min_stock    = intval($_POST['min_stock']      ?? 10);

if (!$name || !$category || $price <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Name, category and price are required']);
    exit();
}

// Insert product
$sql = "INSERT INTO products (user_id, name, category, price, target_sales) VALUES ($user_id, '$name', '$category', $price, $target_sales)";
if (!mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    exit();
}
$product_id = mysqli_insert_id($conn);

// Insert inventory row
mysqli_query($conn, "INSERT INTO inventory (product_id, user_id, stock_qty, min_stock) VALUES ($product_id, $user_id, $stock_qty, $min_stock)");

echo json_encode([
    'status'     => 'success',
    'message'    => "Product '$name' added successfully",
    'product_id' => $product_id
]);
mysqli_close($conn);
?>