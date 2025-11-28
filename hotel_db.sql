-- Create database
CREATE DATABASE IF NOT EXISTS unicorn_hotel;

USE unicorn_hotel;

-- Rooms table
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_type VARCHAR(50) NOT NULL,
    room_number VARCHAR(10) UNIQUE,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    capacity INT NOT NULL,
    amenities TEXT,
    image_url VARCHAR(255),
    available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reservations table
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(20) UNIQUE NOT NULL,
    room_id INT,
    guest_name VARCHAR(100) NOT NULL,
    guest_email VARCHAR(100) NOT NULL,
    guest_phone VARCHAR(20),
    guest_address TEXT,
    special_requests TEXT,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    guests INT NOT NULL,
    total_amount DECIMAL(10, 2),
    status ENUM(
        'pending',
        'confirmed',
        'checked_in',
        'checked_out',
        'cancelled'
    ) DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE SET NULL
);

-- Admin users table
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'manager') DEFAULT 'admin',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample room data
INSERT INTO
    rooms (
        room_type,
        room_number,
        description,
        price,
        capacity,
        amenities
    )
VALUES (
        'standard',
        '101',
        'Perfect for solo travelers or couples looking for comfort at an affordable price. Features a comfortable queen-size bed and modern amenities.',
        15000.00,
        2,
        'Queen Bed, Private Bathroom, Free Wi-Fi, Air Conditioning, TV, Mini Fridge'
    ),
    (
        'standard',
        '102',
        'Perfect for solo travelers or couples looking for comfort at an affordable price. Features a comfortable queen-size bed and modern amenities.',
        15000.00,
        2,
        'Queen Bed, Private Bathroom, Free Wi-Fi, Air Conditioning, TV, Mini Fridge'
    ),
    (
        'deluxe',
        '201',
        'Spacious accommodation with additional amenities for a more comfortable stay. Enjoy the private balcony with beautiful views.',
        25000.00,
        2,
        'King Bed, Private Balcony, Mini Bar, Smart TV, Free Wi-Fi, Air Conditioning'
    ),
    (
        'deluxe',
        '202',
        'Spacious accommodation with additional amenities for a more comfortable stay. Enjoy the private balcony with beautiful views.',
        25000.00,
        2,
        'King Bed, Private Balcony, Mini Bar, Smart TV, Free Wi-Fi, Air Conditioning'
    ),
    (
        'suite',
        '301',
        'Luxurious suite with separate living area, perfect for business travelers or special occasions. Features premium amenities and stunning city views.',
        40000.00,
        3,
        'Separate Living Area, Work Desk, Premium Amenities, City View, King Bed, Smart TV'
    ),
    (
        'presidential',
        '401',
        'The ultimate luxury experience with spacious rooms, premium furnishings, and exclusive services. Perfect for VIP guests and special celebrations.',
        75000.00,
        4,
        'Living Room, Dining Area, Jacuzzi, Premium Amenities, Panoramic View, King Bed, Smart TV'
    );

-- Insert sample admin user (password: admin123)
INSERT INTO
    admin_users (
        username,
        password_hash,
        full_name,
        email,
        role
    )
VALUES (
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'System Administrator',
        'admin@unicornhotel.com',
        'admin'
    );

-- Insert sample reservations
INSERT INTO
    reservations (
        booking_ref,
        room_id,
        guest_name,
        guest_email,
        guest_phone,
        check_in,
        check_out,
        guests,
        total_amount,
        status
    )
VALUES (
        'UNI5F8A3B2C1D4E',
        1,
        'John Smith',
        'john.smith@example.com',
        '+2348012345678',
        DATE_ADD(CURDATE(), INTERVAL 5 DAY),
        DATE_ADD(CURDATE(), INTERVAL 7 DAY),
        2,
        30000.00,
        'confirmed'
    ),
    (
        'UNI6G9B4C3D2E5F',
        3,
        'Sarah Johnson',
        'sarah.j@example.com',
        '+2348023456789',
        DATE_ADD(CURDATE(), INTERVAL 3 DAY),
        DATE_ADD(CURDATE(), INTERVAL 6 DAY),
        2,
        75000.00,
        'confirmed'
    );