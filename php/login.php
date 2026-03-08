<?php
ini_set('session.cookie_samesite', 'Lax');
session_start();

header('Cache-Control: no-cache, no-store, must-revalidate');

require_once 'db_connect.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.html');
    exit();
}

// Get inputs
$email    = trim(mysqli_real_escape_string($conn, $_POST['email']    ?? ''));
$password =      $_POST['password'] ?? '';
$role     =      $_POST['role']     ?? 'vendor';

// Check empty
if (empty($email) || empty($password)) {
    header('Location: ../login.html?error=empty');
    exit();
}

// Find user by email and role
$sql    = "SELECT * FROM users WHERE email = '$email' AND role = '$role' LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) === 0) {
    header('Location: ../login.html?error=invalid');
    exit();
}

$user = mysqli_fetch_assoc($result);

// Verify password
if (!password_verify($password, $user['password'])) {
    header('Location: ../login.html?error=invalid');
    exit();
}

// Set session variables
$_SESSION['user_id']       = $user['id'];
$_SESSION['user_name']     = $user['name'];
$_SESSION['user_email']    = $user['email'];
$_SESSION['user_role']     = $user['role'];
$_SESSION['business_name'] = $user['business_name'];

// Redirect based on role
if ($user['role'] === 'owner') {
    header('Location: ../owner-portal.html');
} else {
    header('Location: ../dashboard.html');
}
exit();

mysqli_close($conn);
?>