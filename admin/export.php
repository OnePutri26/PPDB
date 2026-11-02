<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['admin','committee']);
$rows = $pdo->query('SELECT r.registration_number,r.created_at,r.biodata_status,r.payment_status,r.selection_status,r.class_choice,s.nik,s.nisn,s.full_name,s.gender,s.birth_place,s.birth_date,s.whatsapp,s.address,u.email,p.father_name,p.mother_name,p.whatsapp AS parent_whatsapp,ps.name AS previous_school,ay.name AS academic_year,w.name AS wave,path.name AS registration_path,l.code AS education_level,m.name AS major,m2.name AS second_major FROM registrations r JOIN students s ON s.id=r.student_id JOIN users u ON u.id=s.user_id LEFT JOIN parents p ON p.student_id=s.id LEFT JOIN previous_schools ps ON ps.student_id=s.id JOIN academic_years ay ON ay.id=r.academic_year_id JOIN registration_waves w ON w.id=r.wave_id JOIN registration_paths path ON path.id=r.path_id LEFT JOIN education_levels l ON l.id=r.education_level_id LEFT JOIN majors m ON m.id=r.major_id LEFT JOIN majors m2 ON m2.id=r.second_major_id ORDER BY r.created_at DESC')->fetchAll();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="data-ppdb-' . date('Ymd-His') . '.csv"');
header('X-Content-Type-Options: nosniff');
$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit('Ekspor tidak dapat dibuat.');
}
fwrite($output, "\xEF\xBB\xBF");
$columns = ['registration_number'=>'Nomor pendaftaran','created_at'=>'Tanggal daftar','education_level'=>'Jenjang','biodata_status'=>'Status biodata','payment_status'=>'Status pembayaran','selection_status'=>'Status seleksi','nik'=>'NIK','nisn'=>'NISN','full_name'=>'Nama lengkap','gender'=>'Jenis kelamin','birth_place'=>'Tempat lahir','birth_date'=>'Tanggal lahir','whatsapp'=>'WhatsApp siswa','address'=>'Alamat','email'=>'Email','father_name'=>'Nama ayah','mother_name'=>'Nama ibu','parent_whatsapp'=>'WhatsApp orang tua','previous_school'=>'Sekolah asal','academic_year'=>'Tahun ajaran','wave'=>'Gelombang','registration_path'=>'Jalur','major'=>'Program utama','second_major'=>'Program kedua','class_choice'=>'Pilihan kelas'];
fputcsv($output, array_values($columns));
foreach ($rows as $row) {
    $values = [];
    foreach (array_keys($columns) as $column) {
        $value = (string) ($row[$column] ?? '');
        if (preg_match('/^[\s]*[=+\-@]/', $value)) {
            $value = "'" . $value;
        }
        $values[] = $value;
    }
    fputcsv($output, $values);
}
fclose($output);
