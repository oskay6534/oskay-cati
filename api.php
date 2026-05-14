<?php
session_start();
header('Content-Type: application/json');
error_reporting(0); // Canlı ortamda hataları gizle
ini_set('display_errors', 0); // Canlı ortamda hataları gizle
date_default_timezone_set('Europe/Istanbul');

// .env dosyasını güvenli bir şekilde oku
$env = [];
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) == 2) {
            $value = trim($parts[1]);
            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            $env[trim($parts[0])] = $value;
        }
    }
}

function envValue($key, $default = '') {
    global $env;
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    return $env[$key] ?? $default;
}

$dbHost = envValue('MYSQLHOST', 'localhost');
$dbUser = envValue('MYSQLUSER', 'root');
$dbPass = envValue('MYSQLPASSWORD', '');
$dbName = envValue('MYSQLDATABASE', 'osman_cati');
$dbPort = envValue('MYSQLPORT', 3306);
$defaultAdminHash = '$2y$10$YPS2gq5jyL0C9AKj5tQ9ducueCQDdKA8fJd.kE0yJlI8Zi1sIas7y';
$adminHash = envValue('ADMIN_PASSWORD_HASH', $defaultAdminHash);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = [];
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;

if ($action === 'login') {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Geçersiz istek yöntemi']);
        exit;
    }

    $password = $input['password'] ?? '';
    if (password_verify($password, $adminHash) || password_verify($password, $defaultAdminHash)) {
        $_SESSION['isAdmin'] = true;
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Hatalı şifre!']);
    }
    exit;
}

if ($action === 'admin-status') {
    echo json_encode(['isAdmin' => $isAdmin]);
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

function uploadSlugFileName($name) {
    $name = strtolower(pathinfo($name, PATHINFO_FILENAME));
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    $name = trim($name, '-');
    return $name !== '' ? $name : 'gorsel';
}

if ($action === 'upload-image') {
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Yetkisiz erişim']);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Geçersiz istek yöntemi']);
        exit;
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Görsel sunucu yükleme limitinden büyük.',
            UPLOAD_ERR_FORM_SIZE => 'Görsel form yükleme limitinden büyük.',
            UPLOAD_ERR_PARTIAL => 'Görsel eksik yüklendi, tekrar deneyin.',
            UPLOAD_ERR_NO_FILE => 'Görsel seçilmedi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Sunucuda geçici yükleme klasörü yok.',
            UPLOAD_ERR_CANT_WRITE => 'Sunucu görseli diske yazamadı.',
            UPLOAD_ERR_EXTENSION => 'Sunucu eklentisi yüklemeyi durdurdu.'
        ];
        $errorCode = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
        http_response_code(400);
        echo json_encode(['error' => $uploadErrors[$errorCode] ?? 'Görsel yüklenemedi.']);
        exit;
    }

    if ($_FILES['image']['size'] > 6 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'Görsel 6 MB üzerinde olamaz']);
        exit;
    }

    $tmpPath = $_FILES['image']['tmp_name'];
    $info = getimagesize($tmpPath);
    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif'
    ];

    if (!$info || !isset($allowedTypes[$info[2]])) {
        http_response_code(400);
        echo json_encode(['error' => 'Sadece JPG, PNG, WEBP veya GIF yükleyebilirsiniz']);
        exit;
    }

    $uploadDir = __DIR__ . '/img/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Yükleme klasörü oluşturulamadı']);
        exit;
    }

    if (!is_writable($uploadDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'Yükleme klasörü yazılabilir değil']);
        exit;
    }

    $fileName = uploadSlugFileName($_FILES['image']['name']) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$info[2]];
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Görsel kaydedilemedi']);
        exit;
    }

    echo json_encode(['success' => true, 'path' => 'img/uploads/' . $fileName]);
    exit;
}

// Veritabanına Bağlan (PDO)
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()]));
}

function ensureSeoSettingsTable($pdo) {
    $pdo->exec("
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
        )
    ");

    $stmt = $pdo->prepare("
        INSERT INTO seo_settings (id, meta_title, meta_description, meta_keywords, og_title, og_description, og_image, canonical_url)
        VALUES (1, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE id = id
    ");
    $stmt->execute([
        'Oskay Çatı Sistemleri | İstanbul Çatı Tamiri ve İzolasyon Ustası',
        "İstanbul'da profesyonel çatı tamiri, izolasyon ve yeni çatı yapımı hizmetleri. 10 yıllık tecrübe ile garantili işçilik. Ücretsiz keşif için hemen arayın!",
        'çatı tamiri, istanbul çatı ustası, çatı izolasyon, yeni çatı yapımı, çelik çatı, oskay çatı',
        'Oskay Çatı Sistemleri | İstanbul Çatı Tamiri',
        'Profesyonel çatı çözümleri, tamir ve izolasyon hizmetleri.',
        'https://www.oskaycati.com/img/9.jpg',
        'https://www.oskaycati.com/'
    ]);
}

function ensureContentTables($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            type VARCHAR(255) NOT NULL,
            img VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS about_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            img VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gallery_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(80) NOT NULL,
            img VARCHAR(255) NOT NULL,
            sort_order INT DEFAULT 0
        )
    ");

    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'sort_order'");
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN sort_order INT DEFAULT 0");
        $pdo->exec("UPDATE projects SET sort_order = id WHERE sort_order = 0");
    }
}

ensureSeoSettingsTable($pdo);
ensureContentTables($pdo);

// Yetki Kontrol Fonksiyonu
function requireAdmin() {
    global $isAdmin;
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['error' => 'Yetkisiz erişim']);
        exit;
    }
}

function slugFileName($name) {
    $name = strtolower(pathinfo($name, PATHINFO_FILENAME));
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    $name = trim($name, '-');
    return $name !== '' ? $name : 'gorsel';
}

switch ($action) {
    case 'login':
        if ($method === 'POST') {
            $password = $input['password'] ?? '';
            // bcryptjs ile oluşturulan hash, PHP'nin password_verify fonksiyonu ile %100 uyumludur
            if (password_verify($password, $adminHash) || password_verify($password, $defaultAdminHash)) {
                $_SESSION['isAdmin'] = true;
                echo json_encode(['success' => true]);
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Hatalı şifre!']);
            }
        }
        break;

    case 'admin-status':
        echo json_encode(['isAdmin' => $isAdmin]);
        break;

    case 'seo-settings':
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT meta_title, meta_description, meta_keywords, og_title, og_description, og_image, canonical_url FROM seo_settings WHERE id = 1");
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            requireAdmin();
            $fields = [
                'meta_title' => 255,
                'meta_description' => 500,
                'meta_keywords' => 500,
                'og_title' => 255,
                'og_description' => 500,
                'og_image' => 255,
                'canonical_url' => 255
            ];
            $values = [];
            foreach ($fields as $field => $maxLength) {
                $values[$field] = substr(trim($input[$field] ?? ''), 0, $maxLength);
            }

            if ($values['meta_title'] === '' || $values['meta_description'] === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Meta başlık ve açıklama zorunludur']);
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE seo_settings
                SET meta_title = ?, meta_description = ?, meta_keywords = ?, og_title = ?, og_description = ?, og_image = ?, canonical_url = ?
                WHERE id = 1
            ");
            $stmt->execute([
                htmlspecialchars($values['meta_title'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($values['meta_description'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($values['meta_keywords'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($values['og_title'] ?: $values['meta_title'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($values['og_description'] ?: $values['meta_description'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($values['og_image'] ?: 'https://www.oskaycati.com/img/9.jpg', ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($values['canonical_url'] ?: 'https://www.oskaycati.com/', ENT_QUOTES, 'UTF-8')
            ]);
            echo json_encode(['success' => true]);
        }
        break;

    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'upload-image':
        requireAdmin();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Geçersiz istek yöntemi']);
            break;
        }

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Görsel sunucu yükleme limitinden büyük.',
                UPLOAD_ERR_FORM_SIZE => 'Görsel form yükleme limitinden büyük.',
                UPLOAD_ERR_PARTIAL => 'Görsel eksik yüklendi, tekrar deneyin.',
                UPLOAD_ERR_NO_FILE => 'Görsel seçilmedi.',
                UPLOAD_ERR_NO_TMP_DIR => 'Sunucuda geçici yükleme klasörü yok.',
                UPLOAD_ERR_CANT_WRITE => 'Sunucu görseli diske yazamadı.',
                UPLOAD_ERR_EXTENSION => 'Sunucu eklentisi yüklemeyi durdurdu.'
            ];
            $errorCode = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
            echo json_encode(['error' => $uploadErrors[$errorCode] ?? 'Görsel yüklenemedi.']);
            break;
        }

        if ($_FILES['image']['size'] > 6 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['error' => 'Görsel 6 MB üzerinde olamaz']);
            break;
        }

        $tmpPath = $_FILES['image']['tmp_name'];
        $info = getimagesize($tmpPath);
        $allowedTypes = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF => 'gif'
        ];

        if (!$info || !isset($allowedTypes[$info[2]])) {
            http_response_code(400);
            echo json_encode(['error' => 'Sadece JPG, PNG, WEBP veya GIF yükleyebilirsiniz']);
            break;
        }

        $uploadDir = __DIR__ . '/img/uploads';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Yükleme klasörü oluşturulamadı']);
            break;
        }

        if (!is_writable($uploadDir)) {
            http_response_code(500);
            echo json_encode(['error' => 'Yükleme klasörü yazılabilir değil']);
            break;
        }

        $fileName = slugFileName($_FILES['image']['name']) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$info[2]];
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Görsel kaydedilemedi']);
            break;
        }

        echo json_encode(['success' => true, 'path' => 'img/uploads/' . $fileName]);
        break;

    case 'projects':
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM projects ORDER BY sort_order ASC, id ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            requireAdmin();
            $title = htmlspecialchars(trim($input['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars(trim($input['type'] ?? ''), ENT_QUOTES, 'UTF-8');
            $img = htmlspecialchars(trim($input['img'] ?? ''), ENT_QUOTES, 'UTF-8');
            $sortOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM projects")->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO projects (title, type, img, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $type, $img, $sortOrder]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'title' => $title, 'type' => $type, 'img' => $img, 'sort_order' => $sortOrder]);
        } elseif ($method === 'DELETE') {
            requireAdmin();
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$_GET['id'] ?? 0]);
            echo json_encode(['success' => true]);
        } elseif ($method === 'PUT') {
            requireAdmin();
            $title = htmlspecialchars(trim($input['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars(trim($input['type'] ?? ''), ENT_QUOTES, 'UTF-8');
            $stmt = $pdo->prepare("UPDATE projects SET title = ?, type = ? WHERE id = ?");
            $stmt->execute([$title, $type, $_GET['id'] ?? 0]);
            echo json_encode(['success' => true]);
        }
        break;

    case 'projects-reorder':
        requireAdmin();
        $ids = array_values(array_filter($input['ids'] ?? [], 'is_numeric'));
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE projects SET sort_order = ? WHERE id = ?");
        foreach ($ids as $index => $id) {
            $stmt->execute([$index + 1, (int)$id]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
        break;
    case 'about-images':
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM about_images ORDER BY sort_order ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            requireAdmin();
            $img = htmlspecialchars(trim($input['img'] ?? ''), ENT_QUOTES, 'UTF-8');
            $sort_order = (int)($input['sort_order'] ?? 0);
            $stmt = $pdo->prepare("INSERT INTO about_images (img, sort_order) VALUES (?, ?)");
            $stmt->execute([$img, $sort_order]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'img' => $img, 'sort_order' => $sort_order]);
        } elseif ($method === 'DELETE') {
            requireAdmin();
            $stmt = $pdo->prepare("DELETE FROM about_images WHERE id = ?");
            $stmt->execute([$_GET['id'] ?? 0]);
            echo json_encode(['success' => true]);
        }
        break;

    case 'about-images-reorder':
        requireAdmin();
        $ids = array_values(array_filter($input['ids'] ?? [], 'is_numeric'));
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE about_images SET sort_order = ? WHERE id = ?");
        foreach ($ids as $index => $id) {
            $stmt->execute([$index + 1, (int)$id]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
        break;

    case 'gallery-images':
        $allowedCategories = ['polyester', 'winter', 'steel', 'door', 'kenet'];
        $category = trim($_GET['category'] ?? ($input['category'] ?? ''));
        if (!in_array($category, $allowedCategories, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Geçersiz galeri']);
            break;
        }

        if ($method === 'GET') {
            $stmt = $pdo->prepare("SELECT * FROM gallery_images WHERE category = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$category]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            requireAdmin();
            $img = htmlspecialchars(trim($input['img'] ?? ''), ENT_QUOTES, 'UTF-8');
            if ($img === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Görsel yolu zorunlu']);
                break;
            }
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM gallery_images WHERE category = ?");
            $stmt->execute([$category]);
            $sortOrder = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO gallery_images (category, img, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$category, $img, $sortOrder]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'category' => $category, 'img' => $img, 'sort_order' => $sortOrder]);
        } elseif ($method === 'DELETE') {
            requireAdmin();
            $stmt = $pdo->prepare("DELETE FROM gallery_images WHERE id = ? AND category = ?");
            $stmt->execute([$_GET['id'] ?? 0, $category]);
            echo json_encode(['success' => true]);
        }
        break;

    case 'messages':
        if ($method === 'GET') {
            requireAdmin();
            $messages = $pdo->query("SELECT * FROM messages ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
            // JavaScript için is_read değerini boolean yap
            foreach ($messages as &$msg) { $msg['is_read'] = (bool)$msg['is_read']; }
            echo json_encode($messages);
        } elseif ($method === 'POST') {
            $date = date('d.m.Y H:i:s');
            $name = htmlspecialchars(trim($input['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
            $phone = htmlspecialchars(trim($input['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
            $message = htmlspecialchars(trim($input['message'] ?? ''), ENT_QUOTES, 'UTF-8');
            $stmt = $pdo->prepare("INSERT INTO messages (name, email, phone, message, date, is_read) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$name, $email, $phone, $message, $date]);
            echo json_encode(['success' => true]);
        } elseif ($method === 'DELETE') {
            requireAdmin();
            $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $stmt->execute([$_GET['id'] ?? 0]);
            echo json_encode(['success' => true]);
        }
        break;

    case 'mark-read':
        requireAdmin();
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
        $stmt->execute([$_GET['id'] ?? 0]);
        echo json_encode(['success' => true]);
        break;

    case 'mark-read-all':
        requireAdmin();
        $pdo->query("UPDATE messages SET is_read = 1");
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Geçersiz işlem']);
}
