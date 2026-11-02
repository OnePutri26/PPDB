<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_role(['admin','committee']);
$levels = $pdo->query("SELECT id,code,name FROM education_levels ORDER BY FIELD(code,'SD','SMP','SMA','SMK')")->fetchAll();
$years = $pdo->query('SELECT id,name FROM academic_years ORDER BY name DESC')->fetchAll();
$waves = $pdo->query('SELECT id,name,academic_year_id FROM registration_waves ORDER BY id DESC')->fetchAll();
$filters = [];
$params = [];
foreach (['education_level_id','academic_year_id','wave_id'] as $filter) {
    $value = filter_input(INPUT_GET,$filter,FILTER_VALIDATE_INT);
    if ($value) {
        $filters[$filter] = $value;
        $params[] = $value;
    }
}
$where = [];
if (isset($filters['education_level_id'])) $where[] = 'r.education_level_id=?';
if (isset($filters['academic_year_id'])) $where[] = 'r.academic_year_id=?';
if (isset($filters['wave_id'])) $where[] = 'r.wave_id=?';
$whereSql = $where ? ' WHERE ' . implode(' AND ',$where) : '';
$statsStmt = $pdo->prepare("SELECT COUNT(*) AS total,SUM(r.selection_status='accepted') AS accepted,SUM(r.biodata_status IN ('submitted','needs_revision')) AS unverified,SUM(r.selection_status='rejected') AS rejected FROM registrations r{$whereSql}");
$statsStmt->execute($params);
$stats = $statsStmt->fetch();
$levelStatsStmt = $pdo->prepare("SELECT l.code,COUNT(r.id) AS total FROM education_levels l LEFT JOIN registrations r ON r.education_level_id=l.id" . ($where ? ' AND ' . implode(' AND ',array_map(static fn(string $condition):string=>str_replace('r.','r.',$condition),$where)) : '') . " GROUP BY l.id,l.code ORDER BY FIELD(l.code,'SD','SMP','SMA','SMK')");
$levelStatsStmt->execute($params);
$levelStats = $levelStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$chartStmt = $pdo->prepare("SELECT DATE_FORMAT(r.created_at,'%Y-%m') AS month,COUNT(*) AS total FROM registrations r{$whereSql}" . ($where ? ' AND' : ' WHERE') . " r.created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(r.created_at,'%Y-%m') ORDER BY month");
$chartStmt->execute($params);
$chartRows = $chartStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$chartLabels = $chartData = [];
$chartStart = new DateTimeImmutable('first day of this month');
for ($i = 11; $i >= 0; $i--) {
    $month = $chartStart->modify("-{$i} months")->format('Y-m');
    $chartLabels[] = $month;
    $chartData[] = (int) ($chartRows[$month] ?? 0);
}
$selectionStmt = $pdo->prepare("SELECT r.selection_status,COUNT(*) AS total FROM registrations r{$whereSql} GROUP BY r.selection_status");
$selectionStmt->execute($params);
$selectionRows = $selectionStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$rowsStmt = $pdo->prepare("SELECT r.id,r.registration_number,r.biodata_status,r.payment_status,r.selection_status,r.submitted_at,r.class_choice,s.full_name,s.nik,s.nisn,u.email,w.name AS wave_name,p.name AS path_name,m.name AS major_name,l.code AS level_code,l.name AS level_name,pay.amount,pay.id AS payment_id,pay.proof_file FROM registrations r JOIN students s ON s.id=r.student_id JOIN users u ON u.id=s.user_id JOIN registration_waves w ON w.id=r.wave_id JOIN registration_paths p ON p.id=r.path_id LEFT JOIN majors m ON m.id=r.major_id LEFT JOIN education_levels l ON l.id=r.education_level_id LEFT JOIN payments pay ON pay.registration_id=r.id{$whereSql} ORDER BY r.created_at DESC LIMIT 100");
$rowsStmt->execute($params);
$rows = $rowsStmt->fetchAll();
$documents = [];
if ($rows) {
    $registrationIds = array_map(static fn(array $row): int => (int) $row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($registrationIds), '?'));
    $documentsQuery = $pdo->prepare("SELECT id,registration_id,document_type,original_name,status FROM registration_documents WHERE registration_id IN ({$placeholders}) ORDER BY registration_id,id");
    $documentsQuery->execute($registrationIds);
    foreach ($documentsQuery->fetchAll() as $document) {
        $documents[$document['registration_id']][] = $document;
    }
}
$pageTitle = 'Dashboard Admin';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-space"><div class="container">
    <div class="dashboard-hero mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
        <div><p class="eyebrow mb-1">Panel panitia</p><h1 class="h3 fw-bold mb-1">Dashboard PPDB</h1><p class="text-secondary mb-0">Kelola pendaftaran dan verifikasi calon siswa.</p></div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="<?= e(base_url('admin/export.php')) ?>"><i class="fa-solid fa-file-csv me-2"></i>Ekspor CSV (Excel)</a><a class="btn btn-outline-primary" href="<?= e(base_url('admin/reenrollment.php')) ?>">Daftar ulang</a><?php if ($admin['role']==='admin'): ?><a class="btn btn-primary" href="<?= e(base_url('admin/manage.php')) ?>">Pengaturan & master data</a><?php endif; ?></div>
    </div>
    <div class="content-card mb-4"><form method="get" class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label" for="education_level_id">Jenjang</label><select class="form-select" id="education_level_id" name="education_level_id"><option value="">Semua jenjang</option><?php foreach($levels as $level): ?><option value="<?= e($level['id']) ?>" <?= ($filters['education_level_id']??null)==$level['id']?'selected':'' ?>><?= e($level['code'].' · '.$level['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label" for="academic_year_id">Tahun ajaran</label><select class="form-select" id="academic_year_id" name="academic_year_id"><option value="">Semua tahun</option><?php foreach($years as $year): ?><option value="<?= e($year['id']) ?>" <?= ($filters['academic_year_id']??null)==$year['id']?'selected':'' ?>><?= e($year['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label" for="wave_id">Gelombang</label><select class="form-select" id="wave_id" name="wave_id"><option value="">Semua gelombang</option><?php foreach($waves as $wave): ?><option value="<?= e($wave['id']) ?>" <?= ($filters['wave_id']??null)==$wave['id']?'selected':'' ?>><?= e($wave['name']) ?></option><?php endforeach; ?></select></div><div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="fa-solid fa-filter me-2"></i>Terapkan filter</button><a class="btn btn-outline-primary" href="<?= e(base_url('admin/index.php')) ?>">Reset</a></div></form></div>
    <div class="row g-3 mb-4">
        <?php foreach ([['Jumlah pendaftar',$stats['total'],'fa-users'],['Diterima',$stats['accepted'] ?? 0,'fa-circle-check'],['Belum diverifikasi',$stats['unverified'] ?? 0,'fa-hourglass-half'],['Ditolak',$stats['rejected'] ?? 0,'fa-circle-xmark']] as [$label,$number,$icon]): ?><div class="col-6 col-lg-3"><div class="stat-card d-flex justify-content-between"><div><div class="text-secondary small"><?= e($label) ?></div><div class="stat-number"><?= e($number) ?></div></div><div class="feature-icon"><i class="fa-solid <?= e($icon) ?>"></i></div></div></div><?php endforeach; ?>
    </div>
    <div class="row g-3 mb-4"><div class="col-lg-7"><div class="content-card h-100"><p class="eyebrow mb-1">Tren pendaftaran</p><h2 class="h5 fw-bold">Pendaftar dalam 12 bulan</h2><canvas id="registrationChart" height="130" aria-label="Grafik pendaftar per bulan"></canvas></div></div><div class="col-lg-5"><div class="content-card h-100"><p class="eyebrow mb-1">Performa PPDB</p><h2 class="h5 fw-bold">Status seleksi</h2><canvas id="selectionChart" height="155" aria-label="Grafik status seleksi"></canvas></div></div></div>
    <div class="row g-3 mb-4"><?php foreach($levels as $level): ?><div class="col-6 col-lg-3"><div class="stat-card d-flex justify-content-between align-items-center"><div><div class="text-secondary small"><?= e($level['name']) ?></div><div class="stat-number"><?= e($levelStats[$level['code']]??0) ?></div></div><span class="section-tag"><?= e($level['code']) ?></span></div></div><?php endforeach; ?></div>
    <div class="content-card" id="pendaftar">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3"><div><p class="eyebrow mb-1">Manajemen calon siswa</p><h2 class="h5 fw-bold mb-0">Pendaftar terbaru</h2></div><span class="text-secondary small">Menampilkan maksimal 100 pendaftar terbaru</span></div>
        <?php if (!$rows): ?><div class="alert alert-info mb-0">Belum ada pendaftaran masuk.</div><?php else: ?>
        <div class="table-responsive"><table class="table table-hover align-middle">
            <thead><tr><th>Pendaftar</th><th>Pilihan</th><th>Status</th><th>Dokumen</th><th>Pembayaran</th><th>Seleksi</th></tr></thead>
            <tbody><?php foreach($rows as $row): ?>
                <tr><td><strong><?= e($row['full_name']) ?></strong><div class="small text-secondary"><?= e($row['registration_number']) ?> · <a href="<?= e(base_url('student/receipt.php?id='.$row['id'])) ?>">Cetak</a><br><?= e($row['email']) ?><br>NIK <?= e($row['nik']) ?> · NISN <?= e($row['nisn']) ?></div></td>
                <td><span class="dashboard-status"><?= e($row['level_code']??'Belum diset') ?></span><div class="small text-secondary"><?= e($row['wave_name']) ?> · <?= e($row['path_name']) ?><br><?= e($row['major_name'] ?? $row['class_choice'] ?? 'Tanpa program') ?></div></td>
                <td><span class="badge text-bg-light"><?= e(status_label($row['biodata_status'])) ?></span>
                    <form method="post" action="<?= e(base_url('admin/action.php')) ?>" class="d-flex gap-1 mt-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="registration_id" value="<?= e($row['id']) ?>"><input type="hidden" name="action" value="biodata"><button name="status" value="verified" class="btn btn-success btn-sm">Verifikasi</button><button name="status" value="needs_revision" class="btn btn-outline-warning btn-sm">Perbaiki</button></form>
                </td>
                <td><?php foreach($documents[$row['id']] ?? [] as $doc): ?><div class="border-bottom py-2 small"><a href="<?= e(base_url('student/file.php?id='.$doc['id'])) ?>"><i class="fa-solid fa-paperclip me-1"></i><?= e(ucwords(str_replace('_',' ',$doc['document_type']))) ?></a><span class="badge text-bg-light ms-1"><?= e(status_label($doc['status'])) ?></span>
                    <form method="post" action="<?= e(base_url('admin/action.php')) ?>" class="d-flex gap-1 mt-1"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="registration_id" value="<?= e($row['id']) ?>"><input type="hidden" name="document_id" value="<?= e($doc['id']) ?>"><input type="hidden" name="action" value="document"><button name="status" value="verified" class="btn btn-outline-success btn-sm">Setujui</button><button name="status" value="rejected" class="btn btn-outline-danger btn-sm">Tolak</button></form></div><?php endforeach; ?>
                </td>
                <td><span class="badge text-bg-light"><?= e(status_label($row['payment_status'])) ?></span><?php if ($row['payment_id'] && $row['proof_file']): ?><div class="small"><a href="<?= e(base_url('student/file.php?id='.$row['payment_id'].'&payment=1')) ?>">Lihat bukti</a></div><?php endif; ?>
                    <?php if ($row['payment_status']==='pending'): ?><form method="post" action="<?= e(base_url('admin/action.php')) ?>" class="d-flex gap-1 mt-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="registration_id" value="<?= e($row['id']) ?>"><input type="hidden" name="action" value="payment"><button name="status" value="verified" class="btn btn-success btn-sm">Setujui</button><button name="status" value="rejected" class="btn btn-outline-danger btn-sm">Tolak</button></form><?php endif; ?>
                </td>
                <td><span class="badge text-bg-light"><?= e(status_label($row['selection_status'])) ?></span>
                    <form method="post" action="<?= e(base_url('admin/action.php')) ?>" class="mt-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="registration_id" value="<?= e($row['id']) ?>"><input type="hidden" name="action" value="selection"><select class="form-select form-select-sm mb-1" name="status"><option value="accepted">Diterima</option><option value="rejected">Tidak diterima</option><option value="waitlisted">Daftar tunggu</option></select><input class="form-control form-control-sm mb-1" name="note" placeholder="Catatan hasil"><button class="btn btn-primary btn-sm">Terbitkan hasil</button></form>
                </td></tr>
            <?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </div>
</div></section>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const registrationChart = document.getElementById('registrationChart');
if (registrationChart) new Chart(registrationChart, {
    type: 'line',
    data: { labels: <?= json_encode($chartLabels, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, datasets: [{ label: 'Pendaftar', data: <?= json_encode($chartData) ?>, borderColor: '#5365f5', backgroundColor: 'rgba(83,101,245,.12)', fill: true, tension: .38, pointRadius: 3 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
const selectionChart = document.getElementById('selectionChart');
if (selectionChart) new Chart(selectionChart, { type: 'doughnut', data: { labels: <?= json_encode(array_map('status_label',array_keys($selectionRows)),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>, datasets: [{ data: <?= json_encode(array_values(array_map('intval',$selectionRows))) ?>, backgroundColor: ['#5365f5','#8d5cf6','#ff9557','#20a879'], borderWidth: 0 }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '68%' } });
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
