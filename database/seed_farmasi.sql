-- Data fiktif untuk tes fullstack. MySQL 5.7+.
-- Buat database kosong, pilih database tersebut, lalu impor berkas ini.
SET NAMES utf8mb4;

CREATE TABLE suppliers (
  id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE medicines (
  id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(160) NOT NULL,
  unit VARCHAR(30) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_medicines_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data stok awal per batch pada 2026-10-01, sebelum seluruh stock_usage.
-- Tabel ini hanya wadah seed; peserta boleh memindahkan datanya ke model stok sendiri.
-- UNIQUE (medicine_id, batch_no) berlaku untuk stok awal, bukan penerimaan berikutnya.
CREATE TABLE seed_batch_stock (
  id INT UNSIGNED NOT NULL,
  medicine_id INT UNSIGNED NOT NULL,
  batch_no VARCHAR(50) NOT NULL,
  expires_on DATE NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seed_batch_stock_batch (medicine_id, batch_no),
  CONSTRAINT fk_seed_batch_stock_medicine FOREIGN KEY (medicine_id)
    REFERENCES medicines (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pemakaian obat yang sudah selesai sebelum peserta menjalankan aplikasi.
-- Tabel ini adalah data awal untuk laporan; API/UI pemakaian tidak diminta.
CREATE TABLE stock_usage (
  id INT UNSIGNED NOT NULL,
  medicine_id INT UNSIGNED NOT NULL,
  batch_no VARCHAR(50) NOT NULL,
  used_at DATETIME NOT NULL,
  unit_name VARCHAR(100) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_stock_usage_batch (medicine_id, batch_no),
  CONSTRAINT fk_stock_usage_medicine FOREIGN KEY (medicine_id)
    REFERENCES medicines (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO suppliers (id, name, is_active) VALUES
  (1, 'Farma Nusantara', 1),
  (2, 'Medika Sentosa', 1),
  (3, 'Pemasok Arsip', 0);

INSERT INTO medicines (id, code, name, unit, is_active) VALUES
  (101, 'OBT-001', 'Paracetamol 500 mg tablet', 'tablet', 1),
  (102, 'OBT-002', 'Amoxicillin 500 mg kapsul', 'kapsul', 1),
  (103, 'OBT-003', 'Salbutamol 2 mg tablet', 'tablet', 1),
  (104, 'OBT-004', 'Ibuprofen 400 mg tablet', 'tablet', 1),
  (105, 'OBT-005', 'Obat nonaktif contoh', 'tablet', 0),
  (106, 'OBT-006', 'Cetirizine 10 mg tablet', 'tablet', 1),
  (107, 'OBT-007', 'Loratadine 10 mg tablet', 'tablet', 1),
  (108, 'OBT-008', 'Metformin 500 mg tablet', 'tablet', 1),
  (109, 'OBT-009', 'Amlodipine 5 mg tablet', 'tablet', 1),
  (110, 'OBT-010', 'Omeprazole 20 mg kapsul', 'kapsul', 1),
  (111, 'OBT-011', 'Oralit sachet', 'sachet', 1),
  (112, 'OBT-012', 'Asam folat 1 mg tablet', 'tablet', 1),
  (113, 'OBT-013', 'Zinc 20 mg tablet', 'tablet', 1),
  (114, 'OBT-014', 'Simvastatin 10 mg tablet', 'tablet', 1),
  (115, 'OBT-015', 'Losartan 50 mg tablet', 'tablet', 1),
  (116, 'OBT-016', 'Vitamin B kompleks tablet', 'tablet', 1),
  (117, 'OBT-017', 'Chlorpheniramine 4 mg tablet', 'tablet', 1),
  (118, 'OBT-018', 'Antasida DOEN tablet', 'tablet', 1),
  (119, 'OBT-019', 'Domperidone 10 mg tablet', 'tablet', 1),
  (120, 'OBT-020', 'Ondansetron 4 mg tablet', 'tablet', 1),
  (121, 'OBT-021', 'Nystatin suspensi oral', 'botol', 1),
  (122, 'OBT-022', 'Hydrocortisone 1 persen krim', 'tube', 1),
  (123, 'OBT-023', 'Natrium klorida 0,9 persen infus', 'botol', 1),
  (124, 'OBT-024', 'Ketoprofen 50 mg kapsul', 'kapsul', 0),
  (125, 'OBT-025', 'Cefadroxil 500 mg kapsul', 'kapsul', 0);

INSERT INTO seed_batch_stock (id, medicine_id, batch_no, expires_on, quantity) VALUES
  (1, 101, 'PCT-2601', '2027-12-31', 100),
  (2, 101, 'PCT-2602', '2028-03-31', 40),
  (3, 102, 'AMX-2601', '2027-05-31', 20),
  (4, 103, 'SAL-2601', '2027-11-30', 15),
  (5, 104, 'IBU-2601', '2027-09-30', 5),
  (6, 101, 'PCT-2501', '2026-09-30', 8),
  (7, 107, 'LOR-2501', '2026-09-30', 6),
  (8, 108, 'MET-2601', '2028-02-28', 12),
  (9, 112, 'FOL-2601', '2027-08-31', 9),
  (10, 118, 'ANT-2601', '2027-06-30', 7);

INSERT INTO stock_usage (id, medicine_id, batch_no, used_at, unit_name, quantity) VALUES
  (1, 101, 'PCT-2601', '2026-10-02 09:00:00', 'Poliklinik Umum', 6),
  (2, 102, 'AMX-2601', '2026-10-02 11:00:00', 'IGD', 4),
  (3, 104, 'IBU-2601', '2026-10-02 14:00:00', 'Rawat Inap', 2);
