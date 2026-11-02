<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['admin','committee']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
verify_csrf();

$action = (string) ($_POST['action'] ?? '');
$registrationId = filter_var($_POST['registration_id'] ?? null, FILTER_VALIDATE_INT);
$redirectTo = base_url('admin/index.php');
if (!$registrationId) {
    set_flash('Pendaftaran tidak valid.', 'danger');
    redirect($redirectTo);
}

try {
    if ($action === 'biodata') {
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['verified','needs_revision'], true)) {
            throw new RuntimeException('Status verifikasi tidak valid.');
        }
        $stmt = $pdo->prepare("UPDATE registrations SET biodata_status=? WHERE id=? AND biodata_status IN ('submitted','needs_revision')");
        $stmt->execute([$status,$registrationId]);
        if (!$stmt->rowCount()) {
            throw new RuntimeException('Status data tidak dapat diubah pada tahap ini.');
        }
        log_activity($pdo, (int) $user['id'], 'biodata_' . $status, 'Pendaftaran #' . $registrationId);
        set_flash('Status biodata berhasil diperbarui.');
    } elseif ($action === 'payment') {
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['verified','rejected'], true)) {
            throw new RuntimeException('Status pembayaran tidak valid.');
        }
        $pdo->beginTransaction();
        $payment = $pdo->prepare("UPDATE payments SET status=?,verified_by=?,verified_at=NOW() WHERE registration_id=? AND status='pending' AND proof_file IS NOT NULL");
        $payment->execute([$status,$user['id'],$registrationId]);
        if (!$payment->rowCount()) {
            throw new RuntimeException('Pembayaran tidak ditemukan atau sudah diproses.');
        }
        $pdo->prepare('UPDATE registrations SET payment_status=? WHERE id=?')->execute([$status,$registrationId]);
        log_activity($pdo, (int) $user['id'], 'payment_' . $status, 'Pendaftaran #' . $registrationId);
        $pdo->commit();
        set_flash('Status pembayaran berhasil diperbarui.');
    } elseif ($action === 'document') {
        $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        if (!$documentId || !in_array($status, ['verified','rejected'], true)) {
            throw new RuntimeException('Dokumen atau status verifikasi tidak valid.');
        }
        $exists = $pdo->prepare('SELECT id FROM registration_documents WHERE id=? AND registration_id=?');
        $exists->execute([$documentId,$registrationId]);
        if (!$exists->fetch()) {
            throw new RuntimeException('Dokumen tidak ditemukan.');
        }
        $stmt = $pdo->prepare('UPDATE registration_documents SET status=?,reviewer_note=? WHERE id=? AND registration_id=?');
        $stmt->execute([$status,trim((string) ($_POST['reviewer_note'] ?? '')) ?: null,$documentId,$registrationId]);
        log_activity($pdo, (int) $user['id'], 'document_' . $status, 'Pendaftaran #' . $registrationId);
        set_flash('Status dokumen berhasil diperbarui.');
    } elseif ($action === 'selection') {
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['accepted','rejected','waitlisted'], true)) {
            throw new RuntimeException('Hasil seleksi tidak valid.');
        }
        $check = $pdo->prepare("SELECT r.id FROM registrations r LEFT JOIN education_levels l ON l.id=r.education_level_id WHERE r.id=? AND r.biodata_status='verified' AND r.payment_status IN ('verified','not_required') AND (SELECT COUNT(*) FROM registration_documents d WHERE d.registration_id=r.id AND d.document_type IN ('family_card','birth_certificate','parent_id','photo','diploma','report') AND d.status='verified' AND (COALESCE(l.code,'')<>'SD' OR d.document_type IN ('family_card','birth_certificate','parent_id','photo')))=IF(l.code='SD',4,6)");
        $check->execute([$registrationId]);
        if (!$check->fetch()) {
            throw new RuntimeException('Biodata, pembayaran, dan seluruh dokumen wajib harus diverifikasi sebelum hasil seleksi ditetapkan.');
        }
        $note = trim((string) ($_POST['note'] ?? ''));
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE registrations SET selection_status=? WHERE id=?')->execute([$status,$registrationId]);
        $sql = 'INSERT INTO selection_results (registration_id,result,note,decided_by,published_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE result=VALUES(result),note=VALUES(note),decided_by=VALUES(decided_by),published_at=NOW()';
        $pdo->prepare($sql)->execute([$registrationId,$status,$note ?: null,$user['id']]);
        log_activity($pdo, (int) $user['id'], 'selection_' . $status, 'Pendaftaran #' . $registrationId);
        $pdo->commit();
        set_flash('Hasil seleksi berhasil diterbitkan.');
    } elseif ($action === 'reenrollment') {
        $status = (string) ($_POST['status'] ?? '');
        $dueDate = trim((string) ($_POST['due_date'] ?? '')) ?: null;
        if (!in_array($status, ['pending','in_progress','completed'], true)) {
            throw new RuntimeException('Status daftar ulang tidak valid.');
        }
        if ($dueDate && (!DateTime::createFromFormat('!Y-m-d', $dueDate) || DateTime::createFromFormat('!Y-m-d', $dueDate)->format('Y-m-d') !== $dueDate)) {
            throw new RuntimeException('Tanggal batas daftar ulang tidak valid.');
        }
        $check = $pdo->prepare("SELECT id FROM registrations WHERE id=? AND selection_status='accepted'");
        $check->execute([$registrationId]);
        if (!$check->fetch()) {
            throw new RuntimeException('Daftar ulang hanya tersedia bagi siswa yang diterima.');
        }
        $note = trim((string) ($_POST['note'] ?? ''));
        $sql = 'INSERT INTO reenrollments (registration_id,status,due_date,note,verified_by,completed_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),due_date=VALUES(due_date),note=VALUES(note),verified_by=VALUES(verified_by),completed_at=VALUES(completed_at)';
        $pdo->prepare($sql)->execute([$registrationId,$status,$dueDate,$note ?: null,$user['id'],$status==='completed'?date('Y-m-d H:i:s'):null]);
        log_activity($pdo, (int) $user['id'], 'reenrollment_' . $status, 'Pendaftaran #' . $registrationId);
        set_flash('Status daftar ulang berhasil diperbarui.');
    } else {
        throw new RuntimeException('Aksi tidak dikenali.');
    }
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash($exception->getMessage(), 'warning');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Admin action failed: ' . $exception->getMessage());
    set_flash('Perubahan belum dapat disimpan.', 'danger');
}
redirect($redirectTo);
