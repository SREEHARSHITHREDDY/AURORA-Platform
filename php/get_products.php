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

$user_id = intval($_SESSION['user_id']);

$sql = "
    SELECT p.id, p.name, p.category, p.price, p.target_sales,
           COALESCE(i.stock_qty, 0) AS stock_qty,
           COALESCE(i.min_stock, 10) AS min_stock
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id AND i.user_id = p.user_id
    WHERE p.user_id = $user_id
    ORDER BY p.category, p.name
";

$result = mysqli_query($conn, $sql);
if (!$result) {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    exit();
}

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

echo json_encode([
    'status'   => 'success',
    'products' => $products
]);
mysqli_close($conn);
?>