<?php
require_once 'php/db_connect.php';

echo "<h2>AURORA — Seeding Database...</h2>";

// ---- INSERT DEMO USERS ----
$users = [
    ['Sreeharshith Reddy', 'vendor@aurora.com',  'vendor123',  'Aurora Supermart',    '9876543210', 'vendor'],
    ['Business Owner',     'owner@aurora.com',   'owner123',   'Aurora Retail Chain', '9876543211', 'owner'],
    ['Ramesh Kumar',       'ramesh@aurora.com',  'ramesh123',  'Ramesh Stores',       '9876543212', 'vendor'],
];

foreach ($users as $u) {
    $name  = $u[0]; $email = $u[1];
    $pass  = password_hash($u[2], PASSWORD_DEFAULT);
    $biz   = $u[3]; $phone = $u[4]; $role  = $u[5];
    $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");
    if (mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "INSERT INTO users (name,email,password,business_name,phone,role)
            VALUES ('$name','$email','$pass','$biz','$phone','$role')");
    }
}
echo "✅ Users seeded<br>";

// Get vendor user ID
$res     = mysqli_query($conn, "SELECT id FROM users WHERE email='vendor@aurora.com'");
$vendor  = mysqli_fetch_assoc($res);
$user_id = $vendor['id'];

// ---- INSERT PRODUCTS ----
$products = [
    ['Rice Bag 5kg',        'Grains',    250.00, 300],
    ['Sunflower Oil 1L',    'Oils',      130.00, 250],
    ['Wheat Flour 2kg',     'Grains',     80.00, 200],
    ['Full Cream Milk 1L',  'Dairy',      60.00, 350],
    ['Sugar 1kg',           'Grocery',    45.00, 400],
    ['Rice Oil 1L',         'Oils',      120.00, 200],
    ['Toor Dal 1kg',        'Pulses',    140.00, 180],
    ['Basmati Rice 1kg',    'Grains',    120.00, 150],
    ['Butter 100g',         'Dairy',      55.00, 160],
    ['Ghee 500ml',          'Dairy',     280.00, 120],
    ['Chana Dal 1kg',       'Pulses',    100.00, 140],
    ['Biscuits Pack',       'Snacks',     30.00, 300],
    ['Tomato Sauce 500g',   'Condiments', 75.00, 130],
    ['Salt 1kg',            'Grocery',    20.00, 500],
    ['Turmeric 100g',       'Spices',     35.00, 200],
    ['Red Chilli 100g',     'Spices',     40.00, 190],
    ['Mustard Oil 1L',      'Oils',      145.00, 160],
    ['Coconut Oil 500ml',   'Oils',      180.00, 120],
    ['Green Tea 25 bags',   'Beverages',  95.00, 110],
    ['Coffee 100g',         'Beverages', 180.00, 100],
];

$product_ids = [];
foreach ($products as $p) {
    $name = $p[0]; $cat = $p[1]; $price = $p[2]; $target = $p[3];
    $check = mysqli_query($conn, "SELECT id FROM products WHERE name='$name' AND user_id=$user_id");
    if (mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "INSERT INTO products (user_id,name,category,price,target_sales)
            VALUES ($user_id,'$name','$cat',$price,$target)");
        $product_ids[] = mysqli_insert_id($conn);
    } else {
        $row = mysqli_fetch_assoc($check);
        $product_ids[] = $row['id'];
    }
}
echo "✅ Products seeded<br>";

// ---- INSERT SALES (last 30 days) ----
$salesCount = 0;
foreach ($product_ids as $pid) {
    // Random sales for last 30 days
    for ($day = 30; $day >= 1; $day--) {
        if (rand(0, 2) === 0) continue; // skip some days randomly
        $qty    = rand(2, 25);
        $res2   = mysqli_query($conn, "SELECT price FROM products WHERE id=$pid");
        $prod   = mysqli_fetch_assoc($res2);
        $amount = $qty * $prod['price'];
        $date   = date('Y-m-d', strtotime("-{$day} days"));
        mysqli_query($conn, "INSERT INTO sales (product_id,user_id,quantity,amount,sale_date)
            VALUES ($pid,$user_id,$qty,$amount,'$date')");
        $salesCount++;
    }
}
echo "✅ Sales seeded ($salesCount records)<br>";

// ---- INSERT INVENTORY ----
$stocks = [150, 12, 8, 95, 200, 45, 60, 30, 15, 20, 80, 150, 40, 300, 90, 85, 55, 35, 25, 18];
foreach ($product_ids as $i => $pid) {
    $stock = $stocks[$i] ?? rand(10, 200);
    $min   = rand(5, 20);
    $check = mysqli_query($conn, "SELECT id FROM inventory WHERE product_id=$pid");
    if (mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "INSERT INTO inventory (product_id,user_id,stock_qty,min_stock)
            VALUES ($pid,$user_id,$stock,$min)");
    }
}
echo "✅ Inventory seeded<br>";

// ---- INSERT REVIEWS ----
$reviewData = [
    ['Arun K.',    5, 'Excellent quality rice! Will buy again.'],
    ['Priya S.',   4, 'Good product, delivery was quick.'],
    ['Ravi M.',    3, 'Average quality. Expected better.'],
    ['Sunita P.',  2, 'Product quality has declined recently. Very disappointed.'],
    ['Kiran L.',   5, 'Amazing value for money. Highly recommended!'],
    ['Deepa R.',   1, 'Very bad quality. Totally disappointed with this purchase.'],
    ['Suresh T.',  4, 'Decent product. Good packaging.'],
    ['Anita V.',   3, 'Quality not consistent. Sometimes good sometimes bad.'],
    ['Vijay N.',   5, 'Best product in this category. Always fresh.'],
    ['Meena C.',   2, 'Quality has gone down. Not happy with recent purchases.'],
];
foreach ($product_ids as $pid) {
    $numReviews = rand(2, 5);
    for ($r = 0; $r < $numReviews; $r++) {
        $rev     = $reviewData[array_rand($reviewData)];
        $name    = mysqli_real_escape_string($conn, $rev[0]);
        $rating  = $rev[1];
        $comment = mysqli_real_escape_string($conn, $rev[2]);
        mysqli_query($conn, "INSERT INTO reviews (product_id,user_id,reviewer,rating,comment)
            VALUES ($pid,$user_id,'$name',$rating,'$comment')");
    }
}
echo "✅ Reviews seeded<br>";

echo "<br><h3>✅ All done! Database is ready.</h3>";
echo "<p>You can now <a href='login.html'>Login</a> with:</p>";
echo "<ul>
    <li><strong>Vendor:</strong> vendor@aurora.com / vendor123</li>
    <li><strong>Owner:</strong> owner@aurora.com / owner123</li>
</ul>";

mysqli_close($conn);
?>
```

---

## 🗄️ Import the Database

1. Go to `http://localhost/phpmyadmin`
2. Click on `aurora_db` in the left sidebar
3. Click **Import** tab at the top
4. Choose `aurora_db.sql` from your AURORA folder
5. Click **Go** ✅

---

## 🌱 Run the Seed File

Open Safari and go to:
```
http://localhost/AURORA/seed_data.php