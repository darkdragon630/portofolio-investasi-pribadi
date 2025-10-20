-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2025 at 03:42 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `saaz_v3`
--

-- --------------------------------------------------------

--
-- Table structure for table `investasi`
--

CREATE TABLE `investasi` (
  `id` int(11) NOT NULL,
  `judul_investasi` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `jumlah` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tanggal_investasi` date NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `bukti_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(50) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama_kategori`, `deskripsi`, `created_at`) VALUES
(1, 'Saham', 'Investasi saham di bursa efek', '2025-10-18 13:40:52'),
(2, 'Crypto', 'Investasi cryptocurrency', '2025-10-18 13:40:52'),
(3, 'Properti di Tokenisasi', 'Investasi properti yang ditokenisasi', '2025-10-18 13:40:52'),
(4, 'Nabung', 'Tabungan dan deposito', '2025-10-18 13:40:52'),
(5, 'Emas', 'Investasi emas fisik atau digital', '2025-10-18 13:40:52'),
(6, 'Obligasi', 'Investasi surat utang', '2025-10-18 13:40:52'),
(7, 'Reksadana', 'Investasi reksadana', '2025-10-18 13:40:52');

-- --------------------------------------------------------

--
-- Table structure for table `kerugian_investasi`
--

CREATE TABLE `kerugian_investasi` (
  `id` int(11) NOT NULL,
  `investasi_id` int(11) NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `judul_kerugian` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `jumlah_kerugian` decimal(15,2) NOT NULL,
  `persentase_kerugian` decimal(10,6) DEFAULT NULL,
  `tanggal_kerugian` date NOT NULL,
  `sumber_kerugian` enum('capital_loss','biaya_admin','biaya_transaksi','penurunan_nilai','lainnya') DEFAULT 'lainnya',
  `status` enum('realized','unrealized') DEFAULT 'realized',
  `bukti_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `keuntungan_investasi`
--

CREATE TABLE `keuntungan_investasi` (
  `id` int(11) NOT NULL,
  `investasi_id` int(11) NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `judul_keuntungan` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `jumlah_keuntungan` decimal(15,2) NOT NULL,
  `persentase_keuntungan` decimal(10,6) DEFAULT NULL,
  `tanggal_keuntungan` date NOT NULL,
  `sumber_keuntungan` enum('dividen','capital_gain','bunga','bonus','lainnya') DEFAULT 'lainnya',
  `status` enum('realized','unrealized') DEFAULT 'realized',
  `bukti_file` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_investasi_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_investasi_summary` (
`id` int(11)
,`judul_investasi` varchar(100)
,`modal_investasi` decimal(15,2)
,`tanggal_investasi` date
,`nama_kategori` varchar(50)
,`total_keuntungan` decimal(37,2)
,`total_kerugian` decimal(37,2)
,`nilai_sekarang` decimal(39,2)
,`roi_persen` decimal(47,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_statistik_global`
-- (See below for the actual view)
--
CREATE TABLE `v_statistik_global` (
`total_portofolio` bigint(21)
,`total_investasi` decimal(37,2)
,`total_keuntungan` decimal(37,2)
,`total_kerugian` decimal(37,2)
,`total_nilai` decimal(39,2)
,`roi_global` decimal(47,6)
,`total_transaksi_keuntungan` bigint(21)
,`total_transaksi_kerugian` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure for view `v_investasi_summary`
--
DROP TABLE IF EXISTS `v_investasi_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_investasi_summary`  AS SELECT `i`.`id` AS `id`, `i`.`judul_investasi` AS `judul_investasi`, `i`.`jumlah` AS `modal_investasi`, `i`.`tanggal_investasi` AS `tanggal_investasi`, `k`.`nama_kategori` AS `nama_kategori`, coalesce(sum(distinct `ku`.`jumlah_keuntungan`),0) AS `total_keuntungan`, coalesce(sum(distinct `kr`.`jumlah_kerugian`),0) AS `total_kerugian`, `i`.`jumlah`+ coalesce(sum(distinct `ku`.`jumlah_keuntungan`),0) - coalesce(sum(distinct `kr`.`jumlah_kerugian`),0) AS `nilai_sekarang`, (coalesce(sum(distinct `ku`.`jumlah_keuntungan`),0) - coalesce(sum(distinct `kr`.`jumlah_kerugian`),0)) / `i`.`jumlah` * 100 AS `roi_persen` FROM (((`investasi` `i` left join `keuntungan_investasi` `ku` on(`i`.`id` = `ku`.`investasi_id`)) left join `kerugian_investasi` `kr` on(`i`.`id` = `kr`.`investasi_id`)) join `kategori` `k` on(`i`.`kategori_id` = `k`.`id`)) GROUP BY `i`.`id`, `i`.`judul_investasi`, `i`.`jumlah`, `i`.`tanggal_investasi`, `k`.`nama_kategori` ;

-- --------------------------------------------------------

--
-- Structure for view `v_statistik_global`
--
DROP TABLE IF EXISTS `v_statistik_global`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_statistik_global`  AS SELECT count(distinct `i`.`id`) AS `total_portofolio`, coalesce(sum(`i`.`jumlah`),0) AS `total_investasi`, coalesce(sum(`ku`.`jumlah_keuntungan`),0) AS `total_keuntungan`, coalesce(sum(`kr`.`jumlah_kerugian`),0) AS `total_kerugian`, coalesce(sum(`i`.`jumlah`),0) + coalesce(sum(`ku`.`jumlah_keuntungan`),0) - coalesce(sum(`kr`.`jumlah_kerugian`),0) AS `total_nilai`, (coalesce(sum(`ku`.`jumlah_keuntungan`),0) - coalesce(sum(`kr`.`jumlah_kerugian`),0)) / nullif(sum(`i`.`jumlah`),0) * 100 AS `roi_global`, count(distinct `ku`.`id`) AS `total_transaksi_keuntungan`, count(distinct `kr`.`id`) AS `total_transaksi_kerugian` FROM ((`investasi` `i` left join `keuntungan_investasi` `ku` on(`i`.`id` = `ku`.`investasi_id`)) left join `kerugian_investasi` `kr` on(`i`.`id` = `kr`.`investasi_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `investasi`
--
ALTER TABLE `investasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_investasi_kategori` (`kategori_id`),
  ADD KEY `idx_tanggal_investasi` (`tanggal_investasi`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_kategori` (`nama_kategori`);

--
-- Indexes for table `kerugian_investasi`
--
ALTER TABLE `kerugian_investasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_investasi_id` (`investasi_id`),
  ADD KEY `idx_kategori_id` (`kategori_id`),
  ADD KEY `idx_tanggal_kerugian` (`tanggal_kerugian`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `keuntungan_investasi`
--
ALTER TABLE `keuntungan_investasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_investasi_id` (`investasi_id`),
  ADD KEY `idx_kategori_id` (`kategori_id`),
  ADD KEY `idx_tanggal_keuntungan` (`tanggal_keuntungan`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `investasi`
--
ALTER TABLE `investasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `kerugian_investasi`
--
ALTER TABLE `kerugian_investasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `keuntungan_investasi`
--
ALTER TABLE `keuntungan_investasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `investasi`
--
ALTER TABLE `investasi`
  ADD CONSTRAINT `investasi_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `kerugian_investasi`
--
ALTER TABLE `kerugian_investasi`
  ADD CONSTRAINT `kerugian_investasi_ibfk_1` FOREIGN KEY (`investasi_id`) REFERENCES `investasi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `kerugian_investasi_ibfk_2` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `keuntungan_investasi`
--
ALTER TABLE `keuntungan_investasi`
  ADD CONSTRAINT `keuntungan_investasi_ibfk_1` FOREIGN KEY (`investasi_id`) REFERENCES `investasi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `keuntungan_investasi_ibfk_2` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
