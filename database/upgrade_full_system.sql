-- Database Upgrade Script for Emperor Hotel Full-System Features
USE emperors_hotel_db;

-- 1. Upgrade rooms table status ENUM and add bed_type, max_capacity, view_type
ALTER TABLE rooms 
MODIFY COLUMN status ENUM('Available', 'Reserved', 'Occupied', 'Cleaning', 'Maintenance') NOT NULL DEFAULT 'Available';

ALTER TABLE rooms 
ADD COLUMN bed_type VARCHAR(50) DEFAULT 'King Bed',
ADD COLUMN max_capacity INT NOT NULL DEFAULT 2,
ADD COLUMN view_type VARCHAR(100) DEFAULT 'City View';

-- Update existing seed rooms with realistic bed types, capacities, and view types per room type
UPDATE rooms SET bed_type = 'Queen Bed', max_capacity = 2, view_type = 'City Skyline View' WHERE room_type = 'Imperial Deluxe';
UPDATE rooms SET bed_type = 'King Bed', max_capacity = 4, view_type = 'Garden Terrace View' WHERE room_type = 'Royal Executive';
UPDATE rooms SET bed_type = 'Super King Master Suite', max_capacity = 6, view_type = 'Panoramic Ocean View' WHERE room_type = 'Emperor Presidential';

-- 2. Upgrade users table to support SMTP email verification, OTP codes, and password resets
ALTER TABLE users 
ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN otp_code VARCHAR(10) DEFAULT NULL,
ADD COLUMN otp_expires_at DATETIME DEFAULT NULL,
ADD COLUMN reset_token VARCHAR(100) DEFAULT NULL;

-- 3. Create room_reviews table for verified guest ratings (1 to 5 stars) and feedback
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
