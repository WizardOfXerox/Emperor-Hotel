CREATE DATABASE IF NOT EXISTS emperors_hotel_db;
USE emperors_hotel_db;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    otp_code VARCHAR(10) DEFAULT NULL,
    otp_expires_at DATETIME DEFAULT NULL,
    reset_token VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Guests Table
CREATE TABLE IF NOT EXISTS guests (
    guest_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 3. Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10) NOT NULL UNIQUE,
    room_type ENUM('Imperial Deluxe', 'Royal Executive', 'Emperor Presidential') NOT NULL,
    floor INT NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    bed_type VARCHAR(50) DEFAULT 'King Bed',
    max_capacity INT NOT NULL DEFAULT 2,
    view_type VARCHAR(100) DEFAULT 'City View',
    status ENUM('Available', 'Reserved', 'Occupied', 'Cleaning', 'Maintenance') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Reservations Table
CREATE TABLE IF NOT EXISTS reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    guest_id INT NOT NULL,
    room_id INT NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Pending', 'Confirmed', 'Checked-in', 'Checked-out', 'Cancelled', 'Conflict') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (guest_id) REFERENCES guests(guest_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE RESTRICT
);

-- 5. Payments Table
CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash', 'Credit Card', 'Debit Card', 'Bank Transfer', 'E-Wallet', 'Other') NOT NULL DEFAULT 'Cash',
    payment_status ENUM('Pending', 'Confirmed', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
    transaction_reference VARCHAR(100) DEFAULT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE
);

-- 6. Room Reviews Table
CREATE TABLE IF NOT EXISTS room_reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(reservation_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
);

-- Seed Data: 36 Luxury Hotel Rooms
INSERT INTO rooms (room_number, room_type, floor, price_per_night, bed_type, max_capacity, view_type, status) VALUES
('101', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('102', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('103', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('104', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('105', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('106', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('107', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('108', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('109', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('110', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('111', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('112', 'Imperial Deluxe', 1, 4500.00, 'Queen Bed', 2, 'City Skyline View', 'Available'),
('201', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('202', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('203', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('204', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('205', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('206', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('207', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('208', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('209', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('210', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('211', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('212', 'Royal Executive', 2, 7500.00, 'King Bed', 4, 'Garden Terrace View', 'Available'),
('301', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('302', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('303', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('304', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('305', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('306', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('307', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('308', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('309', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('310', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('311', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available'),
('312', 'Emperor Presidential', 3, 12500.00, 'Super King Master Suite', 6, 'Panoramic Ocean View', 'Available')
ON DUPLICATE KEY UPDATE
price_per_night = VALUES(price_per_night),
bed_type = VALUES(bed_type),
max_capacity = VALUES(max_capacity),
view_type = VALUES(view_type);
