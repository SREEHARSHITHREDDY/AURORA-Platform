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
// FETCH ALL DATA NEEDED FOR STRATEGY ENGINE
// ============================================================

// 1. Sales performance this month
$sql_perf = "
    SELECT
        p.id,
        p.name,
        p.category,
        p.price,
        p.target_sales,
        COALESCE(SUM(s.quantity), 0)     AS units_sold,
        COALESCE(SUM(s.amount), 0)       AS revenue,
        DATEDIFF(CURDATE(), MAX(s.sale_date)) AS days_since_sale
    FROM products p
    LEFT JOIN sales s ON s.product_id = p.id
        AND MONTH(s.sale_date) = MONTH(CURDATE())
        AND YEAR(s.sale_date)  = YEAR(CURDATE())
    WHERE p.user_id = $user_id
    GROUP BY p.id, p.name, p.category, p.price, p.target_sales
";
$res_perf = mysqli_query($conn, $sql_perf);
if (!$res_perf) {
    echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    exit();
}

// 2. Inventory levels
$inventory = [];
$res_inv = mysqli_query($conn, "SELECT product_id, stock_qty, min_stock FROM inventory WHERE user_id = $user_id");
while ($row = mysqli_fetch_assoc($res_inv)) {
    $inventory[$row['product_id']] = $row;
}

// 3. Reviews for sentiment (keyword analysis using PHP strpos)
$positive_kw = ['excellent','amazing','great','good','love','perfect','fantastic','wonderful','best','awesome','happy','satisfied','recommend','quality','fresh','delicious','helpful','fast','nice','superb','tasty','value','worth','smooth','reliable'];
$negative_kw = ['bad','poor','terrible','worst','horrible','awful','disappointed','waste','broken','damaged','late','slow','overpriced','stale','expired','wrong','missing','smell','dirty','unacceptable','refund','complaint','issue','problem','fail'];

$sentiment_map = [];
$res_rev = mysqli_query($conn, "SELECT r.product_id, r.comment FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.user_id = $user_id");
while ($row = mysqli_fetch_assoc($res_rev)) {
    $pid  = $row['product_id'];
    $text = strtolower($row['comment']);
    $pos  = 0; $neg = 0;
    foreach ($positive_kw as $kw) { if (strpos($text, $kw) !== false) $pos++; }
    foreach ($negative_kw as $kw) { if (strpos($text, $kw) !== false) $neg++; }
    if (!isset($sentiment_map[$pid])) $sentiment_map[$pid] = ['pos'=>0,'neg'=>0,'count'=>0];
    $sentiment_map[$pid]['pos']   += $pos;
    $sentiment_map[$pid]['neg']   += $neg;
    $sentiment_map[$pid]['count'] += 1;
}

// ============================================================
// RULE-BASED STRATEGY ENGINE
// ============================================================
$strategies = [];

while ($p = mysqli_fetch_assoc($res_perf)) {
    $pid          = $p['id'];
    $units_sold   = intval($p['units_sold']);
    $target       = intval($p['target_sales']) ?: 100;
    $achievement  = $target > 0 ? round(($units_sold / $target) * 100) : 0;
    $stock        = isset($inventory[$pid]) ? intval($inventory[$pid]['stock_qty'])  : 0;
    $min_stock    = isset($inventory[$pid]) ? intval($inventory[$pid]['min_stock'])  : 10;
    $days_no_sale = ($p['days_since_sale'] === null) ? 999 : intval($p['days_since_sale']);

    // Sentiment score 0-100
    $sent_score = 50;
    if (isset($sentiment_map[$pid]) && $sentiment_map[$pid]['count'] > 0) {
        $sv    = $sentiment_map[$pid];
        $total = $sv['pos'] + $sv['neg'];
        $sent_score = $total > 0 ? intval(($sv['pos'] / $total) * 100) : 50;
    }

    $metrics = [
        'sold'         => $units_sold,
        'target'       => $target,
        'achievement'  => $achievement,
        'stock'        => $stock,
        'days_no_sale' => $days_no_sale === 999 ? 0 : $days_no_sale,
        'sentiment'    => $sent_score
    ];

    // RULE 1: Out of stock
    if ($stock == 0) {
        $strategies[] = ['type'=>'restock','priority'=>'critical','product_id'=>$pid,'product_name'=>$p['name'],'category'=>$p['category'],
            'title'=>'Restock Immediately — Out of Stock',
            'description'=>"{$p['name']} is completely out of stock. You are losing sales every day this product is unavailable. Order new stock immediately.",
            'metrics'=>$metrics];
        continue;
    }

    // RULE 2: Low stock on selling product
    if ($stock <= $min_stock && $achievement >= 50) {
        $strategies[] = ['type'=>'restock','priority'=>'critical','product_id'=>$pid,'product_name'=>$p['name'],'category'=>$p['category'],
            'title'=>'Low Stock Alert — Reorder Before Stockout',
            'description'=>"Only {$stock} units of {$p['name']} remain (threshold: {$min_stock}). This product is at {$achievement}% of its sales target. Reorder now to avoid losing momentum.",
            'metrics'=>$metrics];
        continue;
    }

    // RULE 3: Dead stock (14+ days no sale, lots of stock)
    if ($days_no_sale > 14 && $stock > ($min_stock * 2)) {
        $strategies[] = ['type'=>'clearance','priority'=>'critical','product_id'=>$pid,'product_name'=>$p['name'],'category'=>$p['category'],
            'title'=>'Dead Stock — Run a Clearance Discount',
            'description'=>"{$p['name']} has had no sales in {$days_no_sale} days with {$stock} units sitting idle. Apply a 20-30% clearance discount to free shelf space and recover tied-up capital.",
            'metrics'=>$metrics];
        continue;
    }

    // RULE 4: Far below target (< 40%)
    if ($achievement < 40 && $target >= 30) {
        $strategies[] = ['type'=>'discount','priority'=>'critical','product_id'=>$pid,'product_name'=>$p['name'],'category'=>$p['category'],
            'title'=>'Urgent: ' . $achievement . '% of Target — Apply Discount Now',
            'description'=>"{$p['name']} has only sold {$units_sold} of {$target} units this month ({$achievement}%). Offer a 15-20% limited-time discount or bundle with a popular product to urgently close the gap before month end.",
            'metrics'=>$metrics];
        continue;
    }

    // RULE 5: Below target + poor sentiment
    if ($achievement < 70 && $sent_score < 45) {
        $strategies[] = ['type'=>'quality','priority'=>'medium','product_id'=>$pid,'product_name'=>$p['name'],'category'=>$p['category'],
            'title'=>'Quality Issue — Review Customer Complaints',
            'description'=>"{$p['name']} has a poor sentiment score ({$sent_score}%) and is below sales target ({$achievement}%). Read negative customer comments, check product freshness/quality, and address issues before more customers are turned away.",
            'metrics'=>$metrics];
        continue;
    }

    // RULE 6: Below target (40-70%), decent sentiment — promote it
    if ($achievement >= 40 && $achievement < 70) {
        $strategies[] = ['type'=>'promote','priority'=>'medium','product_id'=>$pid,'product_name'=>$p['name'],'category'=>$p['category'],
            'title'=>'Boost Visibility — ' . $achievement . '% of Monthly Target',
            'description'=>"{$p['name']} is underperforming at {$achievement}% of target despite good reviews. Move it to a prominent shelf position or create a combo deal with a bestseller to increase exposure and drive sales.",
            'metrics'=>$metrics];
        continue;
    }

    // RULE 7: Selling well but poor sentiment — future risk
    if ($achievement >= 70 && $sent_score < 40) {
        $strategies[] = ['type'=>'quality','priority'=>'medium','product_id'=>$pid,'product_name'=>$p['name'],'category'=>$p['category'],
            'title'=>'Negative Reviews on Selling Product — Act Now',
            'description'=>"{$p['name']} is selling well ({$achievement}% of target) but customer sentiment is low ({$sent_score}%). Unchecked negative reviews will erode future sales. Investigate and fix quality issues proactively.",
            'metrics'=>$metrics];
        continue;
    }

    // RULE 8: Star product — bundle opportunity
    if ($achievement >= 120 && $stock > ($min_stock * 3)) {
        $strategies[] = ['type'=>'bundle','priority'=>'low','product_id'=>$pid,'product_name'=>$p['name'],'category'=>$p['category'],
            'title'=>'Star Product — Create a Bundle to Increase Basket Size',
            'description'=>"{$p['name']} is a top performer at {$achievement}% of target with strong stock. Bundle it with a slow-moving product from the same category to boost overall basket value and clear sluggish inventory.",
            'metrics'=>$metrics];
    }
}

// Sort by priority: critical → medium → low
usort($strategies, function($a, $b) {
    $o = ['critical'=>0,'medium'=>1,'low'=>2];
    return ($o[$a['priority']]??3) - ($o[$b['priority']]??3);
});

$critical = count(array_filter($strategies, fn($s) => $s['priority']==='critical'));
$medium   = count(array_filter($strategies, fn($s) => $s['priority']==='medium'));
$low      = count(array_filter($strategies, fn($s) => $s['priority']==='low'));

echo json_encode([
    'status'     => 'success',
    'user_name'  => $_SESSION['user_name'] ?? '',
    'strategies' => $strategies,
    'summary'    => ['total'=>count($strategies),'critical'=>$critical,'medium'=>$medium,'low'=>$low]
]);

mysqli_close($conn);
?>