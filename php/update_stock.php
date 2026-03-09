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

$user_id    = intval($_SESSION['user_id']);
$product_id = intval($_POST['product_id'] ?? 0);
$stock_qty  = intval($_POST['stock_qty']  ?? 0);
$min_stock  = intval($_POST['min_stock']  ?? 10);
$action     = $_POST['action'] ?? 'set'; // set | add | subtract

if (!$product_id) {
    echo json_encode(['status' => 'error', 'message' => 'Product ID required']);
    exit();
}

// Verify ownership
$check = mysqli_query($conn, "SELECT id FROM products WHERE id=$product_id AND user_id=$user_id LIMIT 1");
if (mysqli_num_rows($check) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit();
}

// Check if inventory row exists
$inv = mysqli_query($conn, "SELECT id FROM inventory WHERE product_id=$product_id AND user_id=$user_id LIMIT 1");

if ($action === 'add') {
    $sql_update = "UPDATE inventory SET stock_qty = stock_qty + $stock_qty, min_stock=$min_stock WHERE product_id=$product_id AND user_id=$user_id";
} elseif ($action === 'subtract') {
    $sql_update = "UPDATE inventory SET stock_qty = GREATEST(0, stock_qty - $stock_qty), min_stock=$min_stock WHERE product_id=$product_id AND user_id=$user_id";
} else {
    $sql_update = "UPDATE inventory SET stock_qty=$stock_qty, min_stock=$min_stock WHERE product_id=$product_id AND user_id=$user_id";
}

if (mysqli_num_rows($inv) === 0) {
    // Insert if not exists
    mysqli_query($conn, "INSERT INTO inventory (product_id, user_id, stock_qty, min_stock) VALUES ($product_id, $user_id, $stock_qty, $min_stock)");
} else {
    mysqli_query($conn, $sql_update);
}

// Get updated stock
$updated = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stock_qty FROM inventory WHERE product_id=$product_id AND user_id=$user_id"));

echo json_encode([
    'status'        => 'success',
    'message'       => 'Stock updated successfully',
    'new_stock_qty' => $updated['stock_qty'] ?? $stock_qty
]);
mysqli_close($conn);
?>