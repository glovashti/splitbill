-- Create Database
CREATE DATABASE IF NOT EXISTS db_splitbill;
USE db_splitbill;

-- 1. Table: users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table: bills
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    tax_percent DECIMAL(5,2) DEFAULT 0.00,
    service_percent DECIMAL(5,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table: members
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table: items
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Table: item_shares (Junction table for many-to-many relationship)
CREATE TABLE IF NOT EXISTS item_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    member_id INT NOT NULL,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_share (item_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- VIEWS

-- View 1: Menghitung porsi subtotal per orang untuk setiap makanan yang dikonsumsinya
CREATE OR REPLACE VIEW view_member_shares AS
SELECT 
    ish.id AS share_id,
    m.bill_id,
    m.id AS member_id,
    m.name AS member_name,
    i.id AS item_id,
    i.item_name,
    i.price,
    i.qty,
    (i.price * i.qty) AS item_total,
    (SELECT COUNT(*) FROM item_shares WHERE item_id = i.id) AS total_sharers,
    ((i.price * i.qty) / (SELECT COUNT(*) FROM item_shares WHERE item_id = i.id)) AS share_amount
FROM item_shares ish
JOIN members m ON ish.member_id = m.id
JOIN items i ON ish.item_id = i.id;

-- View 2: Menghitung ringkasan grand total per bill secara realtime
CREATE OR REPLACE VIEW view_bill_totals AS
SELECT 
    b.id AS bill_id,
    b.title,
    b.tax_percent,
    b.service_percent,
    COALESCE(SUM(i.price * i.qty), 0) AS subtotal_amount,
    COALESCE(SUM(i.price * i.qty), 0) * (b.tax_percent / 100) AS tax_amount,
    COALESCE(SUM(i.price * i.qty), 0) * (b.service_percent / 100) AS service_amount,
    COALESCE(SUM(i.price * i.qty), 0) * (1 + (b.tax_percent / 100) + (b.service_percent / 100)) AS grand_total_amount
FROM bills b
LEFT JOIN items i ON b.id = i.bill_id
GROUP BY b.id;

-- FUNCTIONS

-- Function 1: Menghitung total setelah pajak & biaya servis
DROP FUNCTION IF EXISTS fn_calculate_grand_total;
DELIMITER $$
CREATE FUNCTION fn_calculate_grand_total(
    subtotal DECIMAL(10,2), 
    tax_pct DECIMAL(5,2), 
    service_pct DECIMAL(5,2)
) 
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    RETURN subtotal * (1 + (tax_pct / 100) + (service_pct / 100));
END$$
DELIMITER ;

-- Function 2: Menghitung jumlah sharer untuk suatu item
DROP FUNCTION IF EXISTS fn_count_sharers;
DELIMITER $$
CREATE FUNCTION fn_count_sharers(p_item_id INT) 
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE cnt INT;
    SELECT COUNT(*) INTO cnt FROM item_shares WHERE item_id = p_item_id;
    RETURN cnt;
END$$
DELIMITER ;

-- TRIGGERS

-- Trigger 1: Sinkronisasi total_amount di tabel bills setelah input item baru
DROP TRIGGER IF EXISTS trg_after_item_insert;
DELIMITER $$
CREATE TRIGGER trg_after_item_insert
AFTER INSERT ON items
FOR EACH ROW
BEGIN
    UPDATE bills b
    SET b.total_amount = (
        SELECT COALESCE(SUM(i.price * i.qty), 0) * (1 + (b.tax_percent / 100) + (b.service_percent / 100))
        FROM items i
        WHERE i.bill_id = NEW.bill_id
    )
    WHERE b.id = NEW.bill_id;
END$$
DELIMITER ;

-- Trigger 2: Sinkronisasi total_amount di tabel bills setelah edit item
DROP TRIGGER IF EXISTS trg_after_item_update;
DELIMITER $$
CREATE TRIGGER trg_after_item_update
AFTER UPDATE ON items
FOR EACH ROW
BEGIN
    UPDATE bills b
    SET b.total_amount = (
        SELECT COALESCE(SUM(i.price * i.qty), 0) * (1 + (b.tax_percent / 100) + (b.service_percent / 100))
        FROM items i
        WHERE i.bill_id = NEW.bill_id
    )
    WHERE b.id = NEW.bill_id;
END$$
DELIMITER ;

-- Trigger 3: Sinkronisasi total_amount di tabel bills setelah hapus item
DROP TRIGGER IF EXISTS trg_after_item_delete;
DELIMITER $$
CREATE TRIGGER trg_after_item_delete
AFTER DELETE ON items
FOR EACH ROW
BEGIN
    UPDATE bills b
    SET b.total_amount = (
        SELECT COALESCE(SUM(i.price * i.qty), 0) * (1 + (b.tax_percent / 100) + (b.service_percent / 100))
        FROM items i
        WHERE i.bill_id = OLD.bill_id
    )
    WHERE b.id = OLD.bill_id;
END$$
DELIMITER ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`) VALUES
(3, 'admin', 'admin@mail.com', '$2y$10$7I1sdm2UoA/qQGoj3SXCEuKXHvdZkUTH1ztUAnsKbdLdyktNJ1FAe', '2026-06-16 09:03:56');

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `user_id`, `title`, `tax_percent`, `service_percent`, `total_amount`, `created_at`) VALUES
(9, 3, 'Suharti', 10.00, 5.00, 92000.00, '2026-06-16 10:06:48'),
(10, 3, 'MCD', 11.00, 5.00, 12760.00, '2026-06-16 10:09:04'),
(11, 3, 'WARUNG MAHAL', 10.00, 5.00, 34500.00, '2026-06-16 10:11:06'),
(12, 3, 'makan malam', 10.00, 5.00, 241500.00, '2026-06-16 10:12:32'),
(13, 3, 'makan sore', 10.00, 5.00, 57500.00, '2026-06-16 10:14:53');

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `bill_id`, `item_name`, `price`, `qty`) VALUES
(8, 9, 'Ayam Goreng', 40000.00, 2),
(9, 10, 'Nasi Goreng', 4000.00, 1),
(10, 10, 'Telur', 2000.00, 2),
(11, 10, 'Ayam Goreng', 3000.00, 1),
(12, 11, 'mie goreng', 12000.00, 2),
(13, 11, 'es jeruk', 3000.00, 2),
(14, 12, 'ketiaw', 40000.00, 4),
(15, 12, 'es jeruk', 10000.00, 2),
(16, 12, 'es coklat', 15000.00, 2),
(17, 13, 'Ayam Goreng', 30000.00, 1),
(18, 13, 'es jeruk', 10000.00, 2);


--
-- Dumping data for table `item_shares`
--

INSERT INTO `item_shares` (`id`, `item_id`, `member_id`) VALUES
(15, 8, 8),
(16, 8, 9),
(17, 9, 10),
(18, 10, 11),
(19, 10, 12),
(20, 11, 10),
(21, 12, 13),
(22, 12, 14),
(23, 13, 13),
(24, 13, 14),
(25, 14, 15),
(26, 14, 16),
(27, 14, 17),
(28, 14, 18),
(29, 15, 15),
(30, 15, 16),
(31, 16, 17),
(32, 16, 18),
(33, 17, 19),
(34, 18, 19),
(35, 18, 20);

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `bill_id`, `name`) VALUES
(8, 9, 'cici'),
(9, 9, 'Purwanto'),
(10, 10, 'Habibi'),
(11, 10, 'Purwanto'),
(12, 10, 'cici'),
(13, 11, 'Purwanto'),
(14, 11, 'Habibi'),
(15, 12, 'jemes'),
(16, 12, 'pipi'),
(17, 12, 'lili'),
(18, 12, 'lala'),
(19, 13, 'Habibi'),
(20, 13, 'pipi');


