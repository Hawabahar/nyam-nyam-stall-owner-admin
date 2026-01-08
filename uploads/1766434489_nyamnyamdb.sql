-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 22, 2025 at 07:55 PM
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
-- Database: `nyamnyamdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `stall`
--

CREATE TABLE `stall` (
  `StallId` int(11) NOT NULL,
  `StallOwnerId` int(11) DEFAULT NULL,
  `StallName` varchar(100) DEFAULT NULL,
  `Location` varchar(100) DEFAULT NULL,
  `UpdateStatus` enum('Pending','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stall`
--

INSERT INTO `stall` (`StallId`, `StallOwnerId`, `StallName`, `Location`, `UpdateStatus`) VALUES
(13, 13, 'dndnkdeke', 'denknedkn', 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `stallowner`
--

CREATE TABLE `stallowner` (
  `StallOwnerId` int(11) NOT NULL,
  `FirstName` varchar(100) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `MobilePhone` varchar(20) DEFAULT NULL,
  `CompanyName` varchar(100) DEFAULT NULL,
  `BusinessProof` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stallowner`
--

INSERT INTO `stallowner` (`StallOwnerId`, `FirstName`, `Email`, `Password`, `MobilePhone`, `CompanyName`, `BusinessProof`) VALUES
(13, 'hawa bahar', 'hawa.bahar5@hotmail.com', '$2y$10$QRLIe3I6HXB7Soc5diXILOsbpaU84vlKxPiVZe5tL4rSpMlZiBsNu', 'ccmd,ds', 'skjsksn', 'uploads/phone makan.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `stall`
--
ALTER TABLE `stall`
  ADD PRIMARY KEY (`StallId`),
  ADD KEY `StallOwnerId` (`StallOwnerId`);

--
-- Indexes for table `stallowner`
--
ALTER TABLE `stallowner`
  ADD PRIMARY KEY (`StallOwnerId`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `stall`
--
ALTER TABLE `stall`
  MODIFY `StallId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `stallowner`
--
ALTER TABLE `stallowner`
  MODIFY `StallOwnerId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `stall`
--
ALTER TABLE `stall`
  ADD CONSTRAINT `stall_ibfk_1` FOREIGN KEY (`StallOwnerId`) REFERENCES `stallowner` (`StallOwnerId`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
