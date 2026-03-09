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

// ── Totals this month ─────────────────────────────────────────
$totals = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COALESCE(SUM(s.amount),   0) AS total_revenue,
        COALESCE(SUM(s.quantity), 0) AS total_units,
        COUNT(s.id)                  AS total_orders
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
"));

// ── Daily revenue last 30 days ────────────────────────────────
$daily_revenue = [];
$res_d = mysqli_query($conn, "
    SELECT DATE_FORMAT(s.sale_date, '%d %b') AS date,
           SUM(s.amount)   AS revenue,
           SUM(s.quantity) AS units
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.sale_date
    ORDER BY s.sale_date ASC
");
while ($r = mysqli_fetch_assoc($res_d)) {
    $daily_revenue[] = [
        'date'    => $r['date'],
        'revenue' => floatval($r['revenue']),
        'units'   => intval($r['units'])
    ];
}

// ── Category revenue this month ───────────────────────────────
$category_revenue = [];
$res_c = mysqli_query($conn, "
    SELECT p.category,
           SUM(s.amount)   AS revenue,
           SUM(s.quantity) AS units
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
    GROUP BY p.category
    ORDER BY revenue DESC
");
while ($r = mysqli_fetch_assoc($res_c)) {
    $category_revenue[] = [
        'category' => $r['category'],
        'revenue'  => floatval($r['revenue']),
        'units'    => intval($r['units'])
    ];
}

// ── Recent sales transactions (last 100) ─────────────────────
$recent_sales = [];
$res_s = mysqli_query($conn, "
    SELECT p.name, p.category,
           s.quantity, s.amount,
           DATE_FORMAT(s.sale_date, '%d %b %Y') AS sale_date
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
    ORDER BY s.sale_date DESC, s.id DESC
    LIMIT 100
");
while ($r = mysqli_fetch_assoc($res_s)) {
    $recent_sales[] = $r;
}

// ── Best sellers this month ───────────────────────────────────
$best_sellers = [];
$res_bs = mysqli_query($conn, "
    SELECT p.name, p.category,
           SUM(s.quantity) AS units_sold,
           SUM(s.amount)   AS revenue
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
    GROUP BY p.id, p.name, p.category
    ORDER BY revenue DESC
    LIMIT 5
");
while ($r = mysqli_fetch_assoc($res_bs)) $best_sellers[] = $r;

// ── Target vs actual ─────────────────────────────────────────
$target_vs_actual = [];
$res_tv = mysqli_query($conn, "
    SELECT p.name, p.target_sales,
           COALESCE(SUM(s.quantity),0) AS actual_sales,
           ROUND(COALESCE(SUM(s.quantity),0) / p.target_sales * 100) AS achievement
    FROM products p
    LEFT JOIN sales s ON s.product_id = p.id
        AND MONTH(s.sale_date) = MONTH(CURDATE())
        AND YEAR(s.sale_date)  = YEAR(CURDATE())
    WHERE p.user_id = $user_id AND p.target_sales > 0
    GROUP BY p.id, p.name, p.target_sales
    ORDER BY achievement DESC
    LIMIT 5
");
while ($r = mysqli_fetch_assoc($res_tv)) $target_vs_actual[] = $r;

echo json_encode([
    'status'           => 'success',
    'user_name'        => $_SESSION['user_name'] ?? '',
    'total_revenue'    => floatval($totals['total_revenue']),
    'total_units'      => intval($totals['total_units']),
    'total_orders'     => intval($totals['total_orders']),
    'daily_revenue'    => $daily_revenue,
    'category_revenue' => $category_revenue,
    'recent_sales'     => $recent_sales,
    'best_sellers'     => $best_sellers,
    'target_vs_actual' => $target_vs_actual
]);

mysqli_close($conn);
?>