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

// ── Total revenue this month ──────────────────────────────────
$row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(s.amount),0) AS revenue,
           COUNT(s.id) AS orders,
           COALESCE(SUM(s.quantity),0) AS units
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
"));

// ── Total stock ───────────────────────────────────────────────
$stock_row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(i.stock_qty),0) AS total_stock,
           SUM(CASE WHEN i.stock_qty=0 THEN 1 ELSE 0 END) AS out_of_stock,
           SUM(CASE WHEN i.stock_qty>0 AND i.stock_qty<=i.min_stock THEN 1 ELSE 0 END) AS low_stock
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    WHERE p.user_id = $user_id
"));

// ── Sentiment ─────────────────────────────────────────────────
$positive_kw = ['excellent','amazing','great','good','love','perfect','fantastic','wonderful','best','awesome','happy','satisfied','recommend','quality','fresh','delicious','nice','superb'];
$negative_kw = ['bad','poor','terrible','worst','horrible','awful','disappointed','waste','broken','damaged','late','slow','overpriced','stale','expired','wrong','dirty','fail'];
$pos = 0; $neg = 0; $neu = 0;
$res_r = mysqli_query($conn, "SELECT r.comment FROM reviews r JOIN products p ON r.product_id=p.id WHERE p.user_id=$user_id");
while ($r = mysqli_fetch_assoc($res_r)) {
    $t = strtolower($r['comment']); $pc = 0; $nc = 0;
    foreach ($positive_kw as $kw) if (strpos($t,$kw)!==false) $pc++;
    foreach ($negative_kw as $kw) if (strpos($t,$kw)!==false) $nc++;
    if ($pc > $nc) $pos++; elseif ($nc > $pc) $neg++; else $neu++;
}
$total_reviews   = $pos + $neg + $neu;
$sentiment_score = $total_reviews > 0 ? round(($pos / $total_reviews) * 100) : 0;

// ── Best sellers (top 5 this month) ──────────────────────────
$best_sellers = [];
$res_bs = mysqli_query($conn, "
    SELECT p.name, SUM(s.amount) AS revenue, SUM(s.quantity) AS units_sold
    FROM sales s JOIN products p ON s.product_id=p.id
    WHERE p.user_id=$user_id
      AND MONTH(s.sale_date)=MONTH(CURDATE())
      AND YEAR(s.sale_date)=YEAR(CURDATE())
    GROUP BY p.id, p.name
    ORDER BY revenue DESC LIMIT 5
");
while ($r = mysqli_fetch_assoc($res_bs)) $best_sellers[] = $r;

// ── Target vs Actual (top 5 products) ────────────────────────
$target_vs_actual = [];
$res_tv = mysqli_query($conn, "
    SELECT p.name, p.target_sales,
           COALESCE(SUM(s.quantity),0) AS actual_sales,
           ROUND(COALESCE(SUM(s.quantity),0) / p.target_sales * 100) AS achievement
    FROM products p
    LEFT JOIN sales s ON s.product_id=p.id
        AND MONTH(s.sale_date)=MONTH(CURDATE())
        AND YEAR(s.sale_date)=YEAR(CURDATE())
    WHERE p.user_id=$user_id AND p.target_sales > 0
    GROUP BY p.id, p.name, p.target_sales
    ORDER BY achievement DESC LIMIT 5
");
while ($r = mysqli_fetch_assoc($res_tv)) $target_vs_actual[] = $r;

// ── Strategy alerts count ─────────────────────────────────────
$alert_count = intval(mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM (
        SELECT p.id FROM products p
        JOIN inventory i ON i.product_id=p.id AND i.user_id=p.user_id
        WHERE p.user_id=$user_id AND (
            i.stock_qty = 0 OR
            (i.stock_qty > 0 AND i.stock_qty <= i.min_stock)
        )
    ) alerts
"))['c']);

// ── Daily revenue last 7 days ────────────────────────────────
$daily_revenue = [];
$res_d = mysqli_query($conn, "
    SELECT DATE_FORMAT(s.sale_date,'%d %b') AS date_label,
           SUM(s.amount) AS revenue
    FROM sales s JOIN products p ON s.product_id=p.id
    WHERE p.user_id=$user_id
      AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY s.sale_date
    ORDER BY s.sale_date ASC
");
while ($r = mysqli_fetch_assoc($res_d)) {
    $daily_revenue[] = ['date' => $r['date_label'], 'revenue' => floatval($r['revenue'])];
}

// ── Category revenue this month ───────────────────────────────
$category_revenue = [];
$res_c = mysqli_query($conn, "
    SELECT p.category, SUM(s.amount) AS revenue
    FROM sales s JOIN products p ON s.product_id=p.id
    WHERE p.user_id=$user_id
      AND MONTH(s.sale_date)=MONTH(CURDATE())
      AND YEAR(s.sale_date)=YEAR(CURDATE())
    GROUP BY p.category ORDER BY revenue DESC
");
while ($r = mysqli_fetch_assoc($res_c)) {
    $category_revenue[] = ['category' => $r['category'], 'revenue' => floatval($r['revenue'])];
}

// ── Strategy alerts detail ────────────────────────────────────
$strategies = [];
$res_st = mysqli_query($conn, "
    SELECT p.name, i.stock_qty, i.min_stock,
           COALESCE(SUM(s.quantity),0) AS units_sold,
           p.target_sales
    FROM products p
    JOIN inventory i ON i.product_id=p.id AND i.user_id=p.user_id
    LEFT JOIN sales s ON s.product_id=p.id
        AND MONTH(s.sale_date)=MONTH(CURDATE())
    WHERE p.user_id=$user_id
    GROUP BY p.id, p.name, i.stock_qty, i.min_stock, p.target_sales
    LIMIT 20
");
while ($r = mysqli_fetch_assoc($res_st)) {
    if ($r['stock_qty'] == 0) {
        $strategies[] = ['priority'=>'critical','title'=>"Out of Stock: {$r['name']}",
            'recommendation'=>'Restock immediately to avoid losing sales.'];
    } elseif ($r['stock_qty'] <= $r['min_stock']) {
        $strategies[] = ['priority'=>'critical','title'=>"Low Stock: {$r['name']}",
            'recommendation'=>"Only {$r['stock_qty']} units left. Reorder now."];
    }
}

echo json_encode([
    'status'          => 'success',
    'user_name'       => $_SESSION['user_name']     ?? '',
    'business_name'   => $_SESSION['business_name'] ?? '',
    'total_revenue'   => floatval($row['revenue']),
    'total_orders'    => intval($row['orders']),
    'total_units'     => intval($row['units']),
    'sentiment_score' => $sentiment_score,
    'total_stock'     => intval($stock_row['total_stock']),
    'out_of_stock'    => intval($stock_row['out_of_stock']),
    'low_stock'       => intval($stock_row['low_stock']),
    'strategy_alerts' => $alert_count,
    'best_sellers'    => $best_sellers,
    'target_vs_actual'=> $target_vs_actual,
    'daily_revenue'   => $daily_revenue,
    'category_revenue'=> $category_revenue,
    'strategies'      => $strategies
]);

mysqli_close($conn);
?>