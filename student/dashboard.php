<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['student']);
$stmt = $pdo->prepare('SELECT s.*, r.id AS registration_id, r.registration_number, r.education_level_id, r.class_choice, r.second_major_id, r.biodata_status, r.payment_status, r.selection_status, ay.name AS year_name, ay.is_active AS registration_year_active, w.name AS wave_name,w.starts_on AS wave_starts_on,w.ends_on AS wave_ends_on, p.name AS path_name, m.name AS major_name,m2.name AS second_major_name,l.code AS level_code,l.name AS level_name, pay.amount, sr.note AS result_note, sr.published_at, rr.status AS reenrollment_status, rr.due_date AS reenrollment_due_date, rr.note AS reenrollment_note FROM students s LEFT JOIN registrations r ON r.id=(SELECT MAX(r2.id) FROM registrations r2 WHERE r2.student_id=s.id) LEFT JOIN academic_years ay ON ay.id = r.academic_year_id LEFT JOIN registration_waves w ON w.id = r.wave_id LEFT JOIN registration_paths p ON p.id = r.path_id LEFT JOIN majors m ON m.id = r.major_id LEFT JOIN majors m2 ON m2.id=r.second_major_id LEFT JOIN education_levels l ON l.id=r.education_level_id LEFT JOIN payments pay ON pay.registration_id = r.id LEFT JOIN selection_results sr ON sr.registration_id = r.id LEFT JOIN reenrollments rr ON rr.registration_id = r.id WHERE s.user_id = ?');
$stmt->execute([$user['id']]);
$data = $stmt->fetch();
if (!$data) {
    http_response_code(404);
    exit('Data calon siswa tidak ditemukan.');
}
$applicationButtonLabel = !$data['registration_id']
    ? 'Mulai pendaftaran'
    : ((bool) $data['registration_year_active'] ? 'Perbaiki data' : 'Daftar tahun ajaran baru');
$docs = [];
if ($data['registration_id']) {
    $docStmt = $pdo->prepare('SELECT document_type, status FROM registration_documents WHERE registration_id = ?');
    $docStmt->execute([$data['registration_id']]);
    $docs = $docStmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
$required = ['family_card', 'birth_certificate', 'parent_id', 'photo', 'diploma', 'report'];
$required = $data['level_code'] === 'SD' ? ['family_card','birth_certificate','parent_id','photo'] : $required;
$verifiedDocs = count(array_filter($required,static fn(string $type): bool => ($docs[$type]??null)==='verified'));
$profileFields = ['nik','nisn','full_name','gender','birth_place','birth_date','religion','address','whatsapp'];
$profileComplete = count(array_filter($profileFields,static fn(string $field): bool => !empty($data[$field])));
$profileProgress = (int)round(($profileComplete+count(array_intersect($required,array_keys($docs)))/(max(1,count($required)))*count($profileFields))/(count($profileFields)*2)*100);
$announcements = $pdo->query('SELECT title,body,publish_at FROM announcements WHERE is_published=1 AND (publish_at IS NULL OR publish_at<=NOW()) ORDER BY COALESCE(publish_at,created_at) DESC LIMIT 3')->fetchAll();
$history = [];
$registrationHistory = [];
if ($data['registration_id']) {
    $historyStmt = $pdo->prepare('SELECT action, details, created_at FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
    $historyStmt->execute([$user['id']]);
    $history = $historyStmt->fetchAll();
    $registrationHistoryStmt = $pdo->prepare('SELECT r.registration_number,r.created_at,ay.name AS year_name,r.biodata_status,r.payment_status,r.selection_status FROM registrations r JOIN students s ON s.id=r.student_id JOIN academic_years ay ON ay.id=r.academic_year_id WHERE s.user_id=? ORDER BY r.created_at DESC');
    $registrationHistoryStmt->execute([$user['id']]);
    $registrationHistory = $registrationHistoryStmt->fetchAll();
}
$pageTitle = 'Dashboard Calon Siswa';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-space"><div class="container">
    <div class="dashboard-hero mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
        <div><p class="eyebrow mb-1">Ruang siswa · <?= e(date('l, d F Y')) ?></p><h1 class="h3 fw-bold mb-1">Hai, <?= e($data['full_name'] ?: $user['name']) ?> <span aria-hidden="true">✦</span></h1><p class="text-secondary mb-0">One step at a time. Pantau semuanya dari sini.</p></div>
        <?php if (!$data['registration_id'] || in_array($data['biodata_status'], ['draft', 'needs_revision'], true) || !(bool) $data['registration_year_active']): ?><a class="btn btn-primary" href="<?= e(base_url('student/application.php')) ?>"><i class="fa-solid fa-pen-to-square me-2"></i><?= e($applicationButtonLabel) ?></a><?php endif; ?>
    </div>
    <?php if ($data['registration_id']): ?>
    <div class="content-card mb-4"><div class="d-flex justify-content-between gap-2"><div><p class="eyebrow mb-1">Persiapan formulir</p><strong><?= $profileProgress ?>% lengkap</strong></div><span class="dashboard-status"><?= e($data['level_code']??'Jenjang') ?> · <?= e($data['registration_number']) ?></span></div><div class="progress mt-3" role="progressbar" aria-valuenow="<?= $profileProgress ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $profileProgress ?>%"></div></div></div>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="text-secondary small">Nomor pendaftaran</div><div class="stat-number fs-5"><?= e($data['registration_number']) ?></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="text-secondary small">Dokumen terverifikasi</div><div class="stat-number"><?= $verifiedDocs ?>/<?= count($required) ?></div><small class="text-secondary">dokumen wajib</small></div></div>
        <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="text-secondary small">Pembayaran</div><div class="mt-2"><span class="badge text-bg-light"><?= e(status_label($data['payment_status'])) ?></span></div></div></div>
        <div class="col-sm-6 col-lg-3"><div class="stat-card"><div class="text-secondary small">Verifikasi data</div><div class="mt-2"><span class="badge text-bg-light"><?= e(status_label($data['biodata_status'])) ?></span></div></div></div>
    </div>
    <div class="row g-4">
        <div class="col-lg-7"><div class="content-card h-100"><div class="d-flex justify-content-between align-items-start"><div><p class="eyebrow mb-1">Ringkasan pendaftaran</p><h2 class="h5 fw-bold">Status dan pilihan</h2></div><a class="btn btn-outline-primary btn-sm" href="<?= e(base_url('student/receipt.php')) ?>"><i class="fa-solid fa-print me-1"></i>Cetak bukti</a></div>
            <dl class="row mb-0"><dt class="col-sm-5 text-secondary">Jenjang</dt><dd class="col-sm-7"><?= e($data['level_name']??'Menunggu konfigurasi') ?></dd><dt class="col-sm-5 text-secondary">Tahun ajaran</dt><dd class="col-sm-7"><?= e($data['year_name'] ?? '-') ?></dd><dt class="col-sm-5 text-secondary">Gelombang / jadwal</dt><dd class="col-sm-7"><?= e($data['wave_name'] ?? '-') ?><small class="d-block text-secondary"><?= e($data['wave_starts_on']??'') ?> <?= $data['wave_ends_on']?'— '.e($data['wave_ends_on']):'' ?></small></dd><dt class="col-sm-5 text-secondary">Jalur</dt><dd class="col-sm-7"><?= e($data['path_name'] ?? '-') ?></dd><dt class="col-sm-5 text-secondary">Program / kelas</dt><dd class="col-sm-7"><?= e($data['major_name'] ?? $data['class_choice'] ?? 'Tidak dipilih') ?><?= $data['second_major_name']?' · pilihan 2: '.e($data['second_major_name']):'' ?></dd><dt class="col-sm-5 text-secondary">Status seleksi</dt><dd class="col-sm-7"><span class="dashboard-status"><?= e(status_label($data['selection_status'])) ?></span></dd></dl>
            <?php if ($data['published_at']): ?><div class="alert alert-info mt-3 mb-0"><strong>Hasil seleksi:</strong> <?= e(status_label($data['selection_status'])) ?>. <?= e($data['result_note'] ?? '') ?></div><?php endif; ?>
            <?php if ($data['selection_status']==='accepted'): ?><div class="alert alert-success mt-3 mb-0"><strong>Daftar ulang:</strong> <?= e(status_label($data['reenrollment_status'] ?? 'pending')) ?><?= $data['reenrollment_due_date'] ? ' · Batas '.$data['reenrollment_due_date'] : '' ?><br><?= e($data['reenrollment_note'] ?? '') ?></div><?php endif; ?>
            <?php if (in_array($data['payment_status'],['unpaid','rejected'],true) && (float) $data['amount'] > 0): ?><a class="btn btn-primary mt-3" href="<?= e(base_url('student/payment.php')) ?>"><?= $data['payment_status']==='rejected'?'Kirim ulang bukti pembayaran':'Kirim bukti pembayaran' ?></a><?php endif; ?>
        </div></div>
        <div class="col-lg-5"><div class="content-card h-100"><p class="eyebrow mb-1">Dokumen</p><h2 class="h5 fw-bold">Status kelengkapan dokumen</h2><ul class="list-group list-group-flush"><?php foreach (['family_card'=>'Kartu Keluarga','birth_certificate'=>'Akta kelahiran','parent_id'=>'KTP orang tua','photo'=>'Pas foto','diploma'=>'Ijazah / SKL','report'=>'Rapor','achievement'=>'Sertifikat prestasi'] as $type=>$label): ?><li class="list-group-item px-0 d-flex justify-content-between"><span><?= e($label) ?></span><span class="text-secondary"><?= isset($docs[$type]) ? e(status_label($docs[$type])) : 'Belum diunggah' ?></span></li><?php endforeach; ?></ul><a href="<?= e(base_url('student/application.php')) ?>" class="btn btn-outline-primary btn-sm mt-3">Lihat / lengkapi data</a></div></div>
        <?php if($announcements): ?><div class="col-12"><div class="content-card"><p class="eyebrow mb-1">Dari sekolah</p><h2 class="h5 fw-bold">Pengumuman terbaru</h2><div class="row g-3"><?php foreach($announcements as $announcement): ?><div class="col-md-4"><article class="feature-card"><div class="feature-icon orange mb-3"><i class="fa-solid fa-bullhorn"></i></div><h3 class="h6 fw-bold"><?= e($announcement['title']) ?></h3><p class="small text-secondary mb-0"><?= nl2br(e($announcement['body'])) ?></p></article></div><?php endforeach; ?></div></div></div><?php endif; ?>
        <div class="col-12"><div class="content-card"><p class="eyebrow mb-1">Riwayat</p><h2 class="h5 fw-bold">Aktivitas pendaftaran</h2><?php if (!$history): ?><p class="text-secondary mb-0">Belum ada aktivitas.</p><?php else: ?><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Aktivitas</th><th>Waktu</th></tr></thead><tbody><?php foreach ($history as $item): ?><tr><td><?= e(ucfirst(str_replace('_',' ',$item['action']))) ?> <?= e($item['details'] ?? '') ?></td><td><?= e($item['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
        <?php if (count($registrationHistory)>1): ?><div class="col-12"><div class="content-card"><h2 class="h5 fw-bold">Riwayat pendaftaran</h2><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Nomor</th><th>Tahun ajaran</th><th>Terdaftar</th><th>Data</th><th>Pembayaran</th><th>Seleksi</th></tr></thead><tbody><?php foreach($registrationHistory as $prior): ?><tr><td><?= e($prior['registration_number']) ?></td><td><?= e($prior['year_name']) ?></td><td><?= e($prior['created_at']) ?></td><td><?= e(status_label($prior['biodata_status'])) ?></td><td><?= e(status_label($prior['payment_status'])) ?></td><td><?= e(status_label($prior['selection_status'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div><?php endif; ?>
    </div>
    <?php else: ?><div class="content-card"><div class="feature-icon mb-3"><i class="fa-solid fa-clipboard-list"></i></div><h2 class="h5 fw-bold">Pendaftaran belum dimulai</h2><p class="text-secondary">Lengkapi formulir dan siapkan dokumen. Jika gelombang belum tersedia, hubungi panitia sekolah.</p><a class="btn btn-primary" href="<?= e(base_url('student/application.php')) ?>">Isi formulir pendaftaran</a></div><?php endif; ?>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
