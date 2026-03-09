<?php
ini_set('session.cookie_samesite', 'Lax');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'logged_in' => false]);
    exit();
}

echo json_encode([
    'status'    => 'success',
    'logged_in' => true,
    'user_id'   => $_SESSION['user_id'],
    'user_name' => $_SESSION['user_name']     ?? '',
    'role'      => $_SESSION['user_role']     ?? 'vendor',
    'business'  => $_SESSION['business_name'] ?? ''
]);
?>