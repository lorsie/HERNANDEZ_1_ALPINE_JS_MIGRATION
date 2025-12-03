<?php
session_start();
include '../dbConfig.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit;
}

$cart = json_decode($_POST['cart'] ?? '[]', true);
$total = floatval($_POST['total'] ?? 0);
$orderType = trim($_POST['orderType'] ?? 'Unknown');
$cashier = $_SESSION['user']['username'];

if (empty($cart) || $total <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'msg' => 'Invalid order data']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO transactions (items, total, order_type, cashier, date_added) VALUES (?, ?, ?, ?, NOW())");
$stmt->execute([json_encode($cart), $total, $orderType, $cashier]);

echo json_encode(['status' => 'success', 'msg' => 'Order saved']);