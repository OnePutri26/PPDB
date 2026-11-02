<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function base_url(string $path = ''): string
{
    $base = rtrim(getenv('PPDB_BASE_URL') ?: '/dashboard/ppdb', '/');
    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $provided = $_POST['csrf_token'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        http_response_code(419);
        exit('Sesi formulir tidak valid. Muat ulang halaman, lalu coba kembali.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_role(array $roles): array
{
    $user = current_user();
    if (!$user) {
        set_flash('Silakan masuk terlebih dahulu.', 'warning');
        redirect(base_url('auth/login.php'));
    }
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
    return $user;
}

function set_flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function log_activity(PDO $pdo, ?int $userId, string $action, ?string $details = null): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function status_label(string $status): string
{
    return match ($status) {
        'verified' => 'Terverifikasi',
        'accepted' => 'Diterima',
        'pending', 'submitted' => 'Menunggu',
        'rejected' => 'Ditolak',
        'waitlisted' => 'Daftar tunggu',
        'needs_revision' => 'Perlu perbaikan',
        'unpaid' => 'Belum dibayar',
        'not_required' => 'Tidak diperlukan',
        'in_progress' => 'Dalam proses',
        'completed' => 'Selesai',
        'draft' => 'Draft',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function upload_file(array $file, array $allowedMimes, string $directory): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal. Silakan pilih ulang berkas.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran berkas maksimal 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('Format berkas tidak diizinkan.');
    }
    $extension = match ($mime) {
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        default => throw new RuntimeException('Format berkas tidak diizinkan.'),
    };
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder upload tidak dapat disiapkan.');
    }
    $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $storedName)) {
        throw new RuntimeException('Berkas gagal disimpan.');
    }
    return [
        'original_name' => basename((string) $file['name']),
        'stored_name' => $storedName,
        'mime_type' => $mime,
        'file_size' => (int) $file['size'],
    ];
}
