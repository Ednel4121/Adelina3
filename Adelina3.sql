-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 20, 2026 at 12:16 PM
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
-- Database: `adelina3_hoa`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','officer','resident') NOT NULL DEFAULT 'resident',
  `contact_no` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `contact_no`) VALUES
(3, 'Admin', 'admin@gmail.com', '$2y$10$UFJgF.14tuNHj0ILXcTIqOSk5HCkFrcPfP907flwGsgev5mpnID22', 'admin', '09995670275'),
(4, 'test 1', 'test@gmail.com', '$2y$10$uaLPzwrYiWBokzywc/XqD.1Sn92ANPtFcQUBCzpXMn.rqGaNJvgJ.', 'resident', '0909090909'),
(22, 'TEST2', 'TEST2@gmail.com', '$2y$10$JSsWvXRo4vMoYpGskD.BPuhscpIZQGSB416FVzpm8kiE/6bIvOWlq', 'resident', '09992670575'),
(23, 'ednel arancillo', 'ednelarancillo@gmail.com', '$2y$10$J7fnvbYsekNlyTdxdWG/ZubtXpgZxBQOiymoKL2w.i9Euj3N9m5QC', 'resident', '09995670275'),
(24, 'EL CAPANAYAN', 'elcapanayan06@gmail.com', '$2y$10$Q5yC9x.Ldi0JuyR.c.MfVuzgJA8JWHlC.2KvFr7nLle1WfWqC8nwW', 'resident', '09162639905');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
