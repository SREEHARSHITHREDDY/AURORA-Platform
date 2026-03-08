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

// ============================================================
// FETCH LAST 90 DAYS OF DAILY REVENUE
// ============================================================
$sql = "
    SELECT 
        s.sale_date,
        SUM(s.amount) AS daily_revenue
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    GROUP BY s.sale_date
    ORDER BY s.sale_date ASC
";

$result = mysqli_query($conn, $sql);
if (!$result) {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    exit();
}

$raw = [];
while ($row = mysqli_fetch_assoc($result)) {
    $raw[$row['sale_date']] = floatval($row['daily_revenue']);
}

// Fill missing days with 0
$filled = [];
$start = date('Y-m-d', strtotime('-89 days'));
for ($i = 0; $i < 90; $i++) {
    $d = date('Y-m-d', strtotime($start . " +$i days"));
    $filled[$d] = $raw[$d] ?? 0;
}

$dates    = array_keys($filled);
$revenues = array_values($filled);

// ============================================================
// MOVING AVERAGE — window of 7 days
// ============================================================
function movingAverage($data, $window = 7) {
    $result = [];
    $n = count($data);
    for ($i = 0; $i < $n; $i++) {
        if ($i < $window - 1) {
            $result[] = null;
        } else {
            $slice = array_slice($data, $i - $window + 1, $window);
            $result[] = round(array_sum($slice) / $window, 2);
        }
    }
    return $result;
}

$ma7 = movingAverage($revenues, 7);

// ============================================================
// FORECAST NEXT 14 DAYS using last 14-day average
// ============================================================
$last14 = array_slice($revenues, -14);
$last14_nonzero = array_filter($last14, fn($v) => $v > 0);
$daily_avg = count($last14_nonzero) > 0
    ? array_sum($last14_nonzero) / count($last14_nonzero)
    : 0;

$forecast_labels   = [];
$forecast_values   = [];
$forecast_optimistic = [];
$forecast_pessimistic = [];

for ($i = 1; $i <= 14; $i++) {
    $d = date('Y-m-d', strtotime("+$i days"));
    $forecast_labels[]      = date('d M', strtotime($d));
    $forecast_values[]      = round($daily_avg, 2);
    $forecast_optimistic[]  = round($daily_avg * 1.15, 2);
    $forecast_pessimistic[] = round($daily_avg * 0.85, 2);
}

// ============================================================
// TOP 5 PRODUCTS BY REVENUE THIS MONTH
// ============================================================
$sql_top = "
    SELECT p.name, p.category, SUM(s.amount) AS revenue, SUM(s.quantity) AS units
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
    GROUP BY p.id, p.name, p.category
    ORDER BY revenue DESC
    LIMIT 5
";
$top_products = [];
$res_top = mysqli_query($conn, $sql_top);
while ($row = mysqli_fetch_assoc($res_top)) {
    $top_products[] = $row;
}

// ============================================================
// MONTHLY SUMMARY (last 6 months)
// ============================================================
$sql_monthly = "
    SELECT 
        DATE_FORMAT(s.sale_date, '%b %Y') AS month_label,
        DATE_FORMAT(s.sale_date, '%Y-%m') AS month_sort,
        SUM(s.amount)   AS revenue,
        SUM(s.quantity) AS units
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month_sort, month_label
    ORDER BY month_sort ASC
";
$monthly = [];
$res_monthly = mysqli_query($conn, $sql_monthly);
while ($row = mysqli_fetch_assoc($res_monthly)) {
    $monthly[] = $row;
}

// Stats
$total_revenue_90d = array_sum($revenues);
$avg_daily         = count(array_filter($revenues, fn($v) => $v > 0)) > 0
    ? $total_revenue_90d / count(array_filter($revenues, fn($v) => $v > 0))
    : 0;
$forecast_14d      = $daily_avg * 14;
$best_day_val      = max($revenues) ?: 0;
$best_day_idx      = array_search($best_day_val, $revenues);
$best_day_label    = $best_day_idx !== false ? date('d M', strtotime($dates[$best_day_idx])) : 'N/A';

// Format history for chart (last 30 days only for clarity)
$history_labels  = array_map(fn($d) => date('d M', strtotime($d)), array_slice($dates, -30));
$history_revenue = array_slice($revenues, -30);
$history_ma7     = array_slice($ma7, -30);

echo json_encode([
    'status'       => 'success',
    'user_name'    => $_SESSION['user_name'] ?? '',
    'stats' => [
        'total_90d'    => round($total_revenue_90d, 2),
        'avg_daily'    => round($avg_daily, 2),
        'forecast_14d' => round($forecast_14d, 2),
        'best_day'     => $best_day_label,
        'best_day_val' => $best_day_val
    ],
    'history' => [
        'labels'  => $history_labels,
        'revenue' => $history_revenue,
        'ma7'     => $history_ma7
    ],
    'forecast' => [
        'labels'      => $forecast_labels,
        'values'      => $forecast_values,
        'optimistic'  => $forecast_optimistic,
        'pessimistic' => $forecast_pessimistic
    ],
    'monthly'      => $monthly,
    'top_products' => $top_products
]);

mysqli_close($conn);
?>