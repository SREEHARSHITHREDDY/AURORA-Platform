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

// Get inventory with last sale date per product
$sql = "
    SELECT 
        i.product_id,
        p.name AS product_name,
        p.category,
        i.stock_qty,
        i.min_stock,
        DATEDIFF(CURDATE(), MAX(s.sale_date)) AS days_since_last_sale,
        CASE WHEN i.stock_qty <= i.min_stock AND i.stock_qty > 0 THEN 1 ELSE 0 END AS is_low_stock,
        CASE WHEN DATEDIFF(CURDATE(), MAX(s.sale_date)) > 14 OR MAX(s.sale_date) IS NULL THEN 1 ELSE 0 END AS is_dead_stock
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    LEFT JOIN sales s ON s.product_id = p.id
    WHERE p.vendor_id = $user_id
    GROUP BY i.product_id, p.name, p.category, i.stock_qty, i.min_stock
    ORDER BY i.stock_qty ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . mysqli_error($conn)]);
    exit();
}

$inventory = [];
$total_stock = 0;
$low_stock   = 0;
$out_of_stock = 0;
$dead_stock  = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $inventory[] = $row;
    $total_stock += intval($row['stock_qty']);
    if ($row['stock_qty'] == 0)          $out_of_stock++;
    elseif ($row['is_low_stock'] == 1)   $low_stock++;
    if ($row['is_dead_stock'] == 1 && $row['stock_qty'] > 0) $dead_stock++;
}

echo json_encode([
    'status'    => 'success',
    'user_name' => $_SESSION['user_name'] ?? '',
    'inventory' => $inventory,
    'stats'     => [
        'total_stock'  => $total_stock,
        'low_stock'    => $low_stock,
        'out_of_stock' => $out_of_stock,
        'dead_stock'   => $dead_stock
    ]
]);

mysqli_close($conn);
?>