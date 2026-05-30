CREATE DATABASE IF NOT EXISTS emperors_hotel_db;
USE emperors_hotel_db;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE guests (
    guest_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) NOT NULL UNIQUE,
    room_type ENUM('Imperial Deluxe', 'Royal Executive', 'Emperor Presidential') NOT NULL,
    floor INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    status ENUM('Available', 'Reserved', 'Occupied') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (guest_id) REFERENCES guests(guest_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE RESTRICT
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash', 'Credit Card', 'Debit Card', 'Bank Transfer', 'Online Payment', 'Other') NOT NULL DEFAULT 'Cash',
    payment_status ENUM('Pending', 'Confirmed', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
    transaction_reference VARCHAR(100) DEFAULT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE
);

INSERT INTO rooms (room_number, room_type, floor, price_per_night, status) VALUES
('101', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('102', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('103', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('104', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('105', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('106', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('107', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('108', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('109', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('110', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('111', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('112', 'Imperial Deluxe', 1, 4500.00, 'Available'),
('201', 'Royal Executive', 2, 7500.00, 'Available'),
('202', 'Royal Executive', 2, 7500.00, 'Available'),
('203', 'Royal Executive', 2, 7500.00, 'Available'),
('204', 'Royal Executive', 2, 7500.00, 'Available'),
('205', 'Royal Executive', 2, 7500.00, 'Available'),
('206', 'Royal Executive', 2, 7500.00, 'Available'),
('207', 'Royal Executive', 2, 7500.00, 'Available'),
('208', 'Royal Executive', 2, 7500.00, 'Available'),
('209', 'Royal Executive', 2, 7500.00, 'Available'),
('210', 'Royal Executive', 2, 7500.00, 'Available'),
('211', 'Royal Executive', 2, 7500.00, 'Available'),
('212', 'Royal Executive', 2, 7500.00, 'Available'),
('301', 'Emperor Presidential', 3, 12500.00, 'Available'),
('302', 'Emperor Presidential', 3, 12500.00, 'Available'),
('303', 'Emperor Presidential', 3, 12500.00, 'Available'),
('304', 'Emperor Presidential', 3, 12500.00, 'Available'),
('305', 'Emperor Presidential', 3, 12500.00, 'Available'),
('306', 'Emperor Presidential', 3, 12500.00, 'Available'),
('307', 'Emperor Presidential', 3, 12500.00, 'Available'),
('308', 'Emperor Presidential', 3, 12500.00, 'Available'),
('309', 'Emperor Presidential', 3, 12500.00, 'Available'),
('310', 'Emperor Presidential', 3, 12500.00, 'Available'),
('311', 'Emperor Presidential', 3, 12500.00, 'Available'),
('312', 'Emperor Presidential', 3, 12500.00, 'Available');
