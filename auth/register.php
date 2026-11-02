<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$requestedLevel = filter_input(INPUT_GET, 'level_id', FILTER_VALIDATE_INT);
if ($requestedLevel) {
    $levelCheck = $pdo->prepare('SELECT id FROM education_levels WHERE id=? AND is_active=1');
    $levelCheck->execute([$requestedLevel]);
    if ($levelCheck->fetch()) {
        $_SESSION['selected_level_id'] = $requestedLevel;
    }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Nama dan alamat email harus diisi dengan benar.';
    } elseif (strlen($password) < 10 || $password !== $confirmation) {
        $error = 'Kata sandi minimal 10 karakter dan harus sama dengan konfirmasi.';
    } else {
        try {
            $role = $pdo->query("SELECT id FROM roles WHERE name = 'student'")->fetch();
            if (!$role) {
                throw new RuntimeException('Role siswa belum tersedia. Import skema database terlebih dahulu.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (role_id, name, email, password, phone) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([(int) $role['id'], $name, $email, password_hash($password, PASSWORD_DEFAULT), $phone ?: null]);
            $userId = (int) $pdo->lastInsertId();
            $student = $pdo->prepare('INSERT INTO students (user_id, full_name, whatsapp) VALUES (?, ?, ?)');
            $student->execute([$userId, $name, $phone ?: null]);
            $pdo->commit();
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'student'];
            log_activity($pdo, $userId, 'account_registered');
            redirect(base_url(isset($_SESSION['selected_level_id']) ? 'student/application.php' : 'student/dashboard.php'));
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                $error = 'Email tersebut sudah terdaftar. Silakan masuk atau gunakan email lain.';
            } else {
                error_log('Student registration failed: ' . $exception->getMessage());
                $error = 'Akun belum dapat dibuat. Silakan coba kembali.';
            }
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        }
    }
}
$pageTitle = 'Registrasi Akun';
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-wrap">
    <div class="container">
        <div class="row justify-content-center align-items-stretch g-3">
            <div class="col-lg-5"><aside class="auth-aside h-100 d-flex flex-column justify-content-between"><div><div class="feature-icon mb-4"><i class="fa-solid fa-sparkles"></i></div><p class="text-uppercase small fw-bold opacity-75">Satu langkah baru</p><h2 class="display-6 fw-bold">Masa depanmu mulai dari sini.</h2><p class="opacity-75"><?= isset($_SESSION['selected_level_id']) ? 'Jenjang yang kamu pilih akan tersedia langsung di formulir pendaftaran.' : 'Buat akun sekali, lanjutkan pendaftaran dan pantau status kapan saja.' ?></p></div><div class="small opacity-75"><i class="fa-solid fa-shield-heart me-2"></i>Data pendaftaran diproses dengan aman.</div></aside></div>
            <div class="col-lg-6 col-xl-5">
                <div class="auth-card">
                    <p class="eyebrow mb-2">Mulai pendaftaran</p>
                    <h1 class="h3 fw-bold mb-2">Buat akun calon siswa</h1>
                    <p class="text-secondary mb-4">Gunakan email aktif untuk masuk dan memantau proses pendaftaran.</p>
                    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                    <form method="post" data-loading>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3"><label class="form-label" for="name">Nama lengkap</label><input class="form-control" id="name" name="name" maxlength="120" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label" for="phone">Nomor WhatsApp</label><input class="form-control" type="tel" id="phone" name="phone" autocomplete="tel" value="<?= e($_POST['phone'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label" for="password">Kata sandi</label><input class="form-control" type="password" id="password" name="password" minlength="10" required autocomplete="new-password"><div class="form-text">Minimal 10 karakter.</div></div>
                        <div class="mb-4"><label class="form-label" for="password_confirmation">Ulangi kata sandi</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" minlength="10" required autocomplete="new-password" data-password-confirm="#password"></div>
                        <button class="btn btn-primary w-100" type="submit">Buat akun</button>
                    </form>
                    <p class="text-center text-secondary mt-4 mb-0">Sudah memiliki akun? <a href="<?= e(base_url('auth/login.php')) ?>">Masuk</a></p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
