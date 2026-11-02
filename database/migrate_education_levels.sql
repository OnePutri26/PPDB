USE db_ppdb;

CREATE TABLE IF NOT EXISTS education_levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code ENUM('SD','SMP','SMA','SMK') NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    prior_school_label VARCHAR(120) NOT NULL,
    program_label VARCHAR(120) NOT NULL DEFAULT 'Program / peminatan',
    class_info TEXT NULL,
    facilities TEXT NULL,
    tuition_info TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO education_levels (code,name,prior_school_label,program_label,is_active) VALUES
('SD','Sekolah Dasar (SD)','Asal TK / RA (jika ada)','Pilihan kelas',0),
('SMP','Sekolah Menengah Pertama (SMP)','Asal SD / MI','Pilihan kelas',0),
('SMA','Sekolah Menengah Atas (SMA)','Asal SMP / MTs','Peminatan / program',0),
('SMK','Sekolah Menengah Kejuruan (SMK)','Asal SMP / MTs','Kompetensi keahlian',0);

ALTER TABLE majors
    ADD COLUMN IF NOT EXISTS education_level_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT fk_major_education_level FOREIGN KEY (education_level_id) REFERENCES education_levels(id) ON DELETE SET NULL;

ALTER TABLE registrations
    ADD COLUMN IF NOT EXISTS education_level_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS second_major_id BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS class_choice VARCHAR(50) NULL,
    ADD CONSTRAINT fk_registration_second_major FOREIGN KEY (second_major_id) REFERENCES majors(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_registration_education_level FOREIGN KEY (education_level_id) REFERENCES education_levels(id);
