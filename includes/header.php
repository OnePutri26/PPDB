<?php
$user = current_user();
$pageTitle = $pageTitle ?? 'PPDB';
$isAdminArea = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
$isStudentArea = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/student/');
$isLandingPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php' && !$isAdminArea;
$showSidebar = $user && ($isAdminArea || $isStudentArea);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | PPDB Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="<?= $isAdminArea ? 'admin-page' : ($isStudentArea ? 'student-page' : ($isLandingPage ? 'landing-page' : 'auth-page')) ?>">
<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-dark d-inline-flex align-items-center gap-2" href="<?= e(base_url()) ?>"><span class="brand-mark"><i class="fa-solid fa-shapes"></i></span><span><?= e($schoolName ?? 'PPDB SEKOLAH') ?><small class="brand-caption d-block">LEARN · GROW · GO</small></span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <a class="nav-link" href="<?= e(base_url()) ?>">Beranda</a>
                <a class="nav-link" href="<?= e(base_url()) ?>#profil">Tentang sekolah</a>
                <a class="nav-link" href="<?= e(base_url()) ?>#jenjang">Jenjang</a>
                <a class="nav-link" href="<?= e(base_url()) ?>#informasi">Informasi PPDB</a>
                <a class="nav-link" href="<?= e(base_url()) ?>#faq">FAQ</a>
                <?php if ($user): ?>
                    <a class="nav-link" href="<?= e(base_url(in_array($user['role'], ['admin', 'committee'], true) ? 'admin/index.php' : 'student/dashboard.php')) ?>">Dashboard</a>
                    <form method="post" action="<?= e(base_url('auth/logout.php')) ?>" class="m-0 nav-logout">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="btn btn-outline-primary btn-sm" aria-label="Keluar dari akun">Keluar <i class="fa-solid fa-arrow-right-from-bracket ms-1"></i></button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-quiet btn-sm" href="<?= e(base_url('auth/login.php')) ?>">Login</a>
                    <a class="btn btn-primary btn-sm nav-cta" href="<?= e(base_url('auth/register.php')) ?>">Daftar sekarang <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<?php if ($flash): ?>
<div class="toast-container position-fixed top-0 end-0 p-3"><div class="toast ppdb-toast show" role="<?= $flash['type'] === 'danger' ? 'alert' : 'status' ?>" aria-live="<?= $flash['type'] === 'danger' ? 'assertive' : 'polite' ?>" aria-atomic="true"><div class="d-flex align-items-center"><div class="toast-body"><i class="fa-solid fa-sparkles me-2" aria-hidden="true"></i><?= e($flash['message']) ?></div><button type="button" class="btn-close me-2" data-bs-dismiss="toast" aria-label="Tutup"></button></div></div></div>
<?php endif; ?>
<?php if ($showSidebar): ?>
<div class="app-shell">
    <aside class="app-sidebar" aria-label="Navigasi dashboard">
        <div class="sidebar-label"><?= $isAdminArea ? 'Workspace panitia' : 'Ruang siswa' ?></div>
        <nav>
            <a class="sidebar-link <?= str_ends_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'dashboard.php') || str_ends_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 'index.php') ? 'active' : '' ?>" href="<?= e(base_url($isAdminArea ? 'admin/index.php' : 'student/dashboard.php')) ?>"><i class="fa-solid fa-grid-2"></i>Overview</a>
            <?php if ($isAdminArea): ?>
                <a class="sidebar-link" href="<?= e(base_url('admin/index.php#pendaftar')) ?>"><i class="fa-solid fa-users"></i>Data pendaftar</a>
                <a class="sidebar-link" href="<?= e(base_url('admin/reenrollment.php')) ?>"><i class="fa-solid fa-clipboard-check"></i>Daftar ulang</a>
                <a class="sidebar-link" href="<?= e(base_url('admin/export.php')) ?>"><i class="fa-solid fa-file-export"></i>Ekspor data</a>
                <?php if ($user['role'] === 'admin'): ?><a class="sidebar-link" href="<?= e(base_url('admin/manage.php')) ?>"><i class="fa-solid fa-sliders"></i>Pengaturan</a><?php endif; ?>
            <?php else: ?>
                <a class="sidebar-link" href="<?= e(base_url('student/application.php')) ?>"><i class="fa-solid fa-file-pen"></i>Formulir PPDB</a>
                <a class="sidebar-link" href="<?= e(base_url('student/receipt.php')) ?>"><i class="fa-solid fa-receipt"></i>Bukti pendaftaran</a>
                <a class="sidebar-link" href="<?= e(base_url()) ?>#informasi"><i class="fa-solid fa-circle-info"></i>Info sekolah</a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="app-main">
<?php endif; ?>
<main>
