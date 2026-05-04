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
$adminHash = envValue('ADMIN_PASSWORD_HASH', '');

// Veritabanına Bağlan (PDO)
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()]));
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = [];
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}
$isAdmin = isset($_SESSION['isAdmin']) && $_SESSION['isAdmin'] === true;

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
            if (password_verify($password, $adminHash)) {
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
            echo json_encode(['error' => 'Görsel yüklenemedi']);
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
            $stmt = $pdo->query("SELECT * FROM projects ORDER BY id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } elseif ($method === 'POST') {
            requireAdmin();
            $title = htmlspecialchars(trim($input['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars(trim($input['type'] ?? ''), ENT_QUOTES, 'UTF-8');
            $img = htmlspecialchars(trim($input['img'] ?? ''), ENT_QUOTES, 'UTF-8');
            $stmt = $pdo->prepare("INSERT INTO projects (title, type, img) VALUES (?, ?, ?)");
            $stmt->execute([$title, $type, $img]);
            echo json_encode(['id' => $pdo->lastInsertId(), 'title' => $title, 'type' => $type, 'img' => $img]);
        } elseif ($method === 'DELETE') {
            requireAdmin();
            $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$_GET['id'] ?? 0]);
            echo json_encode(['success' => true]);
        }
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
