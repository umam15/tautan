<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$csrf = $data['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $csrf)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$order = $data['order'] ?? null;
if (!is_array($order) || empty($order)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid order payload']);
    exit;
}

reorder_links($order);
echo json_encode(['ok' => true]);
