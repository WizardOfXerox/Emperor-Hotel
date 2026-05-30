USE emperors_hotel_db;

UPDATE rooms
SET room_type = 'Imperial Deluxe'
WHERE room_type = 'Standard';

ALTER TABLE rooms
MODIFY room_type ENUM('Imperial Deluxe', 'Royal Executive', 'Emperor Presidential') NOT NULL;

UPDATE rooms
SET status = 'Available'
WHERE status NOT IN ('Available', 'Reserved', 'Occupied');

ALTER TABLE rooms
MODIFY status ENUM('Available', 'Reserved', 'Occupied') NOT NULL DEFAULT 'Available';

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
('312', 'Emperor Presidential', 3, 12500.00, 'Available')
ON DUPLICATE KEY UPDATE
    room_type = VALUES(room_type),
    floor = VALUES(floor),
    price_per_night = VALUES(price_per_night),
    status = VALUES(status);
