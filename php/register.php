<?php
session_start();
require_once 'db_connect.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.html');
    exit();
}

// Get and sanitize inputs
$name          = trim(mysqli_real_escape_string($conn, $_POST['name']          ?? ''));
$email         = trim(mysqli_real_escape_string($conn, $_POST['email']         ?? ''));
$password      =      $_POST['password']       ?? '';
$business_name = trim(mysqli_real_escape_string($conn, $_POST['business_name'] ?? ''));
$phone         = trim(mysqli_real_escape_string($conn, $_POST['phone']         ?? ''));
$role          =      $_POST['role']           ?? 'vendor';

// Validate role
if (!in_array($role, ['vendor', 'owner'])) {
    $role = 'vendor';
}

// Check empty fields
if (empty($name) || empty($email) || empty($password) || empty($business_name)) {
    header('Location: ../register.html?error=empty');
    exit();
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../register.html?error=invalid_email');
    exit();
}

// Validate password length
if (strlen($password) < 6) {
    header('Location: ../register.html?error=short_password');
    exit();
}

// Check if email already exists
$check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
if (mysqli_num_rows($check) > 0) {
    header('Location: ../register.html?error=exists');
    exit();
}

// Hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$sql = "INSERT INTO users (name, email, password, business_name, phone, role)
        VALUES ('$name', '$email', '$hashed', '$business_name', '$phone', '$role')";

if (mysqli_query($conn, $sql)) {
    // Get the new user ID
    $user_id = mysqli_insert_id($conn);

    // Set session
    $_SESSION['user_id']       = $user_id;
    $_SESSION['user_name']     = $name;
    $_SESSION['user_email']    = $email;
    $_SESSION['user_role']     = $role;
    $_SESSION['business_name'] = $business_name;

    // Redirect based on role
    if ($role === 'owner') {
        header('Location: ../owner-portal.html');
    } else {
        header('Location: ../dashboard.html');
    }
    exit();

} else {
    header('Location: ../register.html?error=db_error');
    exit();
}

mysqli_close($conn);
?>