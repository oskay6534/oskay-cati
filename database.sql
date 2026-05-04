CREATE DATABASE IF NOT EXISTS osman_cati CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE osman_cati;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    img VARCHAR(255) NOT NULL
);


INSERT INTO projects (title, type, img) VALUES 
('Villa Çatı Uygulaması', 'Kiremit Kaplama', 'img/1.jpg'),
('Endüstriyel İzolasyon', 'Sandviç Panel', 'img/2.jpg');

CREATE TABLE IF NOT EXISTS about_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    img VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0
);

INSERT INTO about_images (img, sort_order) VALUES 
('img/8.jpg', 1), 
('img/9.jpg', 2), 
('img/1.jpg', 3);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    date VARCHAR(100) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE
);
