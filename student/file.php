<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['student','admin','committee']);
$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$paymentFile = isset($_GET['payment']);
if (!$documentId) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}
if ($paymentFile) {
    $stmt = $pdo->prepare('SELECT pay.proof_file AS stored_name, r.student_id, s.user_id FROM payments pay JOIN registrations r ON r.id=pay.registration_id JOIN students s ON s.id=r.student_id WHERE pay.id=? AND pay.proof_file IS NOT NULL');
} else {
    $stmt = $pdo->prepare('SELECT d.stored_name, r.student_id, s.user_id FROM registration_documents d JOIN registrations r ON r.id=d.registration_id JOIN students s ON s.id=r.student_id WHERE d.id=?');
}
$stmt->execute([$documentId]);
$file = $stmt->fetch();
if (!$file || ($user['role'] === 'student' && (int) $file['user_id'] !== (int) $user['id'])) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}
$path = __DIR__ . '/../uploads/private/' . basename($file['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="dokumen-' . $documentId . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
readfile($path);
