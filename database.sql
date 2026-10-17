CREATE DATABASE IF NOT EXISTS osman_cati CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE osman_cati;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS seo_settings (
    id INT PRIMARY KEY DEFAULT 1,
    meta_title VARCHAR(255) NOT NULL,
    meta_description TEXT NOT NULL,
    meta_keywords TEXT NOT NULL,
    og_title VARCHAR(255) NOT NULL,
    og_description TEXT NOT NULL,
    og_image VARCHAR(255) NOT NULL,
    canonical_url VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO seo_settings (id, meta_title, meta_description, meta_keywords, og_title, og_description, og_image, canonical_url)
VALUES (
    1,
    'İstanbul Çatı Tamiri ve Çatı Sistemleri | Oskay Çatı',
    'İstanbul''da çatı tamiri, yeni çatı yapımı, su yalıtımı, kenet çatı ve çelik çatı sistemleri. Ücretsiz keşif ve garantili işçilik için Oskay Çatı.',
    'istanbul çatı firması, istanbul çatı tamiri, istanbul çatı sistemleri, çatı ustası, çatı yapımı, çatı izolasyonu, kenet çatı, çelik çatı, çelik konstrüksiyon, oskay çatı',
    'İstanbul Çatı Tamiri ve Çatı Sistemleri | Oskay Çatı',
    'İstanbul''da çatı tamiri, çatı yapımı, su yalıtımı, kenet ve çelik çatı çözümleri.',
    'https://www.oskaycati.com/img/9.jpg',
    'https://www.oskaycati.com/'
)
ON DUPLICATE KEY UPDATE id = id;

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    img VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0
);


INSERT INTO projects (title, type, img, sort_order) VALUES
('Villa Çatı Uygulaması', 'Kiremit Kaplama', 'img/1.jpg', 1),
('Endüstriyel İzolasyon', 'Sandviç Panel', 'img/2.jpg', 2);

CREATE TABLE IF NOT EXISTS about_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    img VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0
);

INSERT INTO about_images (img, sort_order) VALUES 
('img/8.jpg', 1),
('img/9.jpg', 2),
('img/1.jpg', 3);

CREATE TABLE IF NOT EXISTS gallery_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(80) NOT NULL,
    img VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    date VARCHAR(100) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE
);
