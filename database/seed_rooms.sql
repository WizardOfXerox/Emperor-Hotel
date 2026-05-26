USE emperors_hotel_db;

UPDATE rooms
SET room_type = 'Imperial Deluxe'
WHERE room_type = 'Standard';

ALTER TABLE rooms
MODIFY room_type ENUM('Imperial Deluxe', 'Royal Executive', 'Emperor Presidential') NOT NULL;

INSERT INTO rooms (room_number, room_type, floor, capacity_adults, capacity_children, price_per_night, status, description) VALUES
('101', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('102', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('103', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('104', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('105', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('106', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('107', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('108', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('109', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('110', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('111', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('112', 'Imperial Deluxe', 1, 2, 1, 4500.00, 'Available', 'A polished deluxe room with a warm luxury feel, ideal for couples, solo travelers, and business guests.'),
('201', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('202', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('203', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('204', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('205', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('206', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('207', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('208', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('209', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('210', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('211', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('212', 'Royal Executive', 2, 2, 2, 7500.00, 'Available', 'A spacious executive suite with extra privacy, work space, and elevated comfort for premium business stays.'),
('301', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('302', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('303', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('304', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('305', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('306', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('307', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('308', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('309', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('310', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('311', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.'),
('312', 'Emperor Presidential', 3, 4, 2, 12500.00, 'Available', 'The signature luxury suite with grand private spaces, dramatic interiors, and comfort for VIP stays or celebrations.')
ON DUPLICATE KEY UPDATE
    room_type = VALUES(room_type),
    floor = VALUES(floor),
    capacity_adults = VALUES(capacity_adults),
    capacity_children = VALUES(capacity_children),
    price_per_night = VALUES(price_per_night),
    status = VALUES(status),
    description = VALUES(description);
