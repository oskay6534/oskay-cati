<?php
$env = [];
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
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

function pageEnvValue($key, $default = '') {
    global $env;
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    return $env[$key] ?? $default;
}

function esc($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$defaults = [
    'meta_title' => 'Oskay Çatı Sistemleri | İstanbul Çatı Tamiri ve İzolasyon Ustası',
    'meta_description' => "İstanbul'da profesyonel çatı tamiri, izolasyon ve yeni çatı yapımı hizmetleri. 10 yıllık tecrübe ile garantili işçilik. Ücretsiz keşif için hemen arayın!",
    'meta_keywords' => 'çatı tamiri, istanbul çatı ustası, çatı izolasyon, yeni çatı yapımı, çelik çatı, oskay çatı',
    'og_title' => 'Oskay Çatı Sistemleri | İstanbul Çatı Tamiri',
    'og_description' => 'Profesyonel çatı çözümleri, tamir ve izolasyon hizmetleri.',
    'og_image' => 'https://www.oskaycati.com/img/9.jpg',
    'canonical_url' => 'https://www.oskaycati.com/'
];

$seo = $defaults;

try {
    $pdo = new PDO(
        'mysql:host=' . pageEnvValue('MYSQLHOST', 'localhost') . ';port=' . pageEnvValue('MYSQLPORT', 3306) . ';dbname=' . pageEnvValue('MYSQLDATABASE', 'osman_cati') . ';charset=utf8mb4',
        pageEnvValue('MYSQLUSER', 'root'),
        pageEnvValue('MYSQLPASSWORD', '')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
    $stmt->execute(array_values($defaults));

    $stmt = $pdo->query("SELECT meta_title, meta_description, meta_keywords, og_title, og_description, og_image, canonical_url FROM seo_settings WHERE id = 1");
    $seo = array_merge($seo, $stmt->fetch(PDO::FETCH_ASSOC) ?: []);
} catch (Throwable $e) {
    $seo = $defaults;
}

$html = file_get_contents(__DIR__ . '/index.html');
$replacements = [
    '/<title>.*?<\/title>/s' => '<title>' . esc($seo['meta_title']) . '</title>',
    '/<meta name="description" content=".*?">/s' => '<meta name="description" content="' . esc($seo['meta_description']) . '">',
    '/<meta name="keywords" content=".*?">/s' => '<meta name="keywords" content="' . esc($seo['meta_keywords']) . '">',
    '/<link rel="canonical" href=".*?">/s' => '<link rel="canonical" href="' . esc($seo['canonical_url']) . '">',
    '/<meta property="og:url" content=".*?">/s' => '<meta property="og:url" content="' . esc($seo['canonical_url']) . '">',
    '/<meta property="og:title" content=".*?">/s' => '<meta property="og:title" content="' . esc($seo['og_title']) . '">',
    '/<meta property="og:description" content=".*?">/s' => '<meta property="og:description" content="' . esc($seo['og_description']) . '">',
    '/<meta property="og:image" content=".*?">/s' => '<meta property="og:image" content="' . esc($seo['og_image']) . '">'
];

echo preg_replace(array_keys($replacements), array_values($replacements), $html);
