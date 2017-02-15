SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE `eventlog` (
  `id` int(11) NOT NULL,
  `signature` text,
  `events` longtext,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `eventlog`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `eventlog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `level_id` int(3) NOT NULL DEFAULT '0',
  `line_id` varchar(50) DEFAULT NULL,
  `number` int(3) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

CREATE TABLE `level` (
  `id` int(11) NOT NULL,
  `penjumlahan` int(3) NOT NULL DEFAULT '0',
  `penjumlahan_counter` int(3) NOT NULL DEFAULT '0',
  `pengurangan` int(3) NOT NULL DEFAULT '0',
  `pengurangan_counter` int(3) NOT NULL DEFAULT '0',
  `perkalian` int(3) NOT NULL DEFAULT '0',
  `perkalian_counter` int(3) NOT NULL DEFAULT '0',
  `pembagian` int(3) NOT NULL DEFAULT '0',
  `pembagian_counter` int(3) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `level`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `level`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;