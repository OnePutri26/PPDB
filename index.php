<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$settings = $pdo->query('SELECT setting_key, setting_value FROM school_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$schoolRecord = $pdo->query('SELECT name,address,phone,email,description FROM schools ORDER BY id LIMIT 1')->fetch() ?: [];
$schoolName = $settings['school_name'] ?? $schoolRecord['name'] ?? 'Sekolah Anda';
$schoolDescription = $settings['school_description'] ?? $schoolRecord['description'] ?? 'Ruang belajar yang seru, suportif, dan siap menemani setiap langkah masa depanmu.';
$schoolEmail = $settings['school_email'] ?? $schoolRecord['email'] ?? '';
$schoolPhone = $settings['school_phone'] ?? $schoolRecord['phone'] ?? '';
$whatsappNumber = preg_replace('/\D+/', '',$schoolPhone);
if ($whatsappNumber && str_starts_with($whatsappNumber,'0')) {
    $whatsappNumber = '62' . substr($whatsappNumber,1);
}
$schoolAddress = $settings['school_address'] ?? $schoolRecord['address'] ?? '';
$levels = $pdo->query('SELECT id,code,name,prior_school_label,program_label,class_info,facilities,tuition_info,is_active FROM education_levels ORDER BY FIELD(code,\'SD\',\'SMP\',\'SMA\',\'SMK\')')->fetchAll();
$activeYear = $pdo->query('SELECT name FROM academic_years WHERE is_active=1 ORDER BY starts_on DESC LIMIT 1')->fetchColumn();
$waves = $pdo->query('SELECT w.name,w.starts_on,w.ends_on,y.name AS year_name FROM registration_waves w JOIN academic_years y ON y.id=w.academic_year_id WHERE w.is_open=1 AND y.is_active=1 AND (w.starts_on IS NULL OR w.starts_on<=CURDATE()) AND (w.ends_on IS NULL OR w.ends_on>=CURDATE()) ORDER BY w.starts_on')->fetchAll();
$paths = $pdo->query('SELECT name,description,fee FROM registration_paths WHERE is_active=1 ORDER BY name')->fetchAll();
$programs = $pdo->query('SELECT m.name,m.description,l.code AS level_code FROM majors m LEFT JOIN education_levels l ON l.id=m.education_level_id WHERE m.is_active=1 ORDER BY l.code,m.name')->fetchAll();
$programCounts = [];
foreach ($programs as $program) {
    if ($program['level_code']) {
        $programCounts[$program['level_code']] = ($programCounts[$program['level_code']] ?? 0) + 1;
    }
}
$announcements = $pdo->query('SELECT title,body,publish_at FROM announcements WHERE is_published=1 AND (publish_at IS NULL OR publish_at<=NOW()) ORDER BY COALESCE(publish_at,created_at) DESC LIMIT 3')->fetchAll();
$activeLevels = array_values(array_filter($levels, static fn(array $level): bool => (bool) $level['is_active']));
$stats = [
    ['value' => $settings['stat_students'] ?? '', 'label' => 'siswa aktif'],
    ['value' => $settings['stat_teachers'] ?? '', 'label' => 'pendidik'],
    ['value' => $settings['stat_alumni'] ?? '', 'label' => 'alumni'],
];
$hasStats = count(array_filter($stats, static fn(array $stat): bool => $stat['value'] !== '')) > 0;
$pageTitle = 'PPDB · Penerimaan Peserta Didik Baru';
require __DIR__ . '/includes/header.php';
?>
<section class="hero" id="beranda">
    <div class="container hero-inner">
        <div class="hero-copy">
            <div class="hero-edition"><span class="live-dot"></span> PPDB <?= e($activeYear ?: 'TAHUN AJARAN BARU') ?> <span class="edition-divider">/</span> YOUR NEXT CHAPTER</div>
            <h1>Your future<br>starts <span class="hero-highlight">here<span class="hero-scribble">✳</span></span><span class="hero-period">.</span></h1>
            <div class="hero-bottomline">
                <p class="lead mb-0"><?= e($schoolDescription) ?></p>
                <div class="hero-actions">
                    <a class="btn btn-primary btn-lg" href="<?= e(base_url('auth/register.php')) ?>">Daftar sekarang <i class="fa-solid fa-arrow-up-right-from-square ms-2"></i></a>
                    <a class="btn btn-text" href="#profil">Jelajahi sekolah <i class="fa-solid fa-arrow-down ms-2"></i></a>
                </div>
            </div>
            <div class="hero-note"><i class="fa-solid fa-lock"></i> Aman, transparan, dan bisa dipantau kapan saja.</div>
        </div>
        <div class="hero-visual" aria-label="Ilustrasi tiga siswa sedang belajar bersama">
            <div class="hero-art-label">A PLACE TO<br><strong>BECOME.</strong></div>
            <div class="hero-sun"></div><div class="hero-spark spark-a">✳</div><div class="hero-spark spark-b">✳</div>
            <svg class="student-illustration" viewBox="0 0 560 520" role="img" aria-labelledby="studentArtTitle">
                <title id="studentArtTitle">Tiga siswa merayakan ide dan masa depan</title>
                <ellipse cx="288" cy="450" rx="205" ry="27" fill="#292333" opacity=".11"/>
                <path d="M75 328c-18-95 34-208 155-230 124-23 238 41 259 157 19 105-51 176-156 181-113 6-237-10-258-108Z" fill="#D9F36A"/>
                <path d="M408 107c21-25 34-53 34-53m0 0 5 38 39-12m-44-26 35 0" fill="none" stroke="#24212B" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M94 111c12-17 19-36 19-36m0 0 4 25 25-8m-29-17 23 0" fill="none" stroke="#24212B" stroke-width="4" stroke-linecap="round"/>
                <path d="M130 377c4-53 13-88 38-105 27-19 78-18 105 0 24 16 31 52 34 105l-2 48H126l4-48Z" fill="#7047EB" stroke="#24212B" stroke-width="5"/>
                <path d="M180 268c-8-12-10-26-8-41h66c3 17 1 32-7 43-12 17-39 17-51-2Z" fill="#F4B288" stroke="#24212B" stroke-width="4"/>
                <path d="M172 222c-4-48 12-77 49-80 42-4 58 26 51 71-2 17-8 29-19 37l-7-28-12-18c-24 6-40-5-49-15l-7 30-6 15c-5-3-8-7-10-12Z" fill="#25212C" stroke="#24212B" stroke-width="5" stroke-linejoin="round"/>
                <path d="M191 233c5 4 10 4 14 0m23 0c5 4 10 4 14 0m-30 17c10 9 23 9 33 0" fill="none" stroke="#24212B" stroke-width="4" stroke-linecap="round"/>
                <path d="M164 338c-20 1-37 10-49 27m145-26c17 3 28 12 37 26" fill="none" stroke="#F4B288" stroke-width="17" stroke-linecap="round"/>
                <path d="M206 321c13 11 31 11 44 0" fill="none" stroke="#FFF9F0" stroke-width="5" stroke-linecap="round"/>
                <path d="M274 375c8-53 20-84 47-98 30-15 80-7 102 17 21 24 21 57 16 108l-7 26H269l5-53Z" fill="#FF7547" stroke="#24212B" stroke-width="5"/>
                <path d="M330 277c-9-13-10-26-7-42l64 4c2 18-3 32-13 42-14 14-34 12-44-4Z" fill="#A86648" stroke="#24212B" stroke-width="4"/>
                <path d="M318 225c-1-44 17-68 51-65 40 4 54 34 39 76l-9 19-9-24-9-18c-19 5-39-5-49-18l-7 26-7 17c-6-4-9-8-10-13Z" fill="#24212B" stroke="#24212B" stroke-width="5"/>
                <path d="M338 238c5 4 10 4 14 0m22 2c5 4 10 4 14 0m-31 16c10 8 22 9 32 2" fill="none" stroke="#24212B" stroke-width="4" stroke-linecap="round"/>
                <path d="M310 344c-18 3-32 13-41 28m148-18c17 6 28 17 34 31" fill="none" stroke="#A86648" stroke-width="16" stroke-linecap="round"/>
                <path d="M186 372c-5 24-2 39 11 46 13 7 28-1 35-16 7-14 8-29 3-42-9 13-20 20-34 20-5 0-10-3-15-8Z" fill="#FFF9F0" stroke="#24212B" stroke-width="4"/>
                <path d="M147 421h109m52-1h105" stroke="#24212B" stroke-width="6" stroke-linecap="round"/>
                <path d="M294 115l18 13-18 13-18-13 18-13Z" fill="#FF7547" stroke="#24212B" stroke-width="3"/>
                <path d="M128 183c13-19 35-25 58-19-4 25-18 40-43 44" fill="#83C9F5" stroke="#24212B" stroke-width="4"/>
                <path d="M422 334c16-15 37-19 58-11-8 24-24 36-49 35" fill="#83C9F5" stroke="#24212B" stroke-width="4"/>
                <path d="M204 292c18 10 37 13 58 9" fill="none" stroke="#24212B" stroke-width="4" stroke-linecap="round"/>
            </svg>
            <div class="float-card one"><i class="fa-solid fa-lightbulb"></i>Ideas belong here</div>
            <div class="float-card two"><span>100%</span> room to grow</div>
            <div class="hero-index">01 — 04 <span>START ANYWHERE</span></div>
        </div>
        <div class="hero-footer">
            <span>SCROLL TO EXPLORE <i class="fa-solid fa-arrow-down ms-2"></i></span>
            <?php if ($hasStats): ?><div class="hero-stats"><?php foreach ($stats as $stat): if ($stat['value'] === '') continue; ?><div><strong><?= e($stat['value']) ?></strong><span><?= e($stat['label']) ?></span></div><?php endforeach; ?></div>
            <?php else: ?><span>SD · SMP · SMA · SMK</span><?php endif; ?>
        </div>
    </div>
</section>

<section class="section-space levels-section" id="jenjang">
    <div class="container">
        <div class="section-heading mb-4"><p class="eyebrow mb-2">Pilih perjalananmu</p><h2>Temukan jenjang yang cocok buatmu.</h2><p>Setiap langkah belajar punya ceritanya sendiri. Pilih jenjang yang sesuai untuk melihat informasi pendaftaran.</p></div>
        <div class="row g-3">
            <?php foreach ($levels as $index => $level):
                $available = (bool) $level['is_active'] && ($level['code'] !== 'SMK' || !empty($programCounts['SMK']));
                $cardThemes = ['SD'=>'level-sd','SMP'=>'level-smp','SMA'=>'level-sma','SMK'=>'level-smk'];
                $cardIcons = ['SD'=>'fa-shapes','SMP'=>'fa-compass','SMA'=>'fa-lightbulb','SMK'=>'fa-screwdriver-wrench'];
                $cardDescriptions = ['SD'=>'Small steps. Big, bright possibilities.','SMP'=>'Find your people. Find your thing.','SMA'=>'Turn curiosity into direction.','SMK'=>'Make ideas real. Build what is next.'];
            ?>
                <div class="col-sm-6 col-xl-3"><a class="level-card <?= e($cardThemes[$level['code']] ?? 'level-sd') ?> <?= $available ? '' : 'is-disabled' ?>" href="<?= $available ? e(base_url('auth/register.php?level_id='.$level['id'])) : '#kontak' ?>" <?= $available ? '' : 'aria-label="'.e($level['name']).' — informasi menyusul"' ?>>
                    <div class="level-card-top"><span class="level-number">0<?= $index+1 ?> / 04</span><span class="level-arrow"><i class="fa-solid fa-arrow-up-right-from-square"></i></span></div>
                    <div class="level-art" aria-hidden="true"><i class="fa-solid <?= e($cardIcons[$level['code']] ?? 'fa-shapes') ?>"></i><span class="level-art-orbit"></span><span class="level-art-dot"></span></div>
                    <div class="level-card-copy"><div class="level-code"><?= e($level['code']) ?></div><h3><?= e($level['name']) ?></h3>
                    <p><?= e($cardDescriptions[$level['code']] ?? $level['prior_school_label']) ?></p>
                    <div class="level-prerequisite"><i class="fa-solid fa-turn-up"></i><?= e($level['prior_school_label']) ?></div>
                    <div class="level-status"><?= $available ? 'Pendaftaran tersedia' : ((bool)$level['is_active']?'Program keahlian sedang disiapkan':'Belum dibuka · hubungi sekolah') ?><i class="fa-solid fa-arrow-right"></i></div></div>
                </a></div>
            <?php endforeach; ?>
        </div>
        <?php if (!$levels): ?><div class="empty-state">Informasi jenjang sedang disiapkan oleh sekolah.</div><?php endif; ?>
    </div>
</section>

<section class="section-space" id="profil">
    <div class="container"><div class="row align-items-center g-5">
        <div class="col-lg-6"><div class="feature-icon purple mb-3"><i class="fa-solid fa-compass-drafting"></i></div><p class="eyebrow">Kenalan lebih dekat</p><h2 class="fw-bold display-6" style="letter-spacing:-.055em">Sekolah bukan cuma tempat belajar. Ini tempat kamu jadi diri sendiri.</h2><p class="text-secondary mt-3"><?= e($schoolDescription) ?></p>
            <?php if (!empty($schoolRecord['address'])): ?><p class="text-secondary"><i class="fa-solid fa-location-dot text-primary me-2"></i><?= e($schoolAddress) ?></p><?php endif; ?>
            <a href="#kontak" class="btn btn-outline-primary mt-2">Kenali sekolah kami</a>
        </div>
        <div class="col-lg-6"><div class="row g-3">
            <?php foreach ([
                ['fa-people-group','Komunitas suportif','Belajar dan berkembang bersama teman serta pendidik.','purple'],
                ['fa-lightbulb','Belajar aktif','Eksplorasi ide, kolaborasi, dan pengalaman nyata.','orange'],
                ['fa-seedling','Tumbuh percaya diri','Kenali potensi diri dan siapkan langkah berikutnya.','green'],
                ['fa-compass','Siap masa depan','Bekal karakter dan kompetensi untuk tantangan baru.','blue'],
            ] as [$icon,$title,$text,$color]): ?><div class="col-sm-6"><article class="feature-card"><div class="feature-icon <?= e($color) ?> mb-3"><i class="fa-solid <?= e($icon) ?>"></i></div><h3 class="h6 fw-bold"><?= e($title) ?></h3><p class="text-secondary small mb-0"><?= e($text) ?></p></article></div><?php endforeach; ?>
        </div></div>
    </div></div>
</section>

<section class="section-space levels-section" id="program">
    <div class="container"><div class="section-heading mb-4"><p class="eyebrow">Jalur eksplorasi</p><h2>Belajar sesuai minat dan potensimu.</h2><p>Program dan fasilitas berikut mengikuti informasi yang telah dikonfigurasi sekolah.</p></div>
        <div class="row g-3">
            <?php foreach ($activeLevels as $index => $level): $levelPrograms = array_values(array_filter($programs, static fn(array $program): bool => $program['level_code'] === $level['code'])); ?>
                <div class="col-md-6"><article class="program-card">
                    <div class="d-flex align-items-center gap-3 mb-3"><div class="feature-icon <?= ['blue','purple','orange','green'][$index%4] ?>"><i class="fa-solid <?= ['fa-shapes','fa-compass','fa-lightbulb','fa-screwdriver-wrench'][$index%4] ?>"></i></div><div><span class="eyebrow"><?= e($level['code']) ?></span><h3 class="h5 fw-bold mb-0"><?= e($level['name']) ?></h3></div></div>
                    <?php if ($levelPrograms): ?><ul class="list-unstyled d-grid gap-2 mb-0"><?php foreach($levelPrograms as $program): ?><li class="d-flex gap-2"><i class="fa-solid fa-circle-check text-primary mt-1"></i><span><strong><?= e($program['name']) ?></strong><?php if ($program['description']): ?><small class="d-block text-secondary"><?= e($program['description']) ?></small><?php endif; ?></span></li><?php endforeach; ?></ul>
                    <?php else: ?><p class="text-secondary mb-0"><?= e($level['class_info'] ?: 'Informasi kelas dan program akan diperbarui oleh sekolah.') ?></p><?php endif; ?>
                    <?php if ($level['facilities']): ?><div class="small text-secondary border-top pt-3 mt-3"><i class="fa-solid fa-building me-2"></i><?= nl2br(e($level['facilities'])) ?></div><?php endif; ?>
                </article></div>
            <?php endforeach; ?>
            <?php if (!$activeLevels): ?><div class="col-12"><div class="empty-state">Program pendidikan akan ditampilkan setelah sekolah membuka jenjang pendaftaran.</div></div><?php endif; ?>
        </div>
    </div>
</section>

<?php if ($hasStats): ?><section class="py-4"><div class="container"><div class="row g-3"><?php foreach($stats as $stat): if($stat['value']==='') continue; ?><div class="col-4"><div class="stat-card text-center"><div class="stat-number"><?= e($stat['value']) ?></div><div class="small text-secondary"><?= e($stat['label']) ?></div></div></div><?php endforeach; ?></div></div></section><?php endif; ?>

<section class="section-space" id="informasi">
    <div class="container"><div class="section-heading mb-4"><p class="eyebrow">Informasi PPDB</p><h2>Semua info penting. Nggak pakai ribet.</h2><p>Jadwal, dokumen, dan biaya ditampilkan dari pengaturan resmi panitia.</p></div>
        <div class="row g-3">
            <div class="col-md-6 col-xl-3"><article class="feature-card h-100"><div class="feature-icon mb-3"><i class="fa-solid fa-calendar-days"></i></div><h3 class="h6 fw-bold">Jadwal PPDB</h3><?php if ($waves): ?><ul class="list-unstyled small text-secondary mb-0"><?php foreach($waves as $wave): ?><li class="mb-2"><strong><?= e($wave['name']) ?></strong><br><?= e($wave['year_name']) ?> · <?= e($wave['starts_on'] ?? 'Segera') ?> — <?= e($wave['ends_on'] ?? 'Segera') ?></li><?php endforeach; ?></ul><?php else: ?><p class="small text-secondary mb-0">Jadwal belum diumumkan. Hubungi panitia sekolah.</p><?php endif; ?></article></div>
            <div class="col-md-6 col-xl-3" id="persyaratan"><article class="feature-card h-100"><div class="feature-icon purple mb-3"><i class="fa-solid fa-folder-open"></i></div><h3 class="h6 fw-bold">Persyaratan umum</h3><ul class="list-unstyled small text-secondary d-grid gap-2 mb-0"><?php foreach(['Kartu Keluarga','Akta kelahiran','Identitas orang tua / wali','Pas foto terbaru'] as $item): ?><li><i class="fa-solid fa-check text-success me-2"></i><?= e($item) ?></li><?php endforeach; ?><li><i class="fa-solid fa-circle-info text-primary me-2"></i>Ijazah dan rapor mengikuti jenjang.</li></ul></article></div>
            <div class="col-md-6 col-xl-3" id="biaya"><article class="feature-card h-100"><div class="feature-icon orange mb-3"><i class="fa-solid fa-wallet"></i></div><h3 class="h6 fw-bold">Biaya pendaftaran</h3><?php if($paths): ?><ul class="list-unstyled small mb-0"><?php foreach($paths as $path): ?><li class="d-flex justify-content-between gap-2 py-2 border-bottom"><span><?= e($path['name']) ?></span><strong class="text-primary">Rp <?= number_format((float)$path['fee'],0,',','.') ?></strong></li><?php endforeach; ?></ul><?php else: ?><p class="small text-secondary mb-0">Informasi biaya belum tersedia.</p><?php endif; ?></article></div>
            <div class="col-md-6 col-xl-3"><article class="feature-card h-100"><div class="feature-icon green mb-3"><i class="fa-solid fa-route"></i></div><h3 class="h6 fw-bold">Alur pendaftaran</h3><div class="small text-secondary">Buat akun → lengkapi formulir → unggah berkas → verifikasi → pantau hasil seleksi.</div><a class="small fw-bold d-inline-block mt-3" href="<?= e(base_url('auth/register.php')) ?>">Mulai pendaftaran <i class="fa-solid fa-arrow-right ms-1"></i></a></article></div>
        </div>
    </div>
</section>

<section class="section-space levels-section" id="faq"><div class="container"><div class="row g-5">
    <div class="col-lg-4"><p class="eyebrow">FAQ</p><h2 class="fw-bold">Penasaran soal apa?</h2><p class="text-secondary">Ini jawaban dari pertanyaan yang sering ditanyakan calon siswa dan orang tua.</p><a class="btn btn-outline-primary" href="#kontak">Tanya panitia</a></div>
    <div class="col-lg-8"><div class="accordion" id="ppdbFaq">
        <?php foreach([
            ['faq-1','Bagaimana cara mendaftar?','Buat akun melalui tombol Daftar Sekarang, lalu isi formulir dan unggah dokumen yang diminta.'],
            ['faq-2','Apakah saya dapat melihat status pendaftaran?','Bisa. Setelah masuk, dashboard menampilkan kelengkapan data, verifikasi dokumen, pembayaran, dan hasil seleksi.'],
            ['faq-3','Bagaimana cara mengetahui biaya pendaftaran?','Biaya dan pilihan jalur yang aktif ditampilkan pada bagian Biaya PPDB. Konfirmasi rekening resmi kepada panitia sebelum membayar.'],
            ['faq-4','Apakah pilihan jurusan tersedia untuk semua jenjang?','Program yang tersedia mengikuti jenjang dan pengaturan sekolah. Kompetensi keahlian SMK hanya ditampilkan pada jenjang SMK.'],
            ['faq-5','Siapa yang bisa dihubungi untuk bantuan?','Gunakan informasi kontak sekolah pada bagian bawah halaman untuk menghubungi panitia PPDB.'],
        ] as [$id,$question,$answer]): ?><div class="accordion-item faq-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?= e($id) ?>"><?= e($question) ?></button></h3><div id="<?= e($id) ?>" class="accordion-collapse collapse" data-bs-parent="#ppdbFaq"><div class="accordion-body"><?= e($answer) ?></div></div></div><?php endforeach; ?>
    </div></div>
</div></div></section>

<?php if($announcements): ?><section class="section-space"><div class="container"><div class="section-heading mb-4"><p class="eyebrow">Update terbaru</p><h2>Yang baru dari sekolah.</h2></div><div class="row g-3"><?php foreach($announcements as $announcement): ?><div class="col-md-4"><article class="feature-card"><div class="feature-icon orange mb-3"><i class="fa-solid fa-bullhorn"></i></div><h3 class="h6 fw-bold"><?= e($announcement['title']) ?></h3><p class="text-secondary mb-0"><?= nl2br(e($announcement['body'])) ?></p></article></div><?php endforeach; ?></div></div></section><?php endif; ?>

<section class="section-space" id="kontak"><div class="container"><div class="row align-items-center g-4"><div class="col-lg-7"><p class="eyebrow">Ada yang mau ditanyakan?</p><h2 class="fw-bold">Kami siap bantu kamu mulai.</h2><p class="text-secondary">Hubungi panitia PPDB sekolah untuk info pendaftaran, program, dan jadwal terbaru.</p>
    <?php if($schoolAddress): ?><p><i class="fa-solid fa-location-dot text-primary me-2"></i><?= e($schoolAddress) ?></p><?php endif; ?>
    <?php if($schoolEmail): ?><p><i class="fa-regular fa-envelope text-primary me-2"></i><a href="mailto:<?= e($schoolEmail) ?>"><?= e($schoolEmail) ?></a></p><?php endif; ?>
    <?php if($schoolPhone): ?><p><i class="fa-solid fa-phone text-primary me-2"></i><a href="tel:<?= e($schoolPhone) ?>"><?= e($schoolPhone) ?></a></p><?php endif; ?>
    </div><div class="col-lg-5"><div class="content-card"><div class="feature-icon purple mb-3"><i class="fa-solid fa-comments"></i></div><h3 class="h5 fw-bold">Siap memulai perjalanan?</h3><p class="text-secondary">Buat akun gratis dan lanjutkan kapan saja dari dashboard.</p><div class="d-grid gap-2"><a href="<?= e(base_url('auth/register.php')) ?>" class="btn btn-primary">Daftar sekarang <i class="fa-solid fa-arrow-right ms-2"></i></a><?php if($whatsappNumber): ?><a href="https://wa.me/<?= e($whatsappNumber) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp me-2"></i>Chat WhatsApp panitia</a><?php endif; ?></div></div></div></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
