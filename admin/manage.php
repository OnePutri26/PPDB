<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_role(['admin']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'school') {
            $name = trim((string) ($_POST['school_name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Nama sekolah wajib diisi.');
            }
            $statSettings = [];
            foreach (['stat_students','stat_teachers','stat_alumni'] as $statKey) {
                $statValue = trim((string)($_POST[$statKey] ?? ''));
                if ($statValue !== '' && !preg_match('/^\d{1,9}$/',$statValue)) {
                    throw new RuntimeException('Statistik sekolah harus berupa angka.');
                }
                $statSettings[$statKey] = $statValue;
            }
            $schoolData = [$name,trim((string) ($_POST['npsn'] ?? '')) ?: null,trim((string) ($_POST['address'] ?? '')) ?: null,trim((string) ($_POST['phone'] ?? '')) ?: null,trim((string) ($_POST['school_email'] ?? '')) ?: null,trim((string) ($_POST['website'] ?? '')) ?: null,trim((string) ($_POST['description'] ?? '')) ?: null];
            $pdo->beginTransaction();
            $existingSchool = $pdo->query('SELECT id FROM schools ORDER BY id LIMIT 1')->fetchColumn();
            if ($existingSchool) {
                $pdo->prepare('UPDATE schools SET name=?,npsn=?,address=?,phone=?,email=?,website=?,description=? WHERE id=?')->execute([...$schoolData,$existingSchool]);
            } else {
                $pdo->prepare('INSERT INTO schools (name,npsn,address,phone,email,website,description) VALUES (?,?,?,?,?,?,?)')->execute($schoolData);
            }
            $settings = ['school_name'=>$name,'school_description'=>$schoolData[6] ?? '', 'school_email'=>$schoolData[4] ?? '', 'school_phone'=>$schoolData[3] ?? '', 'school_address'=>$schoolData[2] ?? ''];
            $settingSql = 'INSERT INTO school_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)';
            foreach ($settings as $key=>$value) {
                $pdo->prepare($settingSql)->execute([$key,$value]);
            }
            $instruction = trim((string) ($_POST['payment_instructions'] ?? ''));
            $pdo->prepare($settingSql)->execute(['payment_instructions',$instruction]);
            foreach ($statSettings as $statKey=>$statValue) {
                $pdo->prepare($settingSql)->execute([$statKey,$statValue]);
            }
            $pdo->commit();
            set_flash('Profil sekolah berhasil diperbarui.');
        } elseif ($action === 'year') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $starts = trim((string) ($_POST['starts_on'] ?? '')) ?: null;
            $ends = trim((string) ($_POST['ends_on'] ?? '')) ?: null;
            if ($name === '' || ($starts && $ends && $ends < $starts)) {
                throw new RuntimeException('Nama tahun ajaran wajib diisi dan rentang tanggal harus valid.');
            }
            $pdo->beginTransaction();
            if (isset($_POST['is_active'])) {
                $pdo->exec('UPDATE academic_years SET is_active=0');
            }
            $pdo->prepare('INSERT INTO academic_years (name,starts_on,ends_on,is_active) VALUES (?,?,?,?)')->execute([$name,$starts,$ends,isset($_POST['is_active'])?1:0]);
            log_activity($pdo, (int) $admin['id'], 'academic_year_created', $name);
            $pdo->commit();
            set_flash('Tahun ajaran berhasil ditambahkan.');
        } elseif ($action === 'wave') {
            $yearId = filter_var($_POST['academic_year_id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['name'] ?? ''));
            $starts = trim((string) ($_POST['starts_on'] ?? '')) ?: null;
            $ends = trim((string) ($_POST['ends_on'] ?? '')) ?: null;
            if (!$yearId || $name === '' || ($starts && $ends && $ends < $starts)) {
                throw new RuntimeException('Tahun ajaran, nama gelombang, atau rentang tanggal tidak valid.');
            }
            $pdo->prepare('INSERT INTO registration_waves (academic_year_id,name,starts_on,ends_on,is_open) VALUES (?,?,?,?,?)')->execute([$yearId,$name,$starts,$ends,isset($_POST['is_open'])?1:0]);
            log_activity($pdo, (int) $admin['id'], 'registration_wave_created', $name);
            set_flash('Gelombang pendaftaran berhasil ditambahkan.');
        } elseif ($action === 'path') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $fee = filter_var($_POST['fee'] ?? 0, FILTER_VALIDATE_FLOAT);
            if ($name === '' || $fee === false || $fee < 0) {
                throw new RuntimeException('Nama jalur atau biaya tidak valid.');
            }
            $pdo->prepare('INSERT INTO registration_paths (name,description,fee,is_active) VALUES (?,?,?,?)')->execute([$name,trim((string) ($_POST['description'] ?? '')) ?: null,$fee,isset($_POST['is_active'])?1:0]);
            set_flash('Jalur pendaftaran berhasil ditambahkan.');
        } elseif ($action === 'major') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $levelId = filter_var($_POST['education_level_id'] ?? null,FILTER_VALIDATE_INT);
            if ($name === '') {
                throw new RuntimeException('Nama jurusan / program studi wajib diisi.');
            }
            $levelQuery = $pdo->prepare('SELECT code FROM education_levels WHERE id=?');
            $levelQuery->execute([$levelId]);
            $levelCode = $levelQuery->fetchColumn();
            if (!$levelCode || !in_array($levelCode,['SMA','SMK'],true)) {
                throw new RuntimeException('Program studi hanya dapat ditambahkan untuk jenjang SMA atau SMK.');
            }
            $pdo->prepare('INSERT INTO majors (education_level_id,name,code,description,is_active) VALUES (?,?,?,?,?)')->execute([$levelId,$name,trim((string) ($_POST['code'] ?? '')) ?: null,trim((string) ($_POST['description'] ?? '')) ?: null,isset($_POST['is_active'])?1:0]);
            set_flash('Jurusan berhasil ditambahkan.');
        } elseif ($action === 'level_profile') {
            $levelId = filter_var($_POST['level_id'] ?? null,FILTER_VALIDATE_INT);
            $levelName = trim((string)($_POST['level_name'] ?? ''));
            $priorLabel = trim((string)($_POST['prior_school_label'] ?? ''));
            $programLabel = trim((string)($_POST['program_label'] ?? ''));
            if (!$levelId || $levelName==='' || $priorLabel==='' || $programLabel==='') {
                throw new RuntimeException('Nama jenjang, asal sekolah, dan label program wajib diisi.');
            }
            $pdo->prepare('UPDATE education_levels SET name=?,prior_school_label=?,program_label=?,class_info=?,facilities=?,tuition_info=?,is_active=? WHERE id=?')->execute([
                $levelName,$priorLabel,$programLabel,trim((string)($_POST['class_info'] ?? '')) ?: null,
                trim((string)($_POST['facilities'] ?? '')) ?: null,trim((string)($_POST['tuition_info'] ?? '')) ?: null,
                isset($_POST['is_active'])?1:0,$levelId,
            ]);
            log_activity($pdo,(int)$admin['id'],'education_level_updated',$levelName);
            set_flash('Konfigurasi jenjang berhasil diperbarui.');
        } elseif ($action === 'announcement') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));
            $publishAt = trim((string) ($_POST['publish_at'] ?? '')) ?: null;
            if ($title === '' || $body === '') {
                throw new RuntimeException('Judul dan isi pengumuman wajib diisi.');
            }
            $pdo->prepare('INSERT INTO announcements (title,body,publish_at,is_published,created_by) VALUES (?,?,?,?,?)')->execute([$title,$body,$publishAt,isset($_POST['is_published'])?1:0,$admin['id']]);
            set_flash('Pengumuman berhasil ditambahkan.');
        } elseif ($action === 'account') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? 'committee');
            if ($name === '' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<10 || !in_array($role,['admin','committee'],true)) {
                throw new RuntimeException('Nama, email, kata sandi minimal 10 karakter, dan peran akun harus valid.');
            }
            $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name=?');
            $roleStmt->execute([$role]);
            $roleId = $roleStmt->fetchColumn();
            $pdo->prepare('INSERT INTO users (role_id,name,email,password) VALUES (?,?,?,?)')->execute([$roleId,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            log_activity($pdo, (int) $admin['id'], 'staff_account_created', $email . ' (' . $role . ')');
            set_flash('Akun panitia berhasil ditambahkan.');
        } else {
            throw new RuntimeException('Jenis pengaturan tidak dikenali.');
        }
        redirect(base_url('admin/manage.php'));
    } catch (RuntimeException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getCode() === '23000') {
            $error = 'Data duplikat. Periksa nama, kode, email, atau NPSN.';
        } else {
            error_log('Admin master data update failed: ' . $exception->getMessage());
            $error = 'Pengaturan belum berhasil disimpan.';
        }
    }
}
$settings = $pdo->query('SELECT setting_key,setting_value FROM school_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$school = $pdo->query('SELECT * FROM schools ORDER BY id LIMIT 1')->fetch() ?: [];
$years = $pdo->query('SELECT id,name,is_active FROM academic_years ORDER BY name DESC')->fetchAll();
$waves = $pdo->query('SELECT w.id,w.name,w.is_open,y.name AS year_name FROM registration_waves w JOIN academic_years y ON y.id=w.academic_year_id ORDER BY w.id DESC')->fetchAll();
$paths = $pdo->query('SELECT id,name,fee,is_active FROM registration_paths ORDER BY name')->fetchAll();
$majors = $pdo->query('SELECT m.id,m.name,m.code,m.is_active,l.code AS level_code FROM majors m LEFT JOIN education_levels l ON l.id=m.education_level_id ORDER BY l.code,m.name')->fetchAll();
$levels = $pdo->query("SELECT id,code,name,prior_school_label,program_label,class_info,facilities,tuition_info,is_active FROM education_levels ORDER BY FIELD(code,'SD','SMP','SMA','SMK')")->fetchAll();
$staff = $pdo->query("SELECT u.id,u.name,u.email,r.name AS role,u.is_active FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name IN ('admin','committee') ORDER BY u.id DESC")->fetchAll();
$announcements = $pdo->query('SELECT id,title,is_published,publish_at FROM announcements ORDER BY created_at DESC LIMIT 50')->fetchAll();
$pageTitle = 'Pengaturan PPDB';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-space"><div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><p class="eyebrow mb-1">Konfigurasi PPDB</p><h1 class="h3 fw-bold">Pengaturan & master data</h1></div><a class="btn btn-outline-primary" href="<?= e(base_url('admin/index.php')) ?>">Kembali ke dashboard</a></div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-6"><div class="content-card h-100"><h2 class="h5 fw-bold">Profil sekolah & pembayaran</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="school">
            <?php foreach(['school_name'=>'Nama sekolah','npsn'=>'NPSN','school_email'=>'Email sekolah','phone'=>'Nomor telepon','website'=>'Situs web'] as $key=>$label): $column=$key==='school_name'?'name':($key==='school_email'?'email':$key); ?><div class="mb-2"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label><input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($_POST[$key] ?? $school[$column] ?? $settings[$key] ?? '') ?>"></div><?php endforeach; ?>
            <div class="mb-2"><label class="form-label" for="address">Alamat</label><textarea class="form-control" id="address" name="address" rows="2"><?= e($_POST['address'] ?? $school['address'] ?? '') ?></textarea></div>
            <div class="mb-2"><label class="form-label" for="description">Profil singkat</label><textarea class="form-control" id="description" name="description" rows="2"><?= e($_POST['description'] ?? $school['description'] ?? '') ?></textarea></div>
            <div class="mb-3"><label class="form-label" for="payment_instructions">Petunjuk pembayaran resmi</label><textarea class="form-control" id="payment_instructions" name="payment_instructions" rows="2"><?= e($_POST['payment_instructions'] ?? $settings['payment_instructions'] ?? '') ?></textarea></div>
            <div class="row g-2 mb-3"><?php foreach(['stat_students'=>'Siswa aktif','stat_teachers'=>'Guru / pendidik','stat_alumni'=>'Alumni'] as $statKey=>$statLabel): ?><div class="col"><label class="form-label" for="<?= e($statKey) ?>"><?= e($statLabel) ?> · opsional</label><input class="form-control" id="<?= e($statKey) ?>" name="<?= e($statKey) ?>" value="<?= e($_POST[$statKey] ?? $settings[$statKey] ?? '') ?>" inputmode="numeric" placeholder="Kosongkan jika belum ada data"></div><?php endforeach; ?></div>
            <button class="btn btn-primary" type="submit">Simpan profil</button></form></div></div>
        <div class="col-lg-6"><div class="content-card h-100"><h2 class="h5 fw-bold">Tahun ajaran</h2><form method="post" class="mb-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="year"><div class="mb-2"><label class="form-label">Nama (contoh: 2026/2027)</label><input class="form-control" name="name" required></div><div class="row"><div class="col"><label class="form-label">Mulai</label><input class="form-control" type="date" name="starts_on"></div><div class="col"><label class="form-label">Selesai</label><input class="form-control" type="date" name="ends_on"></div></div><div class="form-check my-2"><input class="form-check-input" type="checkbox" name="is_active" id="activeYear"><label class="form-check-label" for="activeYear">Jadikan tahun aktif</label></div><button class="btn btn-primary">Tambah tahun</button></form>
            <div class="small text-secondary"><?php foreach($years as $item): ?><span class="badge text-bg-light me-1"><?= e($item['name']) ?><?= $item['is_active']?' · aktif':'' ?></span><?php endforeach; ?></div></div></div>
        <div class="col-lg-6"><div class="content-card"><h2 class="h5 fw-bold">Gelombang pendaftaran</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="wave"><div class="mb-2"><label class="form-label">Tahun ajaran</label><select class="form-select" name="academic_year_id" required><option value="">Pilih</option><?php foreach($years as $item): ?><option value="<?= e($item['id']) ?>"><?= e($item['name']) ?></option><?php endforeach; ?></select></div><div class="mb-2"><label class="form-label">Nama gelombang</label><input class="form-control" name="name" required></div><div class="row"><div class="col"><label class="form-label">Mulai</label><input class="form-control" type="date" name="starts_on"></div><div class="col"><label class="form-label">Selesai</label><input class="form-control" type="date" name="ends_on"></div></div><div class="form-check my-2"><input class="form-check-input" type="checkbox" name="is_open" id="openWave"><label class="form-check-label" for="openWave">Buka pendaftaran</label></div><button class="btn btn-primary">Tambah gelombang</button></form><hr><div class="small text-secondary"><?php foreach($waves as $item): ?><span class="badge text-bg-light me-1"><?= e($item['year_name'].' · '.$item['name']) ?><?= $item['is_open']?' · dibuka':'' ?></span><?php endforeach; ?></div></div></div>
        <div class="col-lg-6"><div class="content-card"><h2 class="h5 fw-bold">Jalur pendaftaran</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="path"><div class="mb-2"><label class="form-label">Nama jalur</label><input class="form-control" name="name" required></div><div class="mb-2"><label class="form-label">Deskripsi</label><textarea class="form-control" name="description"></textarea></div><div class="mb-2"><label class="form-label">Biaya (Rp)</label><input class="form-control" type="number" min="0" step="1000" name="fee" value="0" required></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" id="activePath" checked><label class="form-check-label" for="activePath">Aktif</label></div><button class="btn btn-primary">Tambah jalur</button></form><hr><?php foreach($paths as $item): ?><span class="badge text-bg-light me-1"><?= e($item['name']) ?> · Rp <?= number_format((float)$item['fee'],0,',','.') ?><?= !$item['is_active']?' · nonaktif':'' ?></span><?php endforeach; ?></div></div>
        <div class="col-lg-6"><div class="content-card"><h2 class="h5 fw-bold">Jurusan / program studi</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="major"><div class="mb-2"><label class="form-label">Jenjang SMA / SMK</label><select class="form-select" name="education_level_id" required><option value="">Pilih jenjang</option><?php foreach($levels as $level): if (!in_array($level['code'],['SMA','SMK'],true)) continue; ?><option value="<?= e($level['id']) ?>"><?= e($level['code'].' · '.$level['name']) ?></option><?php endforeach; ?></select></div><div class="mb-2"><label class="form-label">Nama program / kompetensi keahlian</label><input class="form-control" name="name" required></div><div class="mb-2"><label class="form-label">Kode (opsional)</label><input class="form-control" name="code"></div><div class="mb-2"><label class="form-label">Deskripsi</label><textarea class="form-control" name="description"></textarea></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" id="activeMajor" checked><label class="form-check-label" for="activeMajor">Aktif</label></div><button class="btn btn-primary">Tambah program</button></form><hr><?php foreach($majors as $item): ?><span class="badge text-bg-light me-1"><?= e(($item['level_code']??'Umum').' · '.$item['name']) ?><?= !$item['is_active']?' · nonaktif':'' ?></span><?php endforeach; ?></div></div>
        <div class="col-lg-6"><div class="content-card"><h2 class="h5 fw-bold">Pengumuman</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="announcement"><div class="mb-2"><label class="form-label">Judul</label><input class="form-control" name="title" required maxlength="180"></div><div class="mb-2"><label class="form-label">Isi pengumuman</label><textarea class="form-control" name="body" rows="3" required></textarea></div><div class="mb-2"><label class="form-label">Waktu publikasi (opsional)</label><input class="form-control" type="datetime-local" name="publish_at"></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_published" id="publish" checked><label class="form-check-label" for="publish">Publikasikan</label></div><button class="btn btn-primary">Tambah pengumuman</button></form></div></div>
        <div class="col-lg-6"><div class="content-card"><h2 class="h5 fw-bold">Akun admin & panitia</h2><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="account"><div class="mb-2"><label class="form-label">Nama</label><input class="form-control" name="name" required></div><div class="mb-2"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div><div class="mb-2"><label class="form-label">Kata sandi sementara</label><input class="form-control" type="password" name="password" minlength="10" required></div><div class="mb-2"><label class="form-label">Peran</label><select class="form-select" name="role"><option value="committee">Panitia</option><option value="admin">Admin</option></select></div><button class="btn btn-primary">Tambah akun</button></form><hr><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Nama</th><th>Email</th><th>Peran</th></tr></thead><tbody><?php foreach($staff as $item): ?><tr><td><?= e($item['name']) ?></td><td><?= e($item['email']) ?></td><td><?= e($item['role']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
    </div>
    <section class="mt-4"><div class="section-heading mb-3"><p class="eyebrow mb-1">Jangkau semua tingkat</p><h2 class="h4">Konfigurasi jenjang pendidikan</h2><p class="small">Aktifkan hanya jenjang yang benar-benar tersedia di sekolah. Isi fasilitas dan info biaya agar tampil di beranda dan formulir.</p></div><div class="row g-3">
        <?php foreach($levels as $level): ?><div class="col-lg-6"><div class="content-card"><div class="d-flex justify-content-between align-items-start mb-3"><div><span class="eyebrow"><?= e($level['code']) ?></span><h3 class="h5 fw-bold mb-0"><?= e($level['name']) ?></h3></div><span class="dashboard-status"><?= $level['is_active']?'Pendaftaran aktif':'Belum dibuka' ?></span></div><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="level_profile"><input type="hidden" name="level_id" value="<?= e($level['id']) ?>"><div class="row g-2">
            <div class="col-md-6"><label class="form-label">Nama jenjang</label><input class="form-control" name="level_name" value="<?= e($level['name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Asal sekolah sebelumnya</label><input class="form-control" name="prior_school_label" value="<?= e($level['prior_school_label']) ?>" required></div>
            <div class="col-12"><label class="form-label">Label program / kelas</label><input class="form-control" name="program_label" value="<?= e($level['program_label']) ?>" required></div>
            <div class="col-12"><label class="form-label">Pilihan kelas (pisahkan dengan koma) · SD/SMP</label><input class="form-control" name="class_info" value="<?= e($level['class_info'] ?? '') ?>" placeholder="Kelas 1, Kelas 2, Kelas 3"><div class="form-text">Untuk jenjang SMA/SMK, tambahkan program keahlian melalui formulir di atas.</div></div>
            <div class="col-md-6"><label class="form-label">Fasilitas jenjang</label><textarea class="form-control" name="facilities" rows="2"><?= e($level['facilities'] ?? '') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Informasi biaya / kelas</label><textarea class="form-control" name="tuition_info" rows="2"><?= e($level['tuition_info'] ?? '') ?></textarea></div>
            <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="levelActive<?= e($level['id']) ?>" <?= $level['is_active']?'checked':'' ?>><label class="form-check-label fw-bold" for="levelActive<?= e($level['id']) ?>">Tampilkan jenjang dan buka pendaftaran</label></div><button class="btn btn-primary">Simpan konfigurasi</button></div>
        </div></form></div></div><?php endforeach; ?>
    </div></section>
    <div class="content-card mt-4"><h2 class="h5 fw-bold">Aktifkan / nonaktifkan master data</h2><p class="small text-secondary">Menonaktifkan item tidak menghapus riwayat pendaftaran yang sudah ada.</p><div class="row g-4">
        <?php foreach([['year',$years,'name','is_active','Tahun ajaran'],['wave',$waves,'name','is_open','Gelombang'],['path',$paths,'name','is_active','Jalur'],['major',$majors,'name','is_active','Program studi'],['staff',$staff,'email','is_active','Admin & panitia'],['announcement',$announcements,'title','is_published','Pengumuman']] as [$type,$items,$labelKey,$stateKey,$heading]): ?>
        <div class="col-lg-6"><h3 class="h6 fw-bold"><?= e($heading) ?></h3><?php if (!$items): ?><p class="small text-secondary">Belum ada data.</p><?php endif; ?><?php foreach($items as $item): ?><div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2"><div><?= e($item[$labelKey]) ?><?php if($type==='staff'): ?><div class="small text-secondary"><?= e($item['role']) ?></div><?php endif; ?></div><form method="post" action="<?= e(base_url('admin/manage_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><input type="hidden" name="enabled" value="<?= $item[$stateKey]?'0':'1' ?>"><button class="btn btn-sm <?= $item[$stateKey]?'btn-outline-danger':'btn-outline-success' ?>"><?= $item[$stateKey]?'Nonaktifkan':'Aktifkan' ?></button></form></div><?php endforeach; ?></div>
        <?php endforeach; ?>
    </div></div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
