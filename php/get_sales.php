<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$period  = $_GET['period'] ?? 'week'; // week | month

// ---- SALES CHART DATA ----
if ($period === 'week') {
    // Last 7 days
    $chart_sql = "SELECT 
                    DATE_FORMAT(sale_date, '%a') as label,
                    sale_date,
                    COALESCE(SUM(amount), 0) as total
                  FROM sales
                  WHERE user_id = $user_id
                  AND sale_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)
                  GROUP BY sale_date
                  ORDER BY sale_date ASC";
} else {
    // Last 30 days grouped by week
    $chart_sql = "SELECT 
                    CONCAT('Week ', WEEK(sale_date) - WEEK(DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)) + 1) as label,
                    WEEK(sale_date) as sale_date,
                    COALESCE(SUM(amount), 0) as total
                  FROM sales
                  WHERE user_id = $user_id
                  AND sale_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                  GROUP BY WEEK(sale_date)
                  ORDER BY WEEK(sale_date) ASC";
}

$chart_res    = mysqli_query($conn, $chart_sql);
$chart_labels = [];
$chart_actual = [];

// Build full 7-day array with zeros for missing days
if ($period === 'week') {
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $date  = date('Y-m-d', strtotime("-{$i} days"));
        $label = date('D', strtotime("-{$i} days"));
        $days[$date] = ['label' => $label, 'total' => 0];
    }
    while ($row = mysqli_fetch_assoc($chart_res)) {
        if (isset($days[$row['sale_date']])) {
            $days[$row['sale_date']]['total'] = (float)$row['total'];
        }
    }
    foreach ($days as $d) {
        $chart_labels[] = $d['label'];
        $chart_actual[] = $d['total'];
    }
} else {
    while ($row = mysqli_fetch_assoc($chart_res)) {
        $chart_labels[] = $row['label'];
        $chart_actual[] = (float)$row['total'];
    }
}

// ---- TARGET LINE ----
// Average daily target from all products
$target_sql = "SELECT COALESCE(SUM(target_sales * price), 0) as monthly_target 
               FROM products 
               WHERE user_id = $user_id";
$target_res = mysqli_query($conn, $target_sql);
$target_row = mysqli_fetch_assoc($target_res);
$daily_target = $target_row['monthly_target'] > 0
    ? round($target_row['monthly_target'] / 30)
    : 30000;

$chart_target = array_fill(0, count($chart_labels), $daily_target);

// ---- TOP SELLING PRODUCTS ----
$top_sql = "SELECT 
                p.name,
                p.category,
                p.price,
                p.target_sales,
                COALESCE(SUM(s.quantity), 0) as units_sold,
                COALESCE(SUM(s.amount), 0)   as revenue
            FROM products p
            LEFT JOIN sales s ON p.id = s.product_id
                AND MONTH(s.sale_date) = MONTH(CURRENT_DATE())
                AND YEAR(s.sale_date)  = YEAR(CURRENT_DATE())
            WHERE p.user_id = $user_id
            GROUP BY p.id
            ORDER BY revenue DESC
            LIMIT 5";

$top_res      = mysqli_query($conn, $top_sql);
$top_products = [];
$max_revenue  = 0;

while ($row = mysqli_fetch_assoc($top_res)) {
    if ($row['revenue'] > $max_revenue) $max_revenue = $row['revenue'];
    $top_products[] = $row;
}

// Add percentage for progress bar
foreach ($top_products as &$p) {
    $p['percentage'] = $max_revenue > 0
        ? round(($p['revenue'] / $max_revenue) * 100)
        : 0;
    $p['vs_target'] = $p['target_sales'] > 0
        ? round(($p['units_sold'] / $p['target_sales']) * 100)
        : 0;
}

// ---- RECENT ACTIVITY ----
$activity_sql = "SELECT 
                    s.quantity,
                    s.amount,
                    s.sale_date,
                    p.name as product_name,
                    p.category
                 FROM sales s
                 JOIN products p ON s.product_id = p.id
                 WHERE s.user_id = $user_id
                 ORDER BY s.created_at DESC
                 LIMIT 8";

$activity_res  = mysqli_query($conn, $activity_sql);
$recent_sales  = [];
while ($row = mysqli_fetch_assoc($activity_res)) {
    $recent_sales[] = $row;
}

// ---- UNDERPERFORMING PRODUCTS (for alerts) ----
$under_sql = "SELECT 
                p.name,
                p.category,
                p.target_sales,
                COALESCE(SUM(s.quantity), 0) as units_sold,
                ROUND((COALESCE(SUM(s.quantity), 0) / p.target_sales) * 100) as achievement
              FROM products p
              LEFT JOIN sales s ON p.id = s.product_id
                AND MONTH(s.sale_date) = MONTH(CURRENT_DATE())
              WHERE p.user_id = $user_id
              GROUP BY p.id
              HAVING achievement < 70
              ORDER BY achievement ASC
              LIMIT 4";

$under_res      = mysqli_query($conn, $under_sql);
$underperforming = [];
while ($row = mysqli_fetch_assoc($under_res)) {
    $underperforming[] = $row;
}

// Return everything as JSON
echo json_encode([
    'status'          => 'success',
    'chart' => [
        'labels'  => $chart_labels,
        'actual'  => $chart_actual,
        'target'  => $chart_target,
    ],
    'top_products'    => $top_products,
    'recent_sales'    => $recent_sales,
    'underperforming' => $underperforming,
    'daily_target'    => $daily_target,
]);

mysqli_close($conn);
?>