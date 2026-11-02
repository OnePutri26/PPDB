<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['student','admin','committee']);
$query = 'SELECT r.registration_number,r.created_at,r.submitted_at,r.biodata_status,r.payment_status,r.selection_status,r.class_choice,ay.name AS year_name,w.name AS wave_name,p.name AS path_name,m.name AS major_name,m2.name AS second_major_name,l.name AS level_name,s.full_name,s.nik,s.nisn,s.gender,s.birth_place,s.birth_date,s.address,u.email FROM registrations r JOIN students s ON s.id=r.student_id JOIN users u ON u.id=s.user_id JOIN academic_years ay ON ay.id=r.academic_year_id JOIN registration_waves w ON w.id=r.wave_id JOIN registration_paths p ON p.id=r.path_id LEFT JOIN majors m ON m.id=r.major_id LEFT JOIN majors m2 ON m2.id=r.second_major_id LEFT JOIN education_levels l ON l.id=r.education_level_id WHERE ';
$recordKey = $user['role'] === 'student' ? (int) $user['id'] : filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$recordKey) {
    http_response_code(404);
    exit('Bukti pendaftaran tidak ditemukan.');
}
$query .= $user['role'] === 'student' ? 'u.id=? AND r.id=(SELECT MAX(r2.id) FROM registrations r2 JOIN students s2 ON s2.id=r2.student_id WHERE s2.user_id=u.id)' : 'r.id=?';
$stmt = $pdo->prepare($query);
$stmt->execute([$recordKey]);
$record = $stmt->fetch();
if (!$record) {
    http_response_code(404);
    exit('Bukti pendaftaran belum tersedia.');
}
$pageTitle = 'Bukti Pendaftaran';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-space"><div class="container"><div class="row justify-content-center"><div class="col-lg-8">
    <div class="content-card print-card">
        <div class="d-flex justify-content-between align-items-start"><div><p class="eyebrow mb-1">Bukti pendaftaran</p><h1 class="h3 fw-bold">Penerimaan Peserta Didik Baru</h1></div><button class="btn btn-primary no-print" onclick="window.print()"><i class="fa-solid fa-file-pdf me-2"></i>Cetak / Simpan PDF</button></div>
        <hr><p>Nomor pendaftaran: <strong><?= e($record['registration_number']) ?></strong></p>
        <h2 class="h5 fw-bold mt-4">Biodata</h2><dl class="row"><dt class="col-sm-4">Nama lengkap</dt><dd class="col-sm-8"><?= e($record['full_name']) ?></dd><dt class="col-sm-4">NIK</dt><dd class="col-sm-8"><?= e($record['nik']) ?></dd><dt class="col-sm-4">NISN</dt><dd class="col-sm-8"><?= e($record['nisn']) ?></dd><dt class="col-sm-4">Jenis kelamin</dt><dd class="col-sm-8"><?= e($record['gender']==='L'?'Laki-laki':'Perempuan') ?></dd><dt class="col-sm-4">Tempat, tanggal lahir</dt><dd class="col-sm-8"><?= e($record['birth_place']) ?>, <?= e($record['birth_date']) ?></dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($record['email']) ?></dd><dt class="col-sm-4">Alamat</dt><dd class="col-sm-8"><?= nl2br(e($record['address'])) ?></dd></dl>
        <h2 class="h5 fw-bold mt-4">Pilihan pendaftaran</h2><dl class="row"><dt class="col-sm-4">Jenjang</dt><dd class="col-sm-8"><?= e($record['level_name']??'Belum dipilih') ?></dd><dt class="col-sm-4">Tahun ajaran</dt><dd class="col-sm-8"><?= e($record['year_name']) ?></dd><dt class="col-sm-4">Gelombang</dt><dd class="col-sm-8"><?= e($record['wave_name']) ?></dd><dt class="col-sm-4">Jalur</dt><dd class="col-sm-8"><?= e($record['path_name']) ?></dd><dt class="col-sm-4">Program / kelas</dt><dd class="col-sm-8"><?= e($record['major_name'] ?? $record['class_choice'] ?? 'Tidak dipilih') ?><?= $record['second_major_name']?' · pilihan 2: '.e($record['second_major_name']):'' ?></dd></dl>
        <div class="alert alert-light mt-4 mb-0">Status: data <?= e(status_label($record['biodata_status'])) ?> · pembayaran <?= e(status_label($record['payment_status'])) ?> · seleksi <?= e(status_label($record['selection_status'])) ?><br><small>Dicetak pada <?= date('d-m-Y H:i') ?>. Simpan dokumen PDF melalui pilihan cetak browser.</small></div>
    </div>
</div></div></div></section>
<style>@media print{nav,.footer,.no-print{display:none!important}.section-space{padding:0!important}.content-card{box-shadow:none;border:0}}</style>
<?php require __DIR__ . '/../includes/footer.php'; ?>
