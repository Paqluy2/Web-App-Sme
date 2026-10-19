<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

$action = $_GET['action'] ?? '';
$db = getDB();

// 1. Customer Login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $stmt = $db->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $cust = $stmt->fetch();

    if ($cust && password_verify($password, $cust['password'])) {
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)$cust['customer_id'];
        $_SESSION['customer_name'] = $cust['full_name'];
        $_SESSION['customer_email'] = $cust['email'];
        header('Location: menu.php');
        exit;
    } else {
        echo "<script>alert('Invalid email or password.'); window.location.href='menu.php';</script>";
        exit;
    }
}

// 2. Customer Registration (No address required upfront)
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $check = $db->prepare("SELECT customer_id FROM customers WHERE email = ? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo "<script>alert('An account with this email already exists.'); window.location.href='menu.php';</script>";
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert without address fields
    $insert = $db->prepare("INSERT INTO customers (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
    $insert->execute([$fullName, $email, $phone, $hash]);

    $_SESSION['customer_id'] = (int)$db->lastInsertId();
    $_SESSION['customer_name'] = $fullName;
    $_SESSION['customer_email'] = $email;

    // Send newly registered customers to Account page to set their address
    echo "<script>alert('Account created! Please set up your delivery address.'); window.location.href='account.php';</script>";
    exit;
}

// 3. Customer Logout
if ($action === 'logout') {
    unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_email']);
    header('Location: index.php');
    exit;
}