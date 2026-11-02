<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin','committee']);
$rows = $pdo->query("SELECT r.id,r.registration_number,s.full_name,u.email,rr.status,rr.due_date,rr.note FROM registrations r JOIN students s ON s.id=r.student_id JOIN users u ON u.id=s.user_id LEFT JOIN reenrollments rr ON rr.registration_id=r.id WHERE r.selection_status='accepted' ORDER BY s.full_name")->fetchAll();
$pageTitle = 'Daftar Ulang';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-space"><div class="container"><div class="d-flex justify-content-between align-items-center mb-4"><div><p class="eyebrow mb-1">Calon siswa diterima</p><h1 class="h3 fw-bold">Manajemen daftar ulang</h1></div><a class="btn btn-outline-primary" href="<?= e(base_url('admin/index.php')) ?>">Kembali ke dashboard</a></div>
    <div class="content-card"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Siswa</th><th>Status daftar ulang</th><th>Catatan & batas waktu</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach($rows as $row): ?><tr><td><strong><?= e($row['full_name']) ?></strong><div class="small text-secondary"><?= e($row['registration_number']) ?><br><?= e($row['email']) ?></div></td><td><?= e(status_label($row['status'] ?? 'pending')) ?></td><td><form method="post" action="<?= e(base_url('admin/action.php')) ?>" id="re-<?= e($row['id']) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reenrollment"><input type="hidden" name="registration_id" value="<?= e($row['id']) ?>"><input class="form-control form-control-sm mb-1" type="date" name="due_date" value="<?= e($row['due_date'] ?? '') ?>"><input class="form-control form-control-sm" name="note" placeholder="Catatan daftar ulang" value="<?= e($row['note'] ?? '') ?>"></form></td><td><select class="form-select form-select-sm mb-2" name="status" form="re-<?= e($row['id']) ?>"><option value="pending" <?= ($row['status'] ?? '')==='pending'?'selected':'' ?>>Belum dimulai</option><option value="in_progress" <?= ($row['status'] ?? '')==='in_progress'?'selected':'' ?>>Dalam proses</option><option value="completed" <?= ($row['status'] ?? '')==='completed'?'selected':'' ?>>Selesai</option></select><button class="btn btn-primary btn-sm" type="submit" form="re-<?= e($row['id']) ?>">Simpan status</button></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-secondary py-4">Belum ada siswa yang ditetapkan diterima.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
