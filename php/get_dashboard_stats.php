<?php
ini_set('session.cookie_samesite', 'Lax');
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once 'db_connect.php';

// Session check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// ---- TOTAL REVENUE (this month) ----
$revenue_sql = "SELECT COALESCE(SUM(amount), 0) as total 
                FROM sales 
                WHERE user_id = $user_id 
                AND MONTH(sale_date) = MONTH(CURRENT_DATE())
                AND YEAR(sale_date) = YEAR(CURRENT_DATE())";
$revenue_res  = mysqli_query($conn, $revenue_sql);
$revenue_row  = mysqli_fetch_assoc($revenue_res);
$total_revenue = $revenue_row['total'];

// ---- TOTAL ORDERS (this month) ----
$orders_sql = "SELECT COALESCE(SUM(quantity), 0) as total 
               FROM sales 
               WHERE user_id = $user_id
               AND MONTH(sale_date) = MONTH(CURRENT_DATE())
               AND YEAR(sale_date) = YEAR(CURRENT_DATE())";
$orders_res  = mysqli_query($conn, $orders_sql);
$orders_row  = mysqli_fetch_assoc($orders_res);
$total_orders = $orders_row['total'];

// ---- LAST MONTH REVENUE (for % change) ----
$last_revenue_sql = "SELECT COALESCE(SUM(amount), 0) as total 
                     FROM sales 
                     WHERE user_id = $user_id
                     AND MONTH(sale_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
                     AND YEAR(sale_date)  = YEAR(CURRENT_DATE()  - INTERVAL 1 MONTH)";
$last_rev_res  = mysqli_query($conn, $last_revenue_sql);
$last_rev_row  = mysqli_fetch_assoc($last_rev_res);
$last_revenue  = $last_rev_row['total'];

// Revenue % change
if ($last_revenue > 0) {
    $revenue_change = round((($total_revenue - $last_revenue) / $last_revenue) * 100, 1);
} else {
    $revenue_change = 0;
}

// ---- AVERAGE SENTIMENT ----
$sentiment_sql = "SELECT COALESCE(AVG(rating), 0) as avg_rating 
                  FROM reviews 
                  WHERE user_id = $user_id";
$sentiment_res  = mysqli_query($conn, $sentiment_sql);
$sentiment_row  = mysqli_fetch_assoc($sentiment_res);
$avg_sentiment  = round(($sentiment_row['avg_rating'] / 5) * 100);

// ---- TOTAL STOCK ----
$stock_sql = "SELECT COALESCE(SUM(stock_qty), 0) as total 
              FROM inventory 
              WHERE user_id = $user_id";
$stock_res  = mysqli_query($conn, $stock_sql);
$stock_row  = mysqli_fetch_assoc($stock_res);
$total_stock = $stock_row['total'];

// ---- LOW STOCK COUNT ----
$low_stock_sql = "SELECT COUNT(*) as count 
                  FROM inventory 
                  WHERE user_id = $user_id 
                  AND stock_qty <= min_stock";
$low_stock_res  = mysqli_query($conn, $low_stock_sql);
$low_stock_row  = mysqli_fetch_assoc($low_stock_res);
$low_stock_count = $low_stock_row['count'];

// ---- PENDING STRATEGY ALERTS ----
$alerts_sql = "SELECT COUNT(DISTINCT p.id) as count
               FROM products p
               LEFT JOIN (
                   SELECT product_id, SUM(quantity) as sold
                   FROM sales
                   WHERE user_id = $user_id
                   AND MONTH(sale_date) = MONTH(CURRENT_DATE())
                   GROUP BY product_id
               ) s ON p.id = s.product_id
               WHERE p.user_id = $user_id
               AND (COALESCE(s.sold, 0) < p.target_sales * 0.7)";
$alerts_res  = mysqli_query($conn, $alerts_sql);
$alerts_row  = mysqli_fetch_assoc($alerts_res);
$alert_count = $alerts_row['count'];

// Return JSON
echo json_encode([
    'status'         => 'success',
    'revenue'        => $total_revenue,
    'revenue_change' => $revenue_change,
    'orders'         => $total_orders,
    'sentiment'      => $avg_sentiment,
    'stock'          => $total_stock,
    'low_stock'      => $low_stock_count,
    'alerts'         => $alert_count,
    'user_name'      => $_SESSION['user_name'],
    'business_name'  => $_SESSION['business_name']
]);

mysqli_close($conn);
?>