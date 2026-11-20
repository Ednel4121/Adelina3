<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'officer'], true)) {
    http_response_code(403);
    exit('Walang pahintulot.');
}

$paymentId = filter_var($_GET['payment_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($paymentId === false) {
    http_response_code(400);
    exit('Invalid payment ID.');
}

$stmt = $conn->prepare('SELECT payment_proof FROM payments WHERE payment_id = ?');
$stmt->bind_param('i', $paymentId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment || empty($payment['payment_proof'])) {
    http_response_code(404);
    exit('Hindi makita ang payment proof.');
}

$directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'hoa-payment-proofs';
$path = $directory . DIRECTORY_SEPARATOR . basename($payment['payment_proof']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Hindi makita ang payment proof file.');
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
];
header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
