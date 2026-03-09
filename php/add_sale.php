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
$quantity   = intval($_POST['quantity']   ?? 0);
$sale_date  = mysqli_real_escape_string($conn, $_POST['sale_date'] ?? date('Y-m-d'));

if (!$product_id || $quantity <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product or quantity']);
    exit();
}

// Verify product belongs to this user
$check = mysqli_query($conn, "SELECT id, price FROM products WHERE id=$product_id AND user_id=$user_id LIMIT 1");
if (mysqli_num_rows($check) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Product not found']);
    exit();
}
$product = mysqli_fetch_assoc($check);
$amount  = round($product['price'] * $quantity, 2);

// Insert sale
$sql = "INSERT INTO sales (product_id, user_id, quantity, amount, sale_date) VALUES ($product_id, $user_id, $quantity, $amount, '$sale_date')";
if (!mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    exit();
}

// Update inventory
mysqli_query($conn, "UPDATE inventory SET stock_qty = GREATEST(0, stock_qty - $quantity) WHERE product_id=$product_id AND user_id=$user_id");

echo json_encode([
    'status'  => 'success',
    'message' => "Sale recorded — $quantity units × ₹{$product['price']} = ₹$amount",
    'amount'  => $amount
]);
mysqli_close($conn);
?>