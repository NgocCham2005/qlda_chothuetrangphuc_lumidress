SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";
CREATE DATABASE IF NOT EXISTS `thue_trang_phuc_2026` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `thue_trang_phuc_2026`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `rental_items`;
DROP TABLE IF EXISTS `rentals`;
DROP TABLE IF EXISTS `costumes`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `admins` (`fullname`,`username`,`password`) VALUES
('Quản trị viên','admin','$2a$07$asxx54ahjppf45sd87a5auFL5K1.Cmt9ZheoVVuudOi5BCi10qWly');
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `categories` (`id`,`name`,`description`) VALUES
(1,'Váy cưới','Váy công chúa, đuôi cá và váy cưới tối giản'),
(2,'Vest nam','Vest cưới, vest công sở và vest sự kiện'),
(3,'Áo dài','Áo dài cưới, áo dài truyền thống và áo dài cách tân'),
(4,'Váy dạ hội','Váy dự tiệc, váy phù dâu và váy biểu diễn');
CREATE TABLE `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fullname` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `identity_number` varchar(30) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `customers` (`id`,`fullname`,`phone`,`email`,`identity_number`,`address`,`note`) VALUES
(1,'Nguyễn Minh Anh','0987654321','minhanh@gmail.com','038202001234','Thanh Xuân, Hà Nội','Khách thuê váy cưới'),
(2,'Trần Hoàng Nam','0912345678','hoangnam@gmail.com','036199008765','Cầu Giấy, Hà Nội','Thường thuê vest sự kiện'),
(3,'Lê Thu Hương','0966888999','thuhuong@gmail.com','034203002468','Đống Đa, Hà Nội','Ưu tiên áo dài size M'),
(4,'Phạm Quốc Bảo','0905123456','quocbao@gmail.com','037198001357','Hoàng Mai, Hà Nội','Liên hệ trước khi nhận đồ');
CREATE TABLE `costumes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int DEFAULT NULL,
  `size` varchar(50) NOT NULL,
  `color` varchar(100) DEFAULT NULL,
  `rental_price` decimal(15,2) NOT NULL DEFAULT 0,
  `deposit` decimal(15,2) NOT NULL DEFAULT 0,
  `quantity` int NOT NULL DEFAULT 1,
  `status` enum('available','maintenance','retired') NOT NULL DEFAULT 'available',
  `condition_note` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `category_id` (`category_id`),
  KEY `status` (`status`),
  CONSTRAINT `costumes_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `costumes` (`id`,`code`,`name`,`category_id`,`size`,`color`,`rental_price`,`deposit`,`quantity`,`status`,`condition_note`,`description`,`image`) VALUES
(1,'VC001','Váy cưới công chúa ren Pháp',1,'M','Trắng',1200000,5000000,2,'available','Mới 95%, đã vệ sinh','<p>Váy cưới dáng công chúa, phần thân ren và tùng váy nhiều lớp. Phù hợp lễ cưới trong nhà.</p>','sample-1.svg'),
(2,'VN001','Vest nam xanh navy hai khuy',2,'L','Xanh navy',450000,2000000,3,'available','Tốt, đủ áo và quần','<p>Vest nam dáng slim fit, chất liệu ít nhăn, phù hợp tiệc cưới và sự kiện.</p>','sample-2.svg'),
(3,'AD001','Áo dài đỏ thêu hoa sen',3,'M','Đỏ',350000,1500000,4,'available','Mới 90%','<p>Áo dài truyền thống thêu hoa sen, gồm áo và quần trắng.</p>','sample-3.svg'),
(4,'VC002','Váy cưới đuôi cá trễ vai',1,'S','Trắng kem',900000,4000000,2,'available','Mới 92%','<p>Váy cưới đuôi cá tôn dáng, phần vai mềm và khóa kéo sau lưng.</p>','sample-4.svg'),
(5,'VN002','Vest nam đen cổ điển',2,'XL','Đen',380000,1800000,4,'available','Tốt','<p>Vest đen cổ điển, phù hợp chú rể, MC và sự kiện trang trọng.</p>','sample-5.svg'),
(6,'AD002','Áo dài trắng đính ngọc trai',3,'S','Trắng',320000,1200000,3,'maintenance','Đang thay khóa kéo','<p>Áo dài trắng nhẹ, đính ngọc trai thủ công, phù hợp lễ ăn hỏi.</p>','sample-6.svg'),
(7,'DH001','Váy phù dâu pastel',4,'M','Hồng phấn',280000,1000000,6,'available','Tốt','<p>Váy phù dâu dáng dài, chất liệu voan nhẹ và dễ di chuyển.</p>','sample-7.svg'),
(8,'AD003','Áo dài nam cách tân',3,'L','Xanh rêu',300000,1200000,4,'available','Tốt','<p>Áo dài nam cách tân, đi kèm quần đen, phù hợp lễ cưới và chụp ảnh.</p>','sample-8.svg'),
(9,'DH002','Váy dạ hội ánh kim',4,'M','Vàng champagne',700000,3000000,2,'available','Mới 90%','<p>Váy dạ hội ánh kim, phù hợp biểu diễn và sự kiện buổi tối.</p>','sample-9.svg');
CREATE TABLE `rentals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rental_code` varchar(30) NOT NULL,
  `customer_id` int NOT NULL,
  `receive_date` date NOT NULL,
  `return_date` date NOT NULL,
  `rental_amount` decimal(15,2) NOT NULL DEFAULT 0,
  `deposit_amount` decimal(15,2) NOT NULL DEFAULT 0,
  `discount` decimal(15,2) NOT NULL DEFAULT 0,
  `final_amount` decimal(15,2) NOT NULL DEFAULT 0,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `status` enum('reserved','picked_up','returned','cancelled','overdue') NOT NULL DEFAULT 'reserved',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rental_code` (`rental_code`),
  KEY `customer_id` (`customer_id`),
  KEY `rental_dates` (`receive_date`,`return_date`),
  KEY `status` (`status`),
  CONSTRAINT `rentals_customer_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `rentals_date_check` CHECK (`return_date` >= `receive_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `rentals` (`id`,`rental_code`,`customer_id`,`receive_date`,`return_date`,`rental_amount`,`deposit_amount`,`discount`,`final_amount`,`paid_amount`,`payment_status`,`status`,`note`,`created_at`) VALUES
(1,'TP260716001',1,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 2 DAY),3600000,5000000,0,8600000,5000000,'partial','reserved','Thử lại váy trước khi nhận',NOW()),
(2,'TP260716002',2,DATE_SUB(CURDATE(),INTERVAL 1 DAY),CURDATE(),1800000,4000000,0,5800000,5800000,'paid','picked_up','Nhận lúc 9 giờ',NOW()),
(3,'TP260716003',3,DATE_SUB(CURDATE(),INTERVAL 5 DAY),DATE_SUB(CURDATE(),INTERVAL 2 DAY),2800000,3000000,0,5800000,5800000,'paid','returned','Đã trả đủ',DATE_SUB(NOW(),INTERVAL 6 DAY)),
(4,'TP260716004',4,DATE_SUB(CURDATE(),INTERVAL 3 DAY),DATE_SUB(CURDATE(),INTERVAL 1 DAY),1800000,4000000,100000,5700000,2000000,'partial','overdue','Đã gọi nhắc khách trả',DATE_SUB(NOW(),INTERVAL 4 DAY));
CREATE TABLE `rental_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rental_id` int NOT NULL,
  `costume_id` int NOT NULL,
  `quantity` int NOT NULL DEFAULT 1,
  `selected_size` varchar(50) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0,
  `rental_days` int NOT NULL DEFAULT 1,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0,
  `deposit_amount` decimal(15,2) NOT NULL DEFAULT 0,
  `condition_out` varchar(500) DEFAULT NULL,
  `condition_in` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rental_id` (`rental_id`),
  KEY `costume_id` (`costume_id`),
  CONSTRAINT `rental_items_rental_fk` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rental_items_costume_fk` FOREIGN KEY (`costume_id`) REFERENCES `costumes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `rental_items` (`rental_id`,`costume_id`,`quantity`,`selected_size`,`unit_price`,`rental_days`,`subtotal`,`deposit_amount`,`condition_out`,`condition_in`) VALUES
(1,1,1,'M',1200000,3,3600000,5000000,'Tốt, đủ phụ kiện',NULL),
(2,2,2,'L',450000,2,1800000,4000000,'Tốt, đủ áo và quần',NULL),
(3,3,2,'M',350000,4,2800000,3000000,'Tốt','Đã trả đủ, không hư hỏng'),
(4,4,1,'S',900000,2,1800000,4000000,'Tốt, đủ phụ kiện',NULL);
CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rental_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_type` enum('deposit','rental','refund') NOT NULL DEFAULT 'rental',
  `method` enum('cash','bank','card') NOT NULL DEFAULT 'cash',
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `rental_id` (`rental_id`),
  CONSTRAINT `payments_rental_fk` FOREIGN KEY (`rental_id`) REFERENCES `rentals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `payments` (`rental_id`,`amount`,`payment_type`,`method`,`note`,`created_at`) VALUES
(1,5000000,'deposit','bank','Khách chuyển khoản tiền cọc',NOW()),
(2,4000000,'deposit','cash','Thu tiền cọc',DATE_SUB(NOW(),INTERVAL 1 DAY)),
(2,1800000,'rental','bank','Thanh toán tiền thuê',NOW()),
(3,3000000,'deposit','bank','Thu tiền cọc',DATE_SUB(NOW(),INTERVAL 6 DAY)),
(3,2800000,'rental','cash','Thanh toán đủ tiền thuê',DATE_SUB(NOW(),INTERVAL 5 DAY)),
(4,2000000,'deposit','cash','Thu một phần tiền cọc',DATE_SUB(NOW(),INTERVAL 4 DAY));
COMMIT;