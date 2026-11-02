<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_role(['admin']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
verify_csrf();

$types = [
    'year' => ['academic_years','is_active'],
    'wave' => ['registration_waves','is_open'],
    'path' => ['registration_paths','is_active'],
    'major' => ['majors','is_active'],
    'announcement' => ['announcements','is_published'],
    'staff' => ['users','is_active'],
];
$type = (string) ($_POST['type'] ?? '');
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$enabled = (string) ($_POST['enabled'] ?? '');
if (!isset($types[$type]) || !$id || !in_array($enabled,['0','1'],true)) {
    set_flash('Perubahan master data tidak valid.','warning');
    redirect(base_url('admin/manage.php'));
}
if ($type === 'staff' && $id === (int) $admin['id'] && $enabled === '0') {
    set_flash('Akun yang sedang digunakan tidak dapat dinonaktifkan.','warning');
    redirect(base_url('admin/manage.php'));
}

[$table,$column] = $types[$type];
try {
    if ($type === 'year' && $enabled === '1') {
        $pdo->beginTransaction();
        $pdo->exec('UPDATE academic_years SET is_active=0');
    }
    $where = $type === 'staff'
        ? 'id=? AND role_id IN (SELECT id FROM roles WHERE name IN (\'admin\',\'committee\'))'
        : 'id=?';
    $stmt = $pdo->prepare("UPDATE {$table} SET {$column}=? WHERE {$where}");
    $stmt->execute([(int) $enabled,$id]);
    if (!$stmt->rowCount()) {
        $exists = $pdo->prepare("SELECT id FROM {$table} WHERE {$where}");
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            throw new RuntimeException('Data tidak ditemukan.');
        }
    }
    log_activity($pdo, (int) $admin['id'], 'master_' . $type . '_status', 'ID ' . $id . ' -> ' . $enabled);
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    set_flash('Status master data berhasil diperbarui.');
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash($exception->getMessage(),'warning');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Master data status change failed: ' . $exception->getMessage());
    set_flash('Status master data belum berhasil diperbarui.','danger');
}
redirect(base_url('admin/manage.php'));
