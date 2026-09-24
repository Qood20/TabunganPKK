-- Schema utama aplikasi Tabungan PKK, termasuk tabel akun, anggota, dan transaksi.
-- Database Name: db_tabunganpkk

CREATE DATABASE IF NOT EXISTS `db_tabunganpkk` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_tabunganpkk`;

-- Hapus tabel dari tabel anak ke induk agar dapat di-import ulang dengan aman.
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `members`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'bendahara', 'member') NOT NULL DEFAULT 'member',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profil anggota dapat terhubung opsional ke akun pengguna.
CREATE TABLE `members` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` BIGINT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_members_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Setiap transaksi menyimpan anggota pemilik dan petugas pencatat.
CREATE TABLE `transactions` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `member_id` BIGINT NOT NULL,
  `user_id` BIGINT NOT NULL,
  `type` ENUM('setor', 'tarik') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_transactions_members` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data awal akun untuk pengujian pembagian hak akses (RBAC).
-- Password Default:
-- admin: admin123
-- bendahara: bendahara123
-- member1: member123
INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2y$10$bIDFJUJrZsbzV8rdeCOAP.fSrAP8oR2RDeNg0mjo7iALsXMS9I0iG', 'admin'),
(2, 'bendahara', '$2y$10$FiK5yOd.2qUqkzn6g91KROtREFuNz0313BX8GiJaU56RMXM5NDixm', 'bendahara'),
(3, 'member1', '$2y$10$9Wm0NiudSlhdr0NFZ0D7OecpyGgctzmHdcMjSah.yA2QCUdHkH7ES', 'member');

-- Data awal profil anggota untuk pengujian aplikasi.
INSERT INTO `members` (`id`, `user_id`, `name`, `phone`, `address`) VALUES
(1, 3, 'Ibu Ani', '081234567890', 'RT 02 / RW 05, Kelurahan Mawar'),
(2, NULL, 'Ibu Budi', '081987654321', 'RT 01 / RW 05, Kelurahan Mawar'),
(3, NULL, 'Ibu Siti', '085678901234', 'RT 03 / RW 05, Kelurahan Mawar'),
(4, 2, 'Bu Aisyah', '081299887766', 'RT 04 / RW 05, Kelurahan Mawar');

-- Data awal mutasi setoran dan penarikan untuk dashboard/laporan.
INSERT INTO `transactions` (`id`, `member_id`, `user_id`, `type`, `amount`, `description`, `created_at`) VALUES
(1, 1, 2, 'setor', 500000.00, 'Setoran Wajib Awal', '2026-09-01 08:30:00'),
(2, 1, 2, 'setor', 250000.00, 'Setoran Sukarela', '2026-09-10 10:15:00'),
(3, 1, 2, 'tarik', 100000.00, 'Penarikan Sembako', '2026-09-15 14:20:00'),
(4, 2, 2, 'setor', 300000.00, 'Setoran Bulanan', '2026-09-05 09:00:00'),
(5, 3, 2, 'setor', 450000.00, 'Setoran Bulanan', '2026-09-08 11:00:00'),
(6, 4, 2, 'setor', 400000.00, 'Setoran Awal Tabungan Bu Aisyah', '2026-09-12 13:00:00');
