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

// ── This month revenue & orders ──────────────────────────────
$sql_rev = "
    SELECT 
        COALESCE(SUM(s.amount), 0)   AS revenue,
        COALESCE(SUM(s.quantity), 0) AS units_sold,
        COUNT(s.id)                  AS orders
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
";
$rev = mysqli_fetch_assoc(mysqli_query($conn, $sql_rev));

// ── Last month revenue (for comparison) ─────────────────────
$sql_last = "
    SELECT COALESCE(SUM(s.amount), 0) AS revenue
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND YEAR(s.sale_date)  = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
";
$last = mysqli_fetch_assoc(mysqli_query($conn, $sql_last));
$last_rev = floatval($last['revenue']);
$this_rev = floatval($rev['revenue']);
$change   = $last_rev > 0 ? round((($this_rev - $last_rev) / $last_rev) * 100, 1) : 0;

// ── Top products this month ───────────────────────────────────
$sql_top = "
    SELECT p.name, p.category, p.target_sales,
           SUM(s.quantity) AS units_sold,
           SUM(s.amount)   AS revenue
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
    GROUP BY p.id, p.name, p.category, p.target_sales
    ORDER BY revenue DESC
    LIMIT 10
";
$top_products = [];
$res = mysqli_query($conn, $sql_top);
while ($row = mysqli_fetch_assoc($res)) {
    $row['achievement'] = $row['target_sales'] > 0
        ? round(($row['units_sold'] / $row['target_sales']) * 100)
        : 0;
    $top_products[] = $row;
}

// ── Category breakdown ────────────────────────────────────────
$sql_cat = "
    SELECT p.category, SUM(s.amount) AS revenue, SUM(s.quantity) AS units
    FROM sales s
    JOIN products p ON s.product_id = p.id
    WHERE p.user_id = $user_id
      AND MONTH(s.sale_date) = MONTH(CURDATE())
      AND YEAR(s.sale_date)  = YEAR(CURDATE())
    GROUP BY p.category
    ORDER BY revenue DESC
";
$categories = [];
$res_cat = mysqli_query($conn, $sql_cat);
while ($row = mysqli_fetch_assoc($res_cat)) $categories[] = $row;

// ── Inventory summary ─────────────────────────────────────────
$inv = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        SUM(i.stock_qty) AS total_stock,
        SUM(CASE WHEN i.stock_qty=0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN i.stock_qty>0 AND i.stock_qty<=i.min_stock THEN 1 ELSE 0 END) AS low_stock
    FROM inventory i
    JOIN products p ON i.product_id=p.id
    WHERE p.user_id=$user_id
"));

// ── Sentiment summary ─────────────────────────────────────────
$positive_kw = ['excellent','amazing','great','good','love','perfect','fantastic','wonderful','best','awesome','happy','satisfied','recommend','quality','fresh','delicious','nice','superb'];
$negative_kw = ['bad','poor','terrible','worst','horrible','awful','disappointed','waste','broken','damaged','late','slow','overpriced','stale','expired','wrong','smell','dirty','fail'];

$pos=0; $neg=0; $neu=0;
$res_rev2 = mysqli_query($conn,"SELECT r.comment FROM reviews r JOIN products p ON r.product_id=p.id WHERE p.user_id=$user_id");
while ($row = mysqli_fetch_assoc($res_rev2)) {
    $text=strtolower($row['comment']); $pc=0; $nc=0;
    foreach($positive_kw as $kw) if(strpos($text,$kw)!==false) $pc++;
    foreach($negative_kw as $kw) if(strpos($text,$kw)!==false) $nc++;
    if($pc>$nc) $pos++; elseif($nc>$pc) $neg++; else $neu++;
}
$total_reviews = $pos+$neg+$neu;
$sentiment_score = $total_reviews > 0 ? round(($pos/$total_reviews)*100) : 0;

// ── Monthly history (6 months) ────────────────────────────────
$sql_hist = "
    SELECT DATE_FORMAT(s.sale_date,'%b %Y') AS label,
           DATE_FORMAT(s.sale_date,'%Y-%m') AS sort_key,
           SUM(s.amount) AS revenue, SUM(s.quantity) AS units
    FROM sales s JOIN products p ON s.product_id=p.id
    WHERE p.user_id=$user_id
      AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY sort_key, label ORDER BY sort_key ASC
";
$monthly = [];
$res_hist = mysqli_query($conn, $sql_hist);
while ($row = mysqli_fetch_assoc($res_hist)) $monthly[] = $row;

echo json_encode([
    'status'          => 'success',
    'user_name'       => $_SESSION['user_name']     ?? '',
    'business_name'   => $_SESSION['business_name'] ?? 'Aurora Supermart',
    'report_date'     => date('d F Y'),
    'report_month'    => date('F Y'),
    'revenue'         => $this_rev,
    'units_sold'      => intval($rev['units_sold']),
    'orders'          => intval($rev['orders']),
    'revenue_change'  => $change,
    'top_products'    => $top_products,
    'categories'      => $categories,
    'inventory'       => $inv,
    'sentiment'       => ['score'=>$sentiment_score,'positive'=>$pos,'negative'=>$neg,'neutral'=>$neu,'total'=>$total_reviews],
    'monthly'         => $monthly
]);
mysqli_close($conn);
?>