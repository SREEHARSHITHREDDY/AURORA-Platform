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

// Keyword lists for sentiment analysis (PHP strpos method)
$positive_keywords = [
    'excellent', 'amazing', 'great', 'good', 'love', 'perfect', 'fantastic',
    'wonderful', 'best', 'awesome', 'happy', 'satisfied', 'recommend',
    'quality', 'fresh', 'delicious', 'helpful', 'fast', 'quick', 'nice',
    'superb', 'outstanding', 'brilliant', 'impressive', 'reliable', 'tasty',
    'value', 'worth', 'smooth', 'easy', 'convenient', 'pleased', 'enjoy'
];

$negative_keywords = [
    'bad', 'poor', 'terrible', 'worst', 'horrible', 'awful', 'disappointed',
    'disappointing', 'waste', 'broken', 'damaged', 'late', 'slow', 'rude',
    'overpriced', 'expensive', 'stale', 'expired', 'wrong', 'missing',
    'never', 'not good', 'not fresh', 'smell', 'dirty', 'unacceptable',
    'refund', 'return', 'complaint', 'issue', 'problem', 'fail', 'defective'
];

// Function to analyse sentiment using PHP strpos
function analyseSentiment($text, $positive_keywords, $negative_keywords) {
    $text_lower = strtolower($text);
    $pos_count = 0;
    $neg_count = 0;

    foreach ($positive_keywords as $kw) {
        if (strpos($text_lower, $kw) !== false) $pos_count++;
    }
    foreach ($negative_keywords as $kw) {
        if (strpos($text_lower, $kw) !== false) $neg_count++;
    }

    if ($pos_count > $neg_count)      $sentiment = 'positive';
    elseif ($neg_count > $pos_count)  $sentiment = 'negative';
    else                               $sentiment = 'neutral';

    // Score: 0-100
    $total = $pos_count + $neg_count;
    if ($total === 0) {
        $score = 50; // neutral baseline
    } else {
        $score = intval(($pos_count / $total) * 100);
    }

    return ['sentiment' => $sentiment, 'score' => $score];
}

// Fetch all reviews for this vendor's products
$sql = "
    SELECT 
        r.id,
        r.review_text,
        r.review_date,
        r.customer_name,
        p.name AS product_name,
        p.category
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    WHERE p.vendor_id = $user_id
    ORDER BY r.review_date DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Query failed: ' . mysqli_error($conn)]);
    exit();
}

$reviews    = [];
$by_product = [];
$total      = 0;
$positive   = 0;
$negative   = 0;
$neutral    = 0;
$score_sum  = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $analysis = analyseSentiment($row['review_text'], $positive_keywords, $negative_keywords);
    $row['sentiment'] = $analysis['sentiment'];
    $row['score']     = $analysis['score'];
    $reviews[] = $row;
    $total++;
    $score_sum += $analysis['score'];

    if ($analysis['sentiment'] === 'positive')     $positive++;
    elseif ($analysis['sentiment'] === 'negative') $negative++;
    else                                            $neutral++;

    // Aggregate by product
    $pname = $row['product_name'];
    if (!isset($by_product[$pname])) {
        $by_product[$pname] = ['product_name' => $pname, 'category' => $row['category'], 'scores' => [], 'count' => 0];
    }
    $by_product[$pname]['scores'][] = $analysis['score'];
    $by_product[$pname]['count']++;
}

// Calculate avg score per product
$by_product_arr = [];
foreach ($by_product as $pname => $data) {
    $avg = count($data['scores']) > 0 ? intval(array_sum($data['scores']) / count($data['scores'])) : 50;
    $by_product_arr[] = [
        'product_name' => $data['product_name'],
        'category'     => $data['category'],
        'score'        => $avg,
        'review_count' => $data['count']
    ];
}
// Sort by score descending
usort($by_product_arr, fn($a, $b) => $b['score'] - $a['score']);

$avg_score = $total > 0 ? intval($score_sum / $total) : 0;

echo json_encode([
    'status'     => 'success',
    'user_name'  => $_SESSION['user_name'] ?? '',
    'reviews'    => $reviews,
    'by_product' => $by_product_arr,
    'stats'      => [
        'total'     => $total,
        'positive'  => $positive,
        'negative'  => $negative,
        'neutral'   => $neutral,
        'avg_score' => $avg_score
    ]
]);

mysqli_close($conn);
?>