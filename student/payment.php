<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['student']);
$stmt = $pdo->prepare('SELECT r.id, r.registration_number, r.payment_status, pay.amount, pay.status AS payment_record_status FROM registrations r JOIN students s ON s.id=r.student_id JOIN payments pay ON pay.registration_id=r.id WHERE s.user_id=? AND r.id=(SELECT MAX(r2.id) FROM registrations r2 WHERE r2.student_id=s.id)');
$stmt->execute([$user['id']]);
$payment = $stmt->fetch();
$paymentInstructions = $pdo->query("SELECT setting_value FROM school_settings WHERE setting_key='payment_instructions'")->fetchColumn();
if (!$payment || (float) $payment['amount'] <= 0 || $payment['payment_status'] === 'not_required') {
    set_flash('Pembayaran tidak diperlukan atau pendaftaran belum tersedia.', 'info');
    redirect(base_url('student/dashboard.php'));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!in_array($payment['payment_status'], ['unpaid','rejected'], true)) {
        $error = 'Bukti pembayaran sedang diverifikasi atau pembayaran sudah disetujui.';
    } else {
        try {
            $file = upload_file($_FILES['proof'] ?? [], ['application/pdf','image/jpeg','image/png'], __DIR__ . '/../uploads/private');
            if (!$file) {
                throw new RuntimeException('Pilih bukti pembayaran terlebih dahulu.');
            }
            $reference = trim((string) ($_POST['reference_number'] ?? ''));
            $method = trim((string) ($_POST['method'] ?? ''));
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE payments SET method=?,reference_number=?,proof_file=?,status='pending',verified_by=NULL,verified_at=NULL WHERE registration_id=?")->execute([$method ?: null,$reference ?: null,$file['stored_name'],$payment['id']]);
            $pdo->prepare("UPDATE registrations SET payment_status='pending' WHERE id=?")->execute([$payment['id']]);
            log_activity($pdo, (int) $user['id'], 'payment_proof_submitted');
            $pdo->commit();
            set_flash('Bukti pembayaran berhasil dikirim dan menunggu verifikasi panitia.');
            redirect(base_url('student/dashboard.php'));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($file['stored_name'])) {
                $path = __DIR__ . '/../uploads/private/' . $file['stored_name'];
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if ($exception instanceof RuntimeException) {
                $error = $exception->getMessage();
            } else {
                error_log('Payment submission failed: ' . $exception->getMessage());
                $error = 'Bukti pembayaran belum berhasil dikirim.';
            }
        }
    }
}
$pageTitle = 'Pembayaran PPDB';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-space"><div class="container"><div class="row justify-content-center"><div class="col-lg-7">
    <div class="content-card"><p class="eyebrow mb-2">Pembayaran PPDB</p><h1 class="h3 fw-bold">Kirim bukti pembayaran</h1>
        <p class="text-secondary">Nomor pendaftaran <strong><?= e($payment['registration_number']) ?></strong></p><div class="alert alert-primary">Jumlah yang perlu dibayar: <strong>Rp <?= number_format((float) $payment['amount'],0,',','.') ?></strong>.<br><?= $paymentInstructions ? nl2br(e($paymentInstructions)) : 'Konfirmasi rekening resmi kepada panitia sekolah sebelum melakukan pembayaran.' ?></div>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" data-loading><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="mb-3"><label class="form-label" for="method">Metode pembayaran</label><input class="form-control" id="method" name="method" maxlength="50" placeholder="Transfer bank, virtual account, ..."></div>
            <div class="mb-3"><label class="form-label" for="reference_number">Nomor referensi transaksi</label><input class="form-control" id="reference_number" name="reference_number" maxlength="100"></div>
            <div class="mb-4"><label class="form-label" for="proof">Bukti pembayaran *</label><input class="form-control" type="file" id="proof" name="proof" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required><div class="form-text">PDF, JPG, atau PNG, maksimal 5 MB.</div></div>
            <button type="submit" class="btn btn-primary">Kirim untuk diverifikasi</button><a class="btn btn-link" href="<?= e(base_url('student/dashboard.php')) ?>">Kembali</a>
        </form>
    </div>
</div></div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
