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
    SELECT 
        p.category,
        SUM(s.total_price) AS revenue,
        SUM(s.quantity) AS units_sold
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.vendor_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
    GROUP BY p.category
    ORDER BY revenue DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    exit();
}

$categories = [];
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

echo json_encode(['status' => 'success', 'categories' => $categories]);
mysqli_close($conn);
?>