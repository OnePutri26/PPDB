<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role(['student']);

$stmt = $pdo->prepare(
    'SELECT s.*, p.father_nik, p.mother_nik, p.father_name, p.mother_name, p.father_occupation,
        p.mother_occupation, p.education AS parent_education, p.income, p.whatsapp AS parent_whatsapp,
        p.address AS parent_address, ps.name AS previous_school_name, ps.npsn AS previous_school_npsn,
        ps.address AS previous_school_address, ps.graduation_year, ps.certificate_number, ps.report_score,
        r.id AS registration_id, r.biodata_status, r.academic_year_id, r.wave_id, r.path_id, r.major_id,
        r.second_major_id, r.education_level_id, r.class_choice, ay.is_active AS registration_year_active
     FROM students s
     LEFT JOIN parents p ON p.student_id=s.id
     LEFT JOIN previous_schools ps ON ps.student_id=s.id
     LEFT JOIN registrations r ON r.id=(SELECT MAX(r2.id) FROM registrations r2 WHERE r2.student_id=s.id)
     LEFT JOIN academic_years ay ON ay.id=r.academic_year_id
     WHERE s.user_id=?'
);
$stmt->execute([$user['id']]);
$data = $stmt->fetch();
if (!$data) {
    http_response_code(404);
    exit('Data calon siswa tidak ditemukan.');
}

$isNewApplication = !$data['registration_id'] || !(bool) $data['registration_year_active'];
if ($data['registration_id'] && !$isNewApplication && !in_array($data['biodata_status'], ['draft','needs_revision'], true)) {
    set_flash('Data yang sudah dikirim hanya dapat diedit jika panitia meminta perbaikan.','warning');
    redirect(base_url('student/dashboard.php'));
}

$levels = $pdo->query("SELECT id,code,name,prior_school_label,program_label,class_info,facilities,tuition_info FROM education_levels WHERE is_active=1 ORDER BY FIELD(code,'SD','SMP','SMA','SMK')")->fetchAll();
$years = $pdo->query('SELECT id,name FROM academic_years WHERE is_active=1 ORDER BY name DESC')->fetchAll();
$waves = $pdo->query('SELECT id,name,academic_year_id FROM registration_waves WHERE is_open=1 AND (starts_on IS NULL OR starts_on<=CURDATE()) AND (ends_on IS NULL OR ends_on>=CURDATE()) ORDER BY starts_on,name')->fetchAll();
$paths = $pdo->query('SELECT id,name,fee FROM registration_paths WHERE is_active=1 ORDER BY name')->fetchAll();
$levelOptions = [];
foreach ($levels as $level) {
    $classOptions = array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', (string) $level['class_info']) ?: [])));
    $majorQuery = $pdo->prepare('SELECT id,name,description FROM majors WHERE is_active=1 AND (education_level_id=? OR education_level_id IS NULL) ORDER BY name');
    $majorQuery->execute([$level['id']]);
    $levelOptions[$level['id']] = $level + [
        'class_options' => $classOptions,
        'majors' => $majorQuery->fetchAll(),
    ];
}
$docTypes = [
    'family_card' => 'Kartu Keluarga',
    'birth_certificate' => 'Akta kelahiran',
    'parent_id' => 'KTP orang tua / wali',
    'photo' => 'Pas foto terbaru',
    'diploma' => 'Ijazah / Surat Keterangan Lulus',
    'report' => 'Rapor',
    'achievement' => 'Sertifikat prestasi',
];
$existingDocs = [];
if ($data['registration_id'] && !$isNewApplication) {
    $docQuery = $pdo->prepare('SELECT document_type,original_name FROM registration_documents WHERE registration_id=?');
    $docQuery->execute([$data['registration_id']]);
    $existingDocs = $docQuery->fetchAll(PDO::FETCH_KEY_PAIR);
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fields = [
        'education_level_id','academic_year_id','wave_id','path_id','class_choice','major_id','second_major_id',
        'nik','nisn','full_name','gender','birth_place','birth_date','religion','address','rt','rw','village','district','city','province','postal_code','whatsapp',
        'father_nik','mother_nik','father_name','mother_name','father_occupation','mother_occupation','parent_education','income','parent_whatsapp','parent_address',
        'previous_school_name','previous_school_npsn','previous_school_address','graduation_year','certificate_number','report_score',
    ];
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $level = $levelOptions[$values['education_level_id']] ?? null;
    if (($_POST['declaration'] ?? '') !== '1') {
        $error = 'Konfirmasikan bahwa data pendaftaran sudah diperiksa.';
    } elseif (!$level) {
        $error = 'Pilih jenjang pendidikan yang masih dibuka oleh sekolah.';
    }
    $requiredFields = [
        'education_level_id','academic_year_id','wave_id','path_id','nik','nisn','full_name','gender',
        'birth_place','birth_date','religion','address','village','district','city','province','whatsapp',
        'father_name','mother_name','parent_whatsapp','parent_address',
    ];
    if ($level && $level['code'] !== 'SD') {
        $requiredFields = [...$requiredFields,'previous_school_name','graduation_year'];
    }
    foreach ($requiredFields as $requiredField) {
        if ($values[$requiredField] === '') {
            $error = 'Lengkapi semua kolom wajib pada formulir sebelum mengirim.';
            break;
        }
    }
    foreach (['nik'=>'NIK siswa','nisn'=>'NISN','father_nik'=>'NIK ayah','mother_nik'=>'NIK ibu'] as $field=>$label) {
        if ($values[$field] !== '' && !preg_match($field === 'nisn' ? '/^\d{10}$/' : '/^\d{16}$/',$values[$field])) {
            $error = $field === 'nisn' ? 'NISN harus terdiri dari 10 digit.' : $label . ' harus terdiri dari 16 digit.';
            break;
        }
    }
    if ($error === '' && !in_array($values['gender'],['L','P'],true)) {
        $error = 'Pilih jenis kelamin yang valid.';
    }
    if ($error === '' && (!DateTime::createFromFormat('!Y-m-d',$values['birth_date']) || DateTime::createFromFormat('!Y-m-d',$values['birth_date'])->format('Y-m-d') !== $values['birth_date'])) {
        $error = 'Tanggal lahir tidak valid.';
    }
    if ($error === '' && $values['graduation_year'] !== '' && (!preg_match('/^\d{4}$/',$values['graduation_year']) || (int)$values['graduation_year'] < 1901 || (int)$values['graduation_year'] > (int)date('Y')+1)) {
        $error = 'Tahun lulus tidak valid.';
    }
    if ($error === '' && $values['report_score'] !== '' && (!is_numeric($values['report_score']) || (float)$values['report_score'] < 0 || (float)$values['report_score'] > 100)) {
        $error = 'Nilai rapor harus berada pada rentang 0–100.';
    }

    $year = $wave = $path = null;
    $paymentStatus = 'unpaid';
    if ($error === '') {
        $yearStmt = $pdo->prepare('SELECT id FROM academic_years WHERE id=? AND is_active=1');
        $yearStmt->execute([$values['academic_year_id']]);
        $year = $yearStmt->fetch();
        $waveStmt = $pdo->prepare('SELECT id FROM registration_waves WHERE id=? AND academic_year_id=? AND is_open=1 AND (starts_on IS NULL OR starts_on<=CURDATE()) AND (ends_on IS NULL OR ends_on>=CURDATE())');
        $waveStmt->execute([$values['wave_id'],$values['academic_year_id']]);
        $wave = $waveStmt->fetch();
        $pathStmt = $pdo->prepare('SELECT id,fee FROM registration_paths WHERE id=? AND is_active=1');
        $pathStmt->execute([$values['path_id']]);
        $path = $pathStmt->fetch();
        if (!$year || !$wave || !$path) {
            $error = 'Tahun ajaran, gelombang, atau jalur pendaftaran sudah tidak tersedia.';
        }
        if ($path && (float)$path['fee'] <= 0) {
            $paymentStatus = 'not_required';
        } elseif ($path && !$isNewApplication && (int)$values['path_id'] === (int)$data['path_id']) {
            $paymentCheck = $pdo->prepare('SELECT amount,status FROM payments WHERE registration_id=?');
            $paymentCheck->execute([$data['registration_id']]);
            $previousPayment = $paymentCheck->fetch();
            if ($previousPayment && $previousPayment['status'] === 'verified' && abs((float)$previousPayment['amount']-(float)$path['fee']) < .005) {
                $paymentStatus = 'verified';
            }
        }

        $programRequired = $level['code'] === 'SMK';
        $supportsPrograms = in_array($level['code'],['SMA','SMK'],true) && $level['majors'];
        if ($programRequired && $values['major_id'] === '') {
            $error = 'Pilih kompetensi keahlian utama untuk jenjang SMK.';
        } elseif ($values['major_id'] !== '' && !$supportsPrograms) {
            $error = 'Program yang dipilih tidak sesuai jenjang pendaftaran.';
        } elseif ($values['major_id'] !== '' && !array_filter($level['majors'],static fn(array $major): bool => (string)$major['id'] === $values['major_id'])) {
            $error = 'Program utama tidak tersedia untuk jenjang yang dipilih.';
        } elseif ($values['second_major_id'] !== '' && ($level['code'] !== 'SMK' || $values['second_major_id'] === $values['major_id'] || !array_filter($level['majors'],static fn(array $major): bool => (string)$major['id'] === $values['second_major_id']))) {
            $error = 'Pilihan kompetensi kedua tidak valid.';
        } elseif ($values['class_choice'] !== '' && $level['class_options'] && !in_array($values['class_choice'],$level['class_options'],true)) {
            $error = 'Pilihan kelas tidak tersedia pada jenjang ini.';
        }
    }

    $uploaded = [];
    if ($error === '') {
        try {
            $uploadDirectory = __DIR__ . '/../uploads/private';
            foreach ($docTypes as $type=>$label) {
                $fileInput = $_FILES['documents'] ?? [];
                $documentFile = [];
                foreach (['name','type','tmp_name','error','size'] as $property) {
                    $documentFile[$property] = $fileInput[$property][$type] ?? ($property === 'error' ? UPLOAD_ERR_NO_FILE : '');
                }
                $file = upload_file($documentFile,['application/pdf','image/jpeg','image/png'],$uploadDirectory);
                if ($file !== null) {
                    $uploaded[$type] = $file;
                }
            }
            $requiredDocuments = ['family_card','birth_certificate','parent_id','photo'];
            if ($level['code'] !== 'SD') {
                $requiredDocuments = [...$requiredDocuments,'diploma','report'];
            }
            foreach ($requiredDocuments as $requiredDocument) {
                if (!isset($uploaded[$requiredDocument]) && !isset($existingDocs[$requiredDocument])) {
                    throw new RuntimeException('Dokumen wajib belum lengkap. Unggah: ' . $docTypes[$requiredDocument] . '.');
                }
            }

            $pdo->beginTransaction();
            $studentUpdate = $pdo->prepare('UPDATE students SET nik=?,nisn=?,full_name=?,gender=?,birth_place=?,birth_date=?,religion=?,address=?,rt=?,rw=?,village=?,district=?,city=?,province=?,postal_code=?,whatsapp=? WHERE id=?');
            $studentUpdate->execute([$values['nik'],$values['nisn'],$values['full_name'],$values['gender'],$values['birth_place'],$values['birth_date'],$values['religion'],$values['address'],$values['rt']?:null,$values['rw']?:null,$values['village'],$values['district'],$values['city'],$values['province'],$values['postal_code']?:null,$values['whatsapp'],$data['id']]);
            $pdo->prepare('UPDATE users SET name=?,phone=? WHERE id=?')->execute([$values['full_name'],$values['whatsapp'],$user['id']]);

            $parentSql = 'INSERT INTO parents (student_id,father_nik,mother_nik,father_name,mother_name,father_occupation,mother_occupation,education,income,whatsapp,address) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE father_nik=VALUES(father_nik),mother_nik=VALUES(mother_nik),father_name=VALUES(father_name),mother_name=VALUES(mother_name),father_occupation=VALUES(father_occupation),mother_occupation=VALUES(mother_occupation),education=VALUES(education),income=VALUES(income),whatsapp=VALUES(whatsapp),address=VALUES(address)';
            $pdo->prepare($parentSql)->execute([$data['id'],$values['father_nik']?:null,$values['mother_nik']?:null,$values['father_name'],$values['mother_name'],$values['father_occupation']?:null,$values['mother_occupation']?:null,$values['parent_education']?:null,$values['income']?:null,$values['parent_whatsapp'],$values['parent_address']]);

            $schoolSql = 'INSERT INTO previous_schools (student_id,name,npsn,address,graduation_year,certificate_number,report_score) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),npsn=VALUES(npsn),address=VALUES(address),graduation_year=VALUES(graduation_year),certificate_number=VALUES(certificate_number),report_score=VALUES(report_score)';
            $pdo->prepare($schoolSql)->execute([$data['id'],$values['previous_school_name']?:null,$values['previous_school_npsn']?:null,$values['previous_school_address']?:null,$values['graduation_year']?:null,$values['certificate_number']?:null,$values['report_score']===''?null:$values['report_score']]);

            if (!$isNewApplication) {
                $registrationId = (int)$data['registration_id'];
                $updateSql = "UPDATE registrations SET education_level_id=?,academic_year_id=?,wave_id=?,path_id=?,class_choice=?,major_id=?,second_major_id=?,biodata_status='submitted',payment_status=?,selection_status='pending',submitted_at=NOW() WHERE id=?";
                $pdo->prepare($updateSql)->execute([$level['id'],$values['academic_year_id'],$values['wave_id'],$values['path_id'],$values['class_choice']?:null,$values['major_id']?:null,$values['second_major_id']?:null,$paymentStatus,$registrationId]);
                $pdo->prepare('DELETE FROM selection_results WHERE registration_id=?')->execute([$registrationId]);
            } else {
                $number = 'PPDB-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $insertSql = "INSERT INTO registrations (student_id,education_level_id,academic_year_id,wave_id,path_id,class_choice,major_id,second_major_id,registration_number,biodata_status,payment_status,submitted_at) VALUES (?,?,?,?,?,?,?,?,?,'submitted',?,NOW())";
                $pdo->prepare($insertSql)->execute([$data['id'],$level['id'],$values['academic_year_id'],$values['wave_id'],$values['path_id'],$values['class_choice']?:null,$values['major_id']?:null,$values['second_major_id']?:null,$number,(float)$path['fee']>0?'unpaid':'not_required']);
                $registrationId = (int)$pdo->lastInsertId();
            }

            if (!$isNewApplication && $paymentStatus !== 'verified') {
                $pdo->prepare('UPDATE payments SET amount=?,status=?,proof_file=NULL,method=NULL,reference_number=NULL,verified_by=NULL,verified_at=NULL WHERE registration_id=?')->execute([$path['fee'],$paymentStatus,$registrationId]);
            } else {
                $pdo->prepare('INSERT INTO payments (registration_id,amount,status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount),status=VALUES(status)')->execute([$registrationId,$path['fee'],$paymentStatus]);
            }
            foreach ($uploaded as $type=>$file) {
                $docSql = "INSERT INTO registration_documents (registration_id,document_type,original_name,stored_name,mime_type,file_size,status) VALUES (?,?,?,?,?,?,'pending') ON DUPLICATE KEY UPDATE original_name=VALUES(original_name),stored_name=VALUES(stored_name),mime_type=VALUES(mime_type),file_size=VALUES(file_size),status='pending',reviewer_note=NULL";
                $pdo->prepare($docSql)->execute([$registrationId,$type,$file['original_name'],$file['stored_name'],$file['mime_type'],$file['file_size']]);
            }
            log_activity($pdo,(int)$user['id'],'registration_submitted',$level['code']);
            $pdo->commit();
            unset($_SESSION['selected_level_id']);
            set_flash('Formulir pendaftaran berhasil dikirim. Simpan nomor pendaftaran dari dashboard Anda.');
            redirect(base_url('student/dashboard.php'));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($uploaded as $file) {
                $storedPath = __DIR__ . '/../uploads/private/' . $file['stored_name'];
                if (is_file($storedPath)) {
                    unlink($storedPath);
                }
            }
            if ($exception instanceof PDOException && $exception->getCode()==='23000') {
                $error = 'NIK, NISN, atau nomor identitas sudah digunakan. Periksa kembali data Anda.';
            } elseif ($exception instanceof RuntimeException) {
                $error = $exception->getMessage();
            } else {
                error_log('Application submission failed: ' . $exception->getMessage());
                $error = 'Pendaftaran belum berhasil disimpan. Periksa kembali data lalu coba lagi.';
            }
        }
    }
}

$posted = $_SERVER['REQUEST_METHOD']==='POST' ? array_map(static fn($v)=>is_scalar($v)?(string)$v:'',$_POST) : [];
$value = static fn(string $key): string => (string)($posted[$key] ?? $data[$key] ?? '');
$initialLevel = (string)($_SESSION['selected_level_id'] ?? $data['education_level_id'] ?? '');
$selectedLevel = $levelOptions[$value('education_level_id') ?: $initialLevel] ?? null;
$selectedLevelCode = $selectedLevel['code'] ?? '';
$selectedPrograms = $selectedLevel['majors'] ?? [];
$classOptions = $selectedLevel['class_options'] ?? [];
$schoolRequired = $selectedLevelCode !== 'SD';
$showPrograms = in_array($selectedLevelCode,['SMA','SMK'],true) && $selectedPrograms;
$requiredDocumentTypes = $selectedLevelCode === 'SD'
    ? ['family_card','birth_certificate','parent_id','photo']
    : ['family_card','birth_certificate','parent_id','photo','diploma','report'];
$pageTitle = 'Formulir pendaftaran';
require __DIR__ . '/../includes/header.php';
?>
<section class="section-space"><div class="container registration-layout">
    <div class="registration-heading"><a href="<?= e(base_url('student/dashboard.php')) ?>" class="small fw-bold text-decoration-none"><i class="fa-solid fa-arrow-left me-2"></i>Kembali ke dashboard</a><p class="eyebrow mt-4 mb-2">Pendaftaran online · <?= e($user['name']) ?></p><h1 class="h2 fw-bold">Yuk, mulai perjalananmu.</h1><p class="text-secondary mb-0">Isi bertahap, simpan dokumenmu, dan pantau prosesnya di dashboard.</p></div>
    <?php if ($error): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($error) ?></div><?php endif; ?>
    <?php if (!$years || !$waves || !$paths || !$levels): ?><div class="alert alert-warning">Pendaftaran belum dapat dikirim. Panitia perlu membuka jenjang, tahun ajaran, gelombang, dan jalur terlebih dahulu.</div><?php endif; ?>
    <div class="registration-progress" aria-label="Tahapan formulir">
        <?php foreach(['Jenjang','Jadwal','Biodata','Orang tua','Sekolah asal','Pilihan','Dokumen','Review','Kirim'] as $index=>$label): ?><button class="step-dot" type="button" data-step-indicator="<?= $index ?>" aria-label="Langkah <?= $index+1 ?>: <?= e($label) ?>"><b><?= $index+1 ?></b><?= e($label) ?></button><?php endforeach; ?>
    </div>
    <div class="content-card">
        <form method="post" enctype="multipart/form-data" data-multistep novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <script type="application/json" id="levelConfig"><?= json_encode(array_values(array_map(static fn(array $level): array => ['id'=>(string)$level['id'],'code'=>$level['code'],'name'=>$level['name'],'priorSchool'=>$level['prior_school_label'],'programLabel'=>$level['program_label'],'classes'=>$level['class_options'],'majors'=>array_map(static fn(array $major): array => ['id'=>(string)$major['id'],'name'=>$major['name']],$level['majors']),'facilities'=>$level['facilities'],'tuition'=>$level['tuition_info']],array_values($levelOptions))), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
            <section class="step-card" data-step>
                <p class="eyebrow">01 · Tentukan arah</p><h2 class="step-card-title">Kamu mau daftar jenjang apa?</h2>
                <div class="row g-3">
                    <?php foreach($levels as $level): $programsReady=$level['code']!=='SMK'||count($levelOptions[$level['id']]['majors'])>0; ?><div class="col-sm-6"><label class="level-card p-3 <?= $value('education_level_id')===(string)$level['id'] || (!$value('education_level_id') && $initialLevel===(string)$level['id'])?'border-primary':'' ?>" for="level_<?= e($level['id']) ?>"><div class="d-flex gap-3"><input class="form-check-input mt-2" type="radio" id="level_<?= e($level['id']) ?>" name="education_level_id" value="<?= e($level['id']) ?>" data-level-code="<?= e($level['code']) ?>" <?= $value('education_level_id')===(string)$level['id'] || (!$value('education_level_id') && $initialLevel===(string)$level['id'])?'checked':'' ?> <?= !$programsReady?'disabled':'' ?> required><span><span class="level-code"><?= e($level['code']) ?></span><span class="d-block fw-bold"><?= e($level['name']) ?></span><small class="text-secondary"><?= e($programsReady?$level['prior_school_label']:'Program kompetensi belum disiapkan sekolah.') ?></small></span></div></label></div><?php endforeach; ?>
                    <?php if(!$levels): ?><div class="col-12"><div class="empty-state">Belum ada jenjang yang dibuka. Hubungi panitia sekolah.</div></div><?php endif; ?>
                </div>
            </section>

            <section class="step-card" data-step hidden>
                <p class="eyebrow">02 · Pilih waktu</p><h2 class="step-card-title">Tentukan tahun ajaran dan gelombang.</h2><div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="academic_year_id">Tahun ajaran *</label><select class="form-select" id="academic_year_id" name="academic_year_id" required><option value="">Pilih tahun ajaran</option><?php foreach($years as $option): ?><option value="<?= e($option['id']) ?>" <?= $value('academic_year_id')===(string)$option['id']?'selected':'' ?>><?= e($option['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label" for="wave_id">Gelombang *</label><select class="form-select" id="wave_id" name="wave_id" required><option value="">Pilih gelombang</option><?php foreach($waves as $option): ?><option value="<?= e($option['id']) ?>" data-year="<?= e($option['academic_year_id']) ?>" <?= $value('wave_id')===(string)$option['id']?'selected':'' ?>><?= e($option['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><div class="alert alert-light mb-0"><i class="fa-solid fa-circle-info text-primary me-2"></i>Pastikan gelombang masih dibuka sebelum mengirim formulir.</div></div>
                </div>
            </section>

            <section class="step-card" data-step hidden>
                <p class="eyebrow">03 · Kenalan dulu</p><h2 class="step-card-title">Biodata calon siswa.</h2><div class="row g-3">
                    <?php foreach(['nik'=>'NIK (16 digit)','nisn'=>'NISN (10 digit)','full_name'=>'Nama lengkap','birth_place'=>'Tempat lahir','birth_date'=>'Tanggal lahir','religion'=>'Agama','whatsapp'=>'Nomor WhatsApp','address'=>'Alamat lengkap','rt'=>'RT','rw'=>'RW','village'=>'Kelurahan / desa','district'=>'Kecamatan','city'=>'Kabupaten / kota','province'=>'Provinsi','postal_code'=>'Kode pos'] as $key=>$label): ?>
                        <div class="<?= $key==='address'?'col-12':'col-md-6' ?>"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?><?= in_array($key,['rt','rw','postal_code'],true)?'':' *' ?></label>
                        <?php if($key==='religion'): ?><select class="form-select" id="<?= e($key) ?>" name="<?= e($key) ?>" required><option value="">Pilih agama</option><?php foreach(['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu','Lainnya'] as $religion): ?><option <?= $value($key)===$religion?'selected':'' ?>><?= e($religion) ?></option><?php endforeach; ?></select>
                        <?php else: ?><input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value($key)) ?>" <?= $key==='nik'?'inputmode="numeric" minlength="16" maxlength="16" pattern="[0-9]{16}"':'' ?> <?= $key==='nisn'?'inputmode="numeric" minlength="10" maxlength="10" pattern="[0-9]{10}"':'' ?> <?= $key==='birth_date'?'type="date"':'' ?> <?= in_array($key,['rt','rw','postal_code'],true)?'':'required' ?>><?php endif; ?></div>
                    <?php endforeach; ?>
                    <div class="col-md-6"><label class="form-label" for="gender">Jenis kelamin *</label><select class="form-select" id="gender" name="gender" required><option value="">Pilih</option><option value="L" <?= $value('gender')==='L'?'selected':'' ?>>Laki-laki</option><option value="P" <?= $value('gender')==='P'?'selected':'' ?>>Perempuan</option></select></div>
                </div>
            </section>

            <section class="step-card" data-step hidden>
                <p class="eyebrow">04 · Keluarga</p><h2 class="step-card-title">Data orang tua atau wali.</h2><div class="row g-3">
                    <?php foreach(['father_name'=>'Nama ayah','father_nik'=>'NIK ayah (opsional)','father_occupation'=>'Pekerjaan ayah','mother_name'=>'Nama ibu','mother_nik'=>'NIK ibu (opsional)','mother_occupation'=>'Pekerjaan ibu','parent_education'=>'Pendidikan terakhir','income'=>'Penghasilan orang tua','parent_whatsapp'=>'Nomor WhatsApp orang tua / wali','parent_address'=>'Alamat orang tua / wali'] as $key=>$label): ?>
                    <div class="<?= $key==='parent_address'?'col-12':'col-md-6' ?>"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?><?= in_array($key,['father_nik','mother_nik','father_occupation','mother_occupation','parent_education','income'],true)?'':' *' ?></label><input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value($key)) ?>" <?= in_array($key,['father_nik','mother_nik'],true)?'inputmode="numeric" minlength="16" maxlength="16" pattern="[0-9]{16}"':'' ?> <?= in_array($key,['father_nik','mother_nik','father_occupation','mother_occupation','parent_education','income'],true)?'':'required' ?>></div><?php endforeach; ?>
                </div>
            </section>

            <section class="step-card" data-step hidden>
                <p class="eyebrow">05 · Sekolah sebelumnya</p><h2 class="step-card-title">Cerita tentang sekolah asalmu.</h2>
                <div class="alert alert-light"><i class="fa-solid fa-school text-primary me-2"></i><span data-prior-label><?= e($selectedLevel['prior_school_label'] ?? 'Asal sekolah') ?></span><?php if(!$schoolRequired): ?> · boleh dikosongkan jika belum pernah bersekolah formal.<?php endif; ?></div>
                <div class="row g-3">
                    <?php foreach(['previous_school_name'=>'Nama sekolah / TK / RA','previous_school_npsn'=>'NPSN sekolah (opsional)','previous_school_address'=>'Alamat sekolah (opsional)','graduation_year'=>'Tahun lulus','certificate_number'=>'Nomor ijazah / SKL (opsional)','report_score'=>'Nilai rapor (opsional)'] as $key=>$label): ?>
                    <div class="<?= $key==='previous_school_address'?'col-12':'col-md-6' ?>"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?><?= in_array($key,['previous_school_name','graduation_year'],true)?' *':'' ?></label><input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value($key)) ?>" <?= $key==='graduation_year'?'inputmode="numeric" minlength="4" maxlength="4"':'' ?> <?= $key==='report_score'?'type="number" min="0" max="100" step="0.01"':'' ?> <?= in_array($key,['previous_school_name','graduation_year'],true)?'required':'' ?>></div><?php endforeach; ?>
                </div>
            </section>

            <section class="step-card" data-step hidden>
                <p class="eyebrow">06 · Pilihanmu</p><h2 class="step-card-title">Pilih jalur dan program belajar.</h2><div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="path_id">Jalur pendaftaran *</label><select class="form-select" id="path_id" name="path_id" required><option value="">Pilih jalur</option><?php foreach($paths as $option): ?><option value="<?= e($option['id']) ?>" <?= $value('path_id')===(string)$option['id']?'selected':'' ?>><?= e($option['name']) ?> · Rp <?= number_format((float)$option['fee'],0,',','.') ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6" data-class-choice <?= $classOptions?'':'hidden' ?>><label class="form-label" for="class_choice">Pilihan kelas</label><select class="form-select" id="class_choice" name="class_choice"><option value="">Pilih kelas (opsional)</option><?php foreach($classOptions as $option): ?><option <?= $value('class_choice')===$option?'selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6" data-primary-major <?= $showPrograms?'':'hidden' ?>><label class="form-label" for="major_id" data-primary-major-label><?= $selectedLevelCode==='SMK'?'Kompetensi keahlian utama *':'Peminatan / program (opsional)' ?></label><select class="form-select" id="major_id" name="major_id" data-required-smk="<?= $selectedLevelCode==='SMK'?'1':'0' ?>"><option value="">Pilih program</option><?php foreach($selectedPrograms as $option): ?><option value="<?= e($option['id']) ?>" <?= $value('major_id')===(string)$option['id']?'selected':'' ?>><?= e($option['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6" data-second-major <?= $selectedLevelCode==='SMK'?'':'hidden' ?>><label class="form-label" for="second_major_id">Pilihan kompetensi kedua (opsional)</label><select class="form-select" id="second_major_id" name="second_major_id"><option value="">Tidak memilih</option><?php foreach($selectedPrograms as $option): ?><option value="<?= e($option['id']) ?>" <?= $value('second_major_id')===(string)$option['id']?'selected':'' ?>><?= e($option['name']) ?></option><?php endforeach; ?></select></div>
                    <?php if($selectedLevel && $selectedLevel['tuition_info']): ?><div class="col-12"><div class="alert alert-light"><i class="fa-solid fa-wallet text-primary me-2"></i><?= nl2br(e($selectedLevel['tuition_info'])) ?></div></div><?php endif; ?>
                    <?php if($selectedLevel && $selectedLevel['facilities']): ?><div class="col-12"><div class="alert alert-light"><i class="fa-solid fa-building text-primary me-2"></i><strong>Fasilitas:</strong> <?= nl2br(e($selectedLevel['facilities'])) ?></div></div><?php endif; ?>
                </div>
            </section>

            <section class="step-card" data-step hidden>
                <p class="eyebrow">07 · Berkas digital</p><h2 class="step-card-title">Upload dokumen yang diperlukan.</h2><p class="text-secondary small">Format PDF, JPG, atau PNG · maksimal 5 MB setiap file. Data hanya dapat dilihat oleh siswa dan panitia yang berwenang.</p><div class="row g-3">
                    <?php foreach($docTypes as $type=>$label): $required=in_array($type,$requiredDocumentTypes,true); ?><div class="col-md-6" data-document-tile><div class="file-tile" data-upload-zone><label class="form-label" for="doc_<?= e($type) ?>" data-document-label><?= e($label) ?><?= $required?' *':' · opsional' ?></label><input class="form-control" type="file" id="doc_<?= e($type) ?>" name="documents[<?= e($type) ?>]" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-level-document data-required-for="<?= $type==='diploma'||$type==='report'?'SMP,SMA,SMK':($type==='achievement'?'':'SD,SMP,SMA,SMK') ?>" data-levels="SD,SMP,SMA,SMK" data-already-uploaded="<?= isset($existingDocs[$type])?'1':'' ?>" <?= $required&&!isset($existingDocs[$type])?'required':'' ?>><div class="file-preview" data-file-preview aria-live="polite"></div><div class="form-text"><?= isset($existingDocs[$type])?'Sudah tersimpan: '.e($existingDocs[$type]).' · pilih berkas baru jika ingin mengganti.':'Tarik berkas ke sini atau pilih dari perangkat · maksimal 5 MB.' ?></div></div></div><?php endforeach; ?>
                    <div class="col-12" data-level-info></div>
                </div>
            </section>

            <section class="step-card" data-step hidden>
                <p class="eyebrow">08 · Cek lagi sebentar</p><h2 class="step-card-title">Review data pendaftaranmu.</h2><p class="text-secondary">Pastikan nama, jenjang, dan pilihan sudah benar sebelum dikirim.</p><div data-review-summary class="row g-3"></div><div class="alert alert-light mt-3 mb-0"><i class="fa-solid fa-lock text-primary me-2"></i>Dengan mengirim formulir, kamu menyatakan data yang diisi benar.</div>
            </section>

            <section class="step-card" data-step hidden>
                <p class="eyebrow">09 · Hampir selesai</p><h2 class="step-card-title">Siap kirim pendaftaranmu?</h2><p class="text-secondary">Kamu dapat memantau verifikasi, pembayaran, dan hasil seleksi dari dashboard.</p>
                <div class="dashboard-hero mb-4"><div class="feature-icon purple mb-3"><i class="fa-solid fa-paper-plane"></i></div><h3 class="h5 fw-bold">Data tersimpan dengan aman</h3><p class="text-secondary mb-0">Panitia akan memeriksa formulir dan dokumen yang kamu kirim.</p></div>
                <label class="d-flex gap-2 align-items-start"><input type="checkbox" class="form-check-input mt-1" name="declaration" value="1" required><span>Saya telah memeriksa data dan menyatakan informasi dalam formulir ini benar.</span></label>
            </section>

            <div class="form-step-actions d-flex justify-content-between align-items-center gap-2 mt-4">
                <button class="btn btn-outline-primary" type="button" data-step-back><i class="fa-solid fa-arrow-left me-2"></i>Kembali</button>
                <span class="small text-secondary" data-step-count>Langkah 1 dari 9</span>
                <div><button class="btn btn-primary px-4" type="button" data-step-next>Lanjut <i class="fa-solid fa-arrow-right ms-2"></i></button><button class="btn btn-primary px-4" type="submit" data-step-submit hidden <?= !$years||!$waves||!$paths||!$levels?'disabled':'' ?>>Kirim pendaftaran <i class="fa-solid fa-paper-plane ms-2"></i></button></div>
            </div>
        </form>
    </div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
