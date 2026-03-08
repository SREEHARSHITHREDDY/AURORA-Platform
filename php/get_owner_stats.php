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

// Owner only
if (($_SESSION['user_role'] ?? '') !== 'owner') {
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

// ── Total revenue across ALL vendors this month ──────────────
$sql_rev = "
    SELECT SUM(s.amount) AS total_revenue, COUNT(s.id) AS total_orders
    FROM sales s
    WHERE MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
";
$res_rev = mysqli_query($conn, $sql_rev);
$rev_row = mysqli_fetch_assoc($res_rev);

// ── Vendor count & product count ────────────────────────────
$vendor_count  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role='vendor'"))['c'];
$product_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products"))['c'];

// ── Revenue per vendor (branch comparison) ───────────────────
$sql_branches = "
    SELECT 
        u.name AS vendor_name,
        u.business_name,
        COALESCE(SUM(s.amount), 0)   AS revenue,
        COALESCE(SUM(s.quantity), 0) AS units_sold,
        COUNT(DISTINCT p.id)         AS products
    FROM users u
    LEFT JOIN products p ON p.user_id = u.id
    LEFT JOIN sales s ON s.product_id = p.id
        AND MONTH(s.sale_date) = MONTH(CURDATE())
        AND YEAR(s.sale_date)  = YEAR(CURDATE())
    WHERE u.role = 'vendor'
    GROUP BY u.id, u.name, u.business_name
    ORDER BY revenue DESC
";
$branches = [];
$res_b = mysqli_query($conn, $sql_branches);
while ($row = mysqli_fetch_assoc($res_b)) {
    $branches[] = $row;
}

// ── Monthly trend (last 6 months, all vendors) ───────────────
$sql_trend = "
    SELECT 
        DATE_FORMAT(sale_date,'%b %Y') AS label,
        DATE_FORMAT(sale_date,'%Y-%m') AS sort_key,
        SUM(amount) AS revenue
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY sort_key, label
    ORDER BY sort_key ASC
";
$trend = [];
$res_t = mysqli_query($conn, $sql_trend);
while ($row = mysqli_fetch_assoc($res_t)) {
    $trend[] = $row;
}

// ── Risk alerts ───────────────────────────────────────────────
$risks = [];

// Risk 1: Out of stock products
$sql_oos = "SELECT COUNT(*) AS c FROM inventory WHERE stock_qty = 0";
$oos = intval(mysqli_fetch_assoc(mysqli_query($conn, $sql_oos))['c']);
if ($oos > 0) {
    $risks[] = ['level'=>'critical','title'=>"$oos Products Out of Stock",
        'description'=>"$oos products have zero stock across all vendors. Immediate restocking required to prevent revenue loss."];
}

// Risk 2: Low stock
$sql_low = "SELECT COUNT(*) AS c FROM inventory WHERE stock_qty > 0 AND stock_qty <= min_stock";
$low = intval(mysqli_fetch_assoc(mysqli_query($conn, $sql_low))['c']);
if ($low > 0) {
    $risks[] = ['level'=>'warning','title'=>"$low Products Below Minimum Stock",
        'description'=>"$low products are running low. Coordinate with vendors to reorder before stockouts occur."];
}

// Risk 3: Products with no sales in 14+ days
$sql_dead = "
    SELECT COUNT(DISTINCT p.id) AS c
    FROM products p
    LEFT JOIN sales s ON s.product_id = p.id
    WHERE DATEDIFF(CURDATE(), (SELECT MAX(sale_date) FROM sales WHERE product_id = p.id)) > 14
       OR NOT EXISTS (SELECT 1 FROM sales WHERE product_id = p.id)
";
$dead = intval(mysqli_fetch_assoc(mysqli_query($conn, $sql_dead))['c']);
if ($dead > 0) {
    $risks[] = ['level'=>'warning','title'=>"$dead Products with No Recent Sales",
        'description'=>"$dead products have not recorded any sales in over 14 days. Review pricing and visibility strategies."];
}

// Risk 4: If all looks good
if (empty($risks)) {
    $risks[] = ['level'=>'ok','title'=>'All Systems Healthy',
        'description'=>'No critical issues detected. All stock levels and sales targets are on track.'];
}

// ── Low performing vendors ────────────────────────────────────
$total_rev = floatval($rev_row['total_revenue'] ?? 0);
foreach ($branches as $b) {
    $share = $total_rev > 0 ? round((floatval($b['revenue']) / $total_rev) * 100, 1) : 0;
    if ($share < 10 && floatval($b['revenue']) < 5000) {
        $risks[] = ['level'=>'warning',
            'title'=>"Low Performance: {$b['business_name']}",
            'description'=>"{$b['business_name']} contributed only ₹" . number_format($b['revenue'], 2) . " ({$share}%) of total revenue this month."];
    }
}

// ── Top products across all vendors ──────────────────────────
$sql_top = "
    SELECT p.name, p.category, u.business_name AS vendor,
           SUM(s.amount) AS revenue, SUM(s.quantity) AS units
    FROM sales s
    JOIN products p ON s.product_id = p.id
    JOIN users u ON p.user_id = u.id
    WHERE MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
    GROUP BY p.id, p.name, p.category, u.business_name
    ORDER BY revenue DESC
    LIMIT 8
";
$top_products = [];
$res_top = mysqli_query($conn, $sql_top);
while ($row = mysqli_fetch_assoc($res_top)) {
    $top_products[] = $row;
}

echo json_encode([
    'status'        => 'success',
    'user_name'     => $_SESSION['user_name'] ?? '',
    'total_revenue' => floatval($rev_row['total_revenue'] ?? 0),
    'total_orders'  => intval($rev_row['total_orders']  ?? 0),
    'vendor_count'  => $vendor_count,
    'product_count' => $product_count,
    'branches'      => $branches,
    'trend'         => $trend,
    'risks'         => $risks,
    'top_products'  => $top_products
]);

mysqli_close($conn);
?>