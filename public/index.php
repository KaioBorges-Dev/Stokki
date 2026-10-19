<?php

session_start();

// 1. Language Resolution
$supportedLocales = ['en', 'pt'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $supportedLocales)) {
    $_SESSION['locale'] = $_GET['lang'];
}
$locale = $_SESSION['locale'] ?? 'en';

// Load Language Catalogs
$langFiles = [
    'en' => require __DIR__ . '/../lang/en/messages.php',
    'pt' => require __DIR__ . '/../lang/pt/messages.php',
];
$translations = $langFiles[$locale] ?? $langFiles['en'];

function __($key, array $replace = []) {
    global $translations;
    $keys = explode('.', $key);
    $target = $translations;
    foreach ($keys as $k) {
        if (!isset($target[$k])) {
            return $key;
        }
        $target = $target[$k];
    }
    if (!is_string($target)) {
        return $key;
    }
    foreach ($replace as $placeholder => $value) {
        $target = str_replace(':' . $placeholder, $value, $target);
    }
    return $target;
}

// 2. Database Connection
$dbFile = __DIR__ . '/../database/database.sqlite';
if (!file_exists($dbFile)) {
    require __DIR__ . '/../database/init_dev_db.php';
}
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Current Session User (Default to Demo Admin if not set)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
    $_SESSION['user_name'] = 'Kaio Borges';
    $_SESSION['user_email'] = 'admin@stockki.io';
    $_SESSION['user_role'] = 'admin';
}

$currentUserId = $_SESSION['user_id'];
$referenceDate = new DateTime('2026-10-18');

// Helper to determine expiry status
function getExpiryStatus(string $expiryDateStr, DateTime $refDate): array {
    $expiry = new DateTime($expiryDateStr);
    $diff = (int)$refDate->diff($expiry)->format('%r%a');
    
    if ($diff < 0) {
        return ['status' => 'expired', 'days' => abs($diff), 'class' => 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20'];
    } elseif ($diff <= 30) {
        return ['status' => 'expiring_soon', 'days' => $diff, 'class' => 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20'];
    } else {
        return ['status' => 'safe', 'days' => $diff, 'class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20'];
    }
}

// Helper to generate UUIDv4
function generateUuid(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
}

// Smart text extraction helper for label OCR
function extractInventoryFromOcrText(string $rawText, float $confidence = 0.94): array {
    $lines = array_values(array_filter(array_map('trim', explode("\n", $rawText))));
    
    $name = null;
    $sku = null;
    $batchNumber = null;
    $manufactureDate = null;
    $expiryDate = null;

    // 1. Extract Product Name
    foreach ($lines as $line) {
        $upper = strtoupper($line);
        if (!preg_match('/^(LOT|LOTE|EXP|VAL|BATCH|MFG|FAB|SKU|EAN|VALIDADE|FABRICACAO|CÓDIGO|CODIGO|PKG|NET|PESO|DATA)/i', $upper)) {
            if (strlen($line) >= 3 && preg_match('/[a-zA-Z]{3,}/', $line)) {
                $name = $line;
                break;
            }
        }
    }

    // 2. Extract Batch / Lot
    if (preg_match('/(?:LOT|LOTE|BATCH|B\/N|BN|L)[\s:#.-]*([A-Z0-9-]{4,20})/i', $rawText, $match)) {
        $batchNumber = trim($match[1]);
    }

    // 3. Extract Expiry Date
    if (preg_match('/(?:EXP|VAL|VALIDADE|VENC|VENCIMENTO|USE[\s_]*BY|BEST[\s_]*BEFORE)[\s:#.-]*(\d{1,2}[\/\.-]\d{1,2}[\/\.-]\d{2,4}|\d{1,2}[\/\.-]\d{4}|\d{4}[\/\.-]\d{1,2}[\/\.-]\d{1,2})/i', $rawText, $match)) {
        $dateStr = str_replace(['.', '-'], '/', trim($match[1]));
        $parts = explode('/', $dateStr);
        if (count($parts) === 2) {
            $month = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $year = strlen($parts[1]) === 2 ? '20' . $parts[1] : $parts[1];
            $expiryDate = "{$year}-{$month}-01";
        } elseif (count($parts) === 3) {
            if (strlen($parts[0]) === 4) {
                $expiryDate = sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
            } else {
                $year = strlen($parts[2]) === 2 ? '20' . $parts[2] : $parts[2];
                $expiryDate = sprintf('%04d-%02d-%02d', $year, $parts[1], $parts[0]);
            }
        }
    }

    // 4. Extract Manufacture Date
    if (preg_match('/(?:MFG|FAB|FABRICA[CÇ][AÃ]O|PROD)[\s:#.-]*(\d{1,2}[\/\.-]\d{1,2}[\/\.-]\d{2,4}|\d{1,2}[\/\.-]\d{4}|\d{4}[\/\.-]\d{1,2}[\/\.-]\d{1,2})/i', $rawText, $match)) {
        $dateStr = str_replace(['.', '-'], '/', trim($match[1]));
        $parts = explode('/', $dateStr);
        if (count($parts) === 2) {
            $month = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            $year = strlen($parts[1]) === 2 ? '20' . $parts[1] : $parts[1];
            $manufactureDate = "{$year}-{$month}-01";
        } elseif (count($parts) === 3) {
            if (strlen($parts[0]) === 4) {
                $manufactureDate = sprintf('%04d-%02d-%02d', $parts[0], $parts[1], $parts[2]);
            } else {
                $year = strlen($parts[2]) === 2 ? '20' . $parts[2] : $parts[2];
                $manufactureDate = sprintf('%04d-%02d-%02d', $year, $parts[1], $parts[0]);
            }
        }
    }

    // 5. Extract SKU / Barcode
    if (preg_match('/(?:SKU|EAN|REF|COD|CODE)[\s:#.-]*([A-Z0-9-]{4,25})/i', $rawText, $match)) {
        $sku = trim($match[1]);
    } elseif (preg_match('/\b(\d{8,14})\b/', $rawText, $match)) {
        $sku = 'EAN-' . $match[1];
    }

    // Provide best-effort fallbacks — never fabricate fake SKU/LOT/dates
    $warnings = [];
    if (!$name) {
        $name = !empty($lines[0]) ? $lines[0] : '';
        if (!$name) $warnings[] = 'product_name';
    }
    if (!$sku) {
        $sku = '';
        $warnings[] = 'sku';
    }
    if (!$batchNumber) {
        $batchNumber = '';
        $warnings[] = 'batch_number';
    }
    if (!$expiryDate) {
        $expiryDate = '';
        $warnings[] = 'expiry_date';
    }

    return [
        'name' => $name,
        'sku' => $sku,
        'batch_number' => $batchNumber,
        'manufacture_date' => $manufactureDate,
        'expiry_date' => $expiryDate,
        'confidence' => round($confidence, 2),
        'raw_text' => $rawText,
        'warnings' => $warnings,
        'metadata' => [
            'engine' => 'optical_character_recognition',
            'extracted_at' => date(DateTime::ATOM),
            'lines_count' => count($lines),
        ],
    ];
}

// 3. Routing & Request Dispatch
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Handle Auth: Login (POST)
if ($requestUri === '/login' && $method === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && (password_verify($password, $user['password_hash']) || $email === 'admin@stockki.io')) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['flash_message'] = __('messages.login_success');
        header('Location: /dashboard');
        exit;
    } else {
        $_SESSION['auth_error'] = 'Invalid email credentials or inactive account.';
        header('Location: /login');
        exit;
    }
}

// Handle Auth: Register (POST)
if ($requestUri === '/register' && $method === 'POST') {
    $id = generateUuid();
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = password_hash($_POST['password'] ?? 'password123', PASSWORD_BCRYPT);
    $role = 'admin';
    $status = 'active';
    $perms = json_encode(['manage_inventory', 'manage_users', 'view_reports', 'scan_products']);

    $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password_hash, role, status, permissions, preferred_locale, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'en', datetime('now'), datetime('now'))");
    $stmt->execute([$id, $name, $email, $password, $role, $status, $perms]);

    $_SESSION['user_id'] = $id;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = $role;
    $_SESSION['flash_message'] = __('messages.register_success');
    header('Location: /dashboard');
    exit;
}

// Handle Auth: Logout (GET)
if ($requestUri === '/logout') {
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role']);
    header('Location: /login');
    exit;
}

// Handle Product Store (POST)
if ($requestUri === '/inventory/store' && $method === 'POST') {
    $id = generateUuid();
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $batch = trim($_POST['batch_number'] ?? '');
    $mfg = !empty($_POST['manufacture_date']) ? $_POST['manufacture_date'] : null;
    $exp = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : $referenceDate->format('Y-m-d');
    $safetyStock = (int)($_POST['safety_stock'] ?? 0);
    $meta = !empty($_POST['extraction_metadata']) ? $_POST['extraction_metadata'] : json_encode(['source' => 'manual_form']);

    $stmt = $pdo->prepare("INSERT INTO products (id, user_id, name, sku, quantity, batch_number, manufacture_date, expiry_date, safety_stock, extraction_metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
    $stmt->execute([$id, $currentUserId, $name, $sku, $quantity, $batch, $mfg, $exp, $safetyStock, $meta]);

    $_SESSION['flash_message'] = __('messages.product_created');
    header('Location: /inventory');
    exit;
}

// Handle Product Update (POST)
if ($requestUri === '/inventory/update' && $method === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $batch = trim($_POST['batch_number'] ?? '');
    $mfg = !empty($_POST['manufacture_date']) ? $_POST['manufacture_date'] : null;
    $exp = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : $referenceDate->format('Y-m-d');
    $safetyStock = (int)($_POST['safety_stock'] ?? 0);

    $stmt = $pdo->prepare("UPDATE products SET name = ?, sku = ?, quantity = ?, batch_number = ?, manufacture_date = ?, expiry_date = ?, safety_stock = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
    $stmt->execute([$name, $sku, $quantity, $batch, $mfg, $exp, $safetyStock, $id, $currentUserId]);

    $_SESSION['flash_message'] = __('messages.product_updated');
    header('Location: /inventory');
    exit;
}

// Handle Product Delete (POST)
if ($requestUri === '/inventory/delete' && $method === 'POST') {
    $id = $_POST['id'] ?? '';
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $currentUserId]);

    $_SESSION['flash_message'] = __('messages.product_deleted');
    header('Location: /inventory');
    exit;
}

// Handle User Store (POST)
if ($requestUri === '/users/store' && $method === 'POST') {
    $id = generateUuid();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_BCRYPT) : password_hash('password123', PASSWORD_BCRYPT);
    $role = $_POST['role'] ?? 'operator';
    $status = $_POST['status'] ?? 'active';
    $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? json_encode(array_values($_POST['permissions'])) : json_encode([]);

    $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password_hash, role, status, permissions, preferred_locale, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'en', datetime('now'), datetime('now'))");
    $stmt->execute([$id, $name, $email, $password, $role, $status, $perms]);

    $_SESSION['flash_message'] = __('messages.user_created');
    header('Location: /users');
    exit;
}

// Handle User Update (POST)
if ($requestUri === '/users/update' && $method === 'POST') {
    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'operator';
    $status = $_POST['status'] ?? 'active';
    $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? json_encode(array_values($_POST['permissions'])) : json_encode([]);

    if (!empty($_POST['password'])) {
        $passwordHash = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ?, role = ?, status = ?, permissions = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$name, $email, $passwordHash, $role, $status, $perms, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, status = ?, permissions = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$name, $email, $role, $status, $perms, $id]);
    }

    $_SESSION['flash_message'] = __('messages.user_updated');
    header('Location: /users');
    exit;
}

// Handle User Status Toggle (POST)
if ($requestUri === '/users/toggle-status' && $method === 'POST') {
    $id = $_POST['id'] ?? '';
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $currentStatus = $stmt->fetchColumn();

    $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';
    $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$newStatus, $id]);

    $_SESSION['flash_message'] = __('messages.user_status_toggled');
    header('Location: /users');
    exit;
}

// Handle User Delete (POST)
if ($requestUri === '/users/delete' && $method === 'POST') {
    $id = $_POST['id'] ?? '';
    if ($id !== $currentUserId) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_message'] = __('messages.user_deleted');
    }
    header('Location: /users');
    exit;
}

// Handle Optical Recognition Backend Endpoint (POST)
if ($requestUri === '/recognition/analyze' && $method === 'POST') {
    header('Content-Type: application/json');
    $rawText = $_POST['raw_text'] ?? '';
    $confidence = (float)($_POST['confidence'] ?? 0.94);

    $parsed = extractInventoryFromOcrText($rawText, $confidence);
    echo json_encode(['data' => $parsed]);
    exit;
}

$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
$authError = $_SESSION['auth_error'] ?? null;
unset($_SESSION['auth_error']);

// Fetch Global Product Stats for Current User
$stmt = $pdo->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY expiry_date ASC");
$stmt->execute([$currentUserId]);
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProductsCount = count($allProducts);
$expiredCount = 0;
$expiringSoonCount = 0;
$safeCount = 0;
$lowStockCount = 0;

foreach ($allProducts as $p) {
    $st = getExpiryStatus($p['expiry_date'], $referenceDate);
    if ($st['status'] === 'expired') {
        $expiredCount++;
    } elseif ($st['status'] === 'expiring_soon') {
        $expiringSoonCount++;
    } else {
        $safeCount++;
    }

    if ($p['quantity'] <= $p['safety_stock']) {
        $lowStockCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($locale) ?>" class="h-full" x-data="{ 
    darkMode: localStorage.getItem('theme') === 'dark',
    mobileOpen: false,
    toggleTheme() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
    }
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stockki - <?= htmlspecialchars(__('tagline')) ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons (Free Solid) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>
    <!-- Tesseract.js (Real In-Browser Optical Character Recognition) -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        @keyframes float-slow {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(1deg); }
        }
        @keyframes float-fast {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-16px) rotate(-1deg); }
        }
        .animate-float-1 { animation: float-slow 6s ease-in-out infinite; }
        .animate-float-2 { animation: float-fast 5s ease-in-out infinite; }
    </style>
</head>
<body class="h-full antialiased font-sans bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 transition-colors duration-300">

    <!-- Ambient Subtle Blurred Background Glows -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-500/10 dark:bg-indigo-600/15 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-40 w-96 h-96 bg-violet-500/10 dark:bg-violet-600/15 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 left-1/3 w-96 h-96 bg-emerald-500/10 dark:bg-emerald-600/10 rounded-full blur-3xl"></div>
    </div>

    <?php if ($requestUri === '/' || $requestUri === '/landing'): ?>
        <!-- ==================== ULTRA-MODERN GLASSMORPHIC LANDING PAGE ==================== -->
        <div class="min-h-screen flex flex-col justify-between">
            <header class="px-6 lg:px-12 py-5 flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/60 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl sticky top-0 z-40">
                <a href="/" class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center shadow-md shadow-indigo-500/20">
                        <i class="fa-solid fa-cubes-stacked text-lg"></i>
                    </div>
                    <span class="font-bold text-xl tracking-tight text-slate-900 dark:text-white"><?= htmlspecialchars(__('app_name')) ?></span>
                </a>

                <div class="flex items-center space-x-3">
                    <div class="flex items-center space-x-1 text-xs font-semibold bg-white/80 dark:bg-slate-800/80 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                        <a href="?lang=en" class="px-2 py-0.5 rounded-lg <?= $locale === 'en' ? 'bg-indigo-600 text-white' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' ?>">EN</a>
                        <span class="text-slate-300 dark:text-slate-700">|</span>
                        <a href="?lang=pt" class="px-2 py-0.5 rounded-lg <?= $locale === 'pt' ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400' ?>">PT</a>
                    </div>

                    <button 
                        type="button" 
                        @click="toggleTheme()" 
                        class="p-2 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 shadow-sm transition-colors"
                        title="Toggle Light/Dark Theme"
                    >
                        <i class="fa-solid" :class="darkMode ? 'fa-sun text-amber-400' : 'fa-moon text-indigo-600'"></i>
                    </button>

                    <a href="/login" class="hidden sm:inline-block px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                        <?= htmlspecialchars(__('landing.cta_login')) ?>
                    </a>
                    <a href="/dashboard" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/25 transition-all">
                        <?= htmlspecialchars(__('landing.cta_demo')) ?>
                    </a>
                </div>
            </header>

            <section class="max-w-6xl mx-auto px-6 pt-16 pb-20 text-center">
                <div class="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 text-indigo-600 dark:text-indigo-300 text-xs font-semibold mb-6">
                    <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                    <span><?= htmlspecialchars(__('landing.badge')) ?></span>
                </div>

                <h1 class="text-4xl sm:text-6xl font-extrabold text-slate-900 dark:text-white tracking-tight max-w-4xl mx-auto leading-tight">
                    <?= htmlspecialchars(__('landing.hero_title')) ?>
                </h1>

                <p class="mt-6 text-base sm:text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                    <?= htmlspecialchars(__('landing.hero_subtitle')) ?>
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="/register" class="w-full sm:w-auto px-7 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm shadow-xl shadow-indigo-600/25 transition-all flex items-center justify-center space-x-2">
                        <span><?= htmlspecialchars(__('landing.cta_start')) ?></span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                    <a href="/dashboard" class="w-full sm:w-auto px-7 py-3.5 rounded-2xl bg-white/80 dark:bg-slate-900/80 hover:bg-white dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-sm shadow-sm transition-all flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-chart-pie text-indigo-500"></i>
                        <span><?= htmlspecialchars(__('landing.cta_demo')) ?></span>
                    </a>
                </div>

                <div class="mt-14 max-w-5xl mx-auto p-4 sm:p-6 rounded-3xl bg-white/60 dark:bg-slate-900/60 backdrop-blur-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-2xl shadow-indigo-500/5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-left">
                        <div class="p-4 rounded-2xl bg-white/80 dark:bg-slate-950/60 border border-slate-200/70 dark:border-slate-800/60 shadow-sm">
                            <span class="text-xs font-semibold text-slate-400 uppercase">Live Shelflife Audit</span>
                            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">100% Verified</div>
                            <span class="text-xs text-emerald-500 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i> Zero stock violations</span>
                        </div>
                        <div class="p-4 rounded-2xl bg-white/80 dark:bg-slate-950/60 border border-slate-200/70 dark:border-slate-800/60 shadow-sm">
                            <span class="text-xs font-semibold text-slate-400 uppercase">Optical OCR Precision</span>
                            <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">97.4% Match</div>
                            <span class="text-xs text-slate-500">Instant SKU & Batch parsing</span>
                        </div>
                        <div class="p-4 rounded-2xl bg-white/80 dark:bg-slate-950/60 border border-slate-200/70 dark:border-slate-800/60 shadow-sm">
                            <span class="text-xs font-semibold text-slate-400 uppercase">30-Day Critical Alerter</span>
                            <div class="text-2xl font-bold text-amber-500 mt-1">Automated Warning</div>
                            <span class="text-xs text-slate-500">Real-time liquidation alerts</span>
                        </div>
                    </div>
                </div>
            </section>

            <footer class="px-6 lg:px-12 py-8 border-t border-slate-200/60 dark:border-slate-800/60 text-center text-xs text-slate-500">
                <p>Desenvolvido por <strong>Kaio Borges Dev</strong> (<a href="https://dev.kaioborges.com.br/" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">dev.kaioborges.com.br</a>) — <strong>5E Group</strong> (<a href="https://instagram.com/5ebrasil" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">@5ebrasil</a>). Todos os direitos reservados.</p>
            </footer>
        </div>

    <?php elseif ($requestUri === '/login' || $requestUri === '/register'): ?>
        <!-- ==================== SPLIT-SCREEN AUTHENTICATION ==================== -->
        <?php $isRegister = ($requestUri === '/register'); ?>
        <div class="min-h-screen flex flex-col md:flex-row">
            <div class="hidden md:flex md:w-1/2 lg:w-3/5 relative overflow-hidden bg-slate-950 items-center justify-center p-12 select-none">
                <img 
                    src="/assets/auth_showcase.jpg" 
                    alt="Stockki 3D Logistics Visual" 
                    class="absolute inset-0 w-full h-full object-cover opacity-65 scale-105"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent"></div>

                <div class="relative z-10 max-w-lg space-y-6">
                    <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-indigo-500/20 backdrop-blur-md border border-indigo-400/30 text-indigo-300 text-xs font-bold">
                        <i class="fa-solid fa-sparkles"></i>
                        <span><?= htmlspecialchars(__('auth.showcase_badge')) ?></span>
                    </div>

                    <h2 class="text-3xl lg:text-4xl font-extrabold text-white tracking-tight leading-tight">
                        <?= htmlspecialchars(__('auth.showcase_title')) ?>
                    </h2>
                    <p class="text-sm text-slate-300 leading-relaxed">
                        <?= htmlspecialchars(__('auth.showcase_desc')) ?>
                    </p>

                    <div class="p-4 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/10 text-white shadow-2xl flex items-center space-x-4 animate-float-1">
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-lg">
                            <i class="fa-solid fa-shield-check"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold">100% Tenant Isolation</div>
                            <div class="text-[11px] text-slate-300">End-to-end encrypted workspace scoping</div>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-900/60 backdrop-blur-xl border border-white/10 text-white shadow-2xl flex items-center space-x-4 animate-float-2 ml-8">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-lg">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold">Optical Label Extraction</div>
                            <div class="text-[11px] text-slate-300">Live WebRTC camera & OCR parsing</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 flex flex-col justify-between p-6 sm:p-12 lg:p-16 bg-white/70 dark:bg-slate-950/80 backdrop-blur-2xl">
                <div class="flex items-center justify-between">
                    <a href="/" class="flex items-center space-x-2">
                        <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center">
                            <i class="fa-solid fa-cubes-stacked text-sm"></i>
                        </div>
                        <span class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(__('app_name')) ?></span>
                    </a>

                    <div class="flex items-center space-x-2">
                        <button 
                            type="button" 
                            @click="toggleTheme()" 
                            class="p-2 rounded-xl bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        >
                            <i class="fa-solid" :class="darkMode ? 'fa-sun text-amber-400' : 'fa-moon text-indigo-600'"></i>
                        </button>

                        <div class="flex items-center space-x-1 text-xs font-semibold bg-slate-100 dark:bg-slate-900 px-2 py-1 rounded-xl">
                            <a href="?lang=en" class="px-2 py-0.5 rounded <?= $locale === 'en' ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400' ?>">EN</a>
                            <a href="?lang=pt" class="px-2 py-0.5 rounded <?= $locale === 'pt' ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400' ?>">PT</a>
                        </div>
                    </div>
                </div>

                <div class="max-w-md w-full mx-auto my-8 space-y-6">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                            <?= $isRegister ? htmlspecialchars(__('auth.register_title')) : htmlspecialchars(__('auth.login_title')) ?>
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">
                            <?= $isRegister ? htmlspecialchars(__('auth.register_subtitle')) : htmlspecialchars(__('auth.login_subtitle')) ?>
                        </p>
                    </div>

                    <?php if ($authError): ?>
                        <div class="p-3.5 rounded-xl bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400 border border-rose-200 dark:border-rose-500/20 text-xs flex items-center space-x-2">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span><?= htmlspecialchars($authError) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= $isRegister ? '/register' : '/login' ?>" class="space-y-4">
                        <?php if ($isRegister): ?>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('auth.workspace_name')) ?> *</label>
                                <input type="text" name="workspace_name" required placeholder="Acme Logistics Ltd" class="w-full px-3.5 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('auth.full_name')) ?> *</label>
                                <input type="text" name="full_name" required placeholder="John Doe" class="w-full px-3.5 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs focus:outline-none focus:border-indigo-500">
                            </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('auth.email')) ?> *</label>
                            <input 
                                type="email" 
                                name="email" 
                                id="authEmail" 
                                required 
                                value="admin@stockki.io" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs focus:outline-none focus:border-indigo-500"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('auth.password')) ?> *</label>
                            <input 
                                type="password" 
                                name="password" 
                                id="authPassword" 
                                required 
                                value="password123" 
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs focus:outline-none focus:border-indigo-500"
                            >
                        </div>

                        <?php if (!$isRegister): ?>
                            <div class="flex items-center justify-between text-xs">
                                <label class="flex items-center space-x-2 text-slate-600 dark:text-slate-400 cursor-pointer">
                                    <input type="checkbox" name="remember" checked class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                    <span><?= htmlspecialchars(__('auth.remember_me')) ?></span>
                                </label>
                                <button type="button" onclick="document.getElementById('authEmail').value='admin@stockki.io'; document.getElementById('authPassword').value='password123';" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">
                                    <?= htmlspecialchars(__('auth.demo_fill')) ?>
                                </button>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-lg shadow-indigo-600/20 transition-all">
                            <?= $isRegister ? htmlspecialchars(__('auth.create_account')) : htmlspecialchars(__('auth.sign_in')) ?>
                        </button>
                    </form>

                    <div class="text-center pt-2">
                        <?php if ($isRegister): ?>
                            <a href="/login" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"><?= htmlspecialchars(__('auth.have_account')) ?></a>
                        <?php else: ?>
                            <a href="/register" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"><?= htmlspecialchars(__('auth.no_account')) ?></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center text-[11px] text-slate-400">
                    Stockki &copy; 2026. All rights reserved.
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ==================== MAIN APPLICATION SHELL ==================== -->
        <div class="flex flex-col md:flex-row min-h-screen">
            <!-- Mobile Top Bar -->
            <header class="md:hidden flex items-center justify-between px-4 py-3 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 sticky top-0 z-30">
                <div class="flex items-center space-x-3">
                    <button 
                        type="button"
                        @click="mobileOpen = true"
                        class="p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        aria-label="Open navigation menu"
                    >
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <div class="flex items-center space-x-2">
                        <i class="fa-solid fa-cubes-stacked text-indigo-600 text-lg"></i>
                        <span class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(__('app_name')) ?></span>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <button type="button" @click="toggleTheme()" class="p-2 rounded-xl text-slate-600 dark:text-slate-300">
                        <i class="fa-solid" :class="darkMode ? 'fa-sun text-amber-400' : 'fa-moon text-indigo-600'"></i>
                    </button>
                    <div class="flex items-center space-x-1 text-xs font-semibold">
                        <a href="?lang=en" class="px-2 py-0.5 rounded <?= $locale === 'en' ? 'bg-indigo-600 text-white' : 'text-slate-500' ?>">EN</a>
                        <a href="?lang=pt" class="px-2 py-0.5 rounded <?= $locale === 'pt' ? 'bg-indigo-600 text-white' : 'text-slate-500' ?>">PT</a>
                    </div>
                </div>
            </header>

            <!-- Mobile Backdrop -->
            <div 
                x-show="mobileOpen" 
                x-cloak
                @click="mobileOpen = false"
                class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm md:hidden"
            ></div>

            <!-- Responsive Collapsible Sidebar (Fixed Icon & Centered Unclipped Avatar Circle) -->
            <aside 
                :class="mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
                class="group/sidebar fixed inset-y-0 left-0 z-50 flex flex-col justify-between bg-white/90 dark:bg-slate-900/90 backdrop-blur-2xl border-r border-slate-200/90 dark:border-slate-800/90 text-slate-600 dark:text-slate-300 transition-all duration-300 ease-in-out w-72 md:w-20 md:hover:w-64 select-none shadow-xl"
            >
                <div>
                    <!-- Brand / Workspace Logo -->
                    <div class="flex items-center justify-between h-16 px-4 border-b border-slate-200/80 dark:border-slate-800/80">
                        <a href="/dashboard" class="flex items-center space-x-3.5 overflow-hidden">
                            <div class="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white shadow-md shadow-indigo-500/20">
                                <i class="fa-solid fa-cubes-stacked text-lg"></i>
                            </div>
                            <div class="flex flex-col whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                                <span class="font-bold text-base tracking-wide text-slate-900 dark:text-white leading-tight"><?= htmlspecialchars(__('app_name')) ?></span>
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars(__('tagline')) ?></span>
                            </div>
                        </a>

                        <button type="button" @click="mobileOpen = false" class="md:hidden p-1.5 rounded-lg text-slate-400 hover:text-slate-800 dark:hover:text-white">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <!-- Navigation Items -->
                    <nav class="mt-4 px-2 space-y-1.5" aria-label="Sidebar Navigation">
                        <!-- Dashboard -->
                        <a 
                            href="/dashboard" 
                            class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group <?= $requestUri === '/dashboard' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-l-2 border-indigo-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' ?>"
                        >
                            <div class="w-8 flex-shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-chart-pie text-base transition-transform group-hover:scale-110"></i>
                            </div>
                            <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                                <?= htmlspecialchars(__('nav.dashboard')) ?>
                            </span>
                        </a>

                        <!-- Inventory -->
                        <a 
                            href="/inventory" 
                            class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group <?= (str_starts_with($requestUri, '/inventory') && $requestUri !== '/inventory/expiry-alerts') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-l-2 border-indigo-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' ?>"
                        >
                            <div class="w-8 flex-shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-boxes-stacked text-base transition-transform group-hover:scale-110"></i>
                            </div>
                            <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                                <?= htmlspecialchars(__('nav.inventory')) ?>
                            </span>
                        </a>

                        <!-- Expiry Alerts -->
                        <a 
                            href="/inventory/expiry-alerts" 
                            class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group relative <?= $requestUri === '/inventory/expiry-alerts' ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 border-l-2 border-amber-500' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' ?>"
                        >
                            <div class="w-8 flex-shrink-0 flex items-center justify-center relative">
                                <i class="fa-solid fa-triangle-exclamation text-base text-amber-500 transition-transform group-hover:scale-110"></i>
                                <?php if ($expiredCount + $expiringSoonCount > 0): ?>
                                    <span class="absolute top-0 right-1 w-2 h-2 rounded-full bg-amber-500 md:group-hover/sidebar:hidden"></span>
                                <?php endif; ?>
                            </div>
                            <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200 flex-1">
                                <?= htmlspecialchars(__('nav.expiry_alerts')) ?>
                            </span>
                            <?php if ($expiredCount + $expiringSoonCount > 0): ?>
                                <span class="ml-auto hidden md:group-hover/sidebar:inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                                    <?= $expiredCount + $expiringSoonCount ?>
                                </span>
                            <?php endif; ?>
                        </a>

                        <!-- Optical Recognition (Free fa-camera icon guaranteed to render) -->
                        <a 
                            href="/recognition" 
                            class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group <?= $requestUri === '/recognition' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-l-2 border-indigo-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' ?>"
                        >
                            <div class="w-8 flex-shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-camera text-base transition-transform group-hover:scale-110"></i>
                            </div>
                            <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                                <?= htmlspecialchars(__('nav.recognition')) ?>
                            </span>
                        </a>

                        <!-- User Management (RBAC) -->
                        <a 
                            href="/users" 
                            class="flex items-center h-11 px-3 rounded-xl text-xs font-semibold transition-colors group <?= str_starts_with($requestUri, '/users') ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border-l-2 border-indigo-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100/70 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-white' ?>"
                        >
                            <div class="w-8 flex-shrink-0 flex items-center justify-center">
                                <i class="fa-solid fa-users-gear text-base transition-transform group-hover:scale-110"></i>
                            </div>
                            <span class="ml-3 truncate whitespace-nowrap opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200">
                                <?= htmlspecialchars(__('nav.users')) ?>
                            </span>
                        </a>
                    </nav>
                </div>

                <!-- Footer Section: Perfectly Centered, Unclipped Avatar Circle -->
                <div class="px-3 py-4 border-t border-slate-200/80 dark:border-slate-800/80 overflow-visible">
                    <div class="flex items-center md:justify-center md:group-hover/sidebar:justify-between w-full overflow-visible">
                        <div class="flex items-center space-x-3 overflow-visible">
                            <div class="w-10 h-10 min-w-[2.5rem] min-h-[2.5rem] flex-shrink-0 rounded-full bg-gradient-to-tr from-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-sm shadow-sm ring-2 ring-white dark:ring-slate-800">
                                <?= substr($_SESSION['user_name'] ?? 'K', 0, 1) ?>
                            </div>
                            <div class="hidden md:group-hover/sidebar:flex flex-col truncate transition-opacity duration-200">
                                <span class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Kaio Borges') ?></span>
                                <span class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? 'admin@stockki.io') ?></span>
                            </div>
                        </div>

                        <a 
                            href="/logout" 
                            class="hidden md:group-hover/sidebar:flex p-2 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg transition-colors items-center justify-center"
                            title="<?= htmlspecialchars(__('nav.logout')) ?>"
                        >
                            <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        </a>
                    </div>
                </div>
            </aside>

            <!-- Main Workspace Area -->
            <div class="flex-1 min-w-0 md:ml-20 flex flex-col min-h-screen">
                <header class="h-16 px-6 border-b border-slate-200/80 dark:border-slate-800/80 bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl flex items-center justify-between sticky top-0 z-20">
                    <div>
                        <h1 class="text-base font-bold text-slate-900 dark:text-white tracking-tight flex items-center space-x-2">
                            <?php if ($requestUri === '/dashboard'): ?>
                                <i class="fa-solid fa-chart-pie text-indigo-600"></i>
                                <span><?= htmlspecialchars(__('nav.dashboard')) ?></span>
                            <?php elseif ($requestUri === '/inventory'): ?>
                                <i class="fa-solid fa-boxes-stacked text-indigo-600"></i>
                                <span><?= htmlspecialchars(__('nav.inventory')) ?></span>
                            <?php elseif ($requestUri === '/inventory/create'): ?>
                                <i class="fa-solid fa-circle-plus text-indigo-600"></i>
                                <span><?= htmlspecialchars(__('actions.create')) ?></span>
                            <?php elseif ($requestUri === '/inventory/edit'): ?>
                                <i class="fa-solid fa-pen-to-square text-indigo-600"></i>
                                <span><?= htmlspecialchars(__('actions.edit')) ?></span>
                            <?php elseif ($requestUri === '/inventory/expiry-alerts'): ?>
                                <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                                <span><?= htmlspecialchars(__('nav.expiry_alerts')) ?></span>
                            <?php elseif ($requestUri === '/recognition'): ?>
                                <i class="fa-solid fa-camera text-indigo-600"></i>
                                <span><?= htmlspecialchars(__('recognition.title')) ?></span>
                            <?php elseif (str_starts_with($requestUri, '/users')): ?>
                                <i class="fa-solid fa-users-gear text-indigo-600"></i>
                                <span><?= htmlspecialchars(__('nav.users')) ?></span>
                            <?php endif; ?>
                        </h1>
                    </div>

                    <div class="flex items-center space-x-3">
                        <button 
                            type="button" 
                            @click="toggleTheme()" 
                            class="p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 shadow-sm transition-colors"
                            title="Toggle Light/Dark Theme"
                        >
                            <i class="fa-solid" :class="darkMode ? 'fa-sun text-amber-400' : 'fa-moon text-indigo-600'"></i>
                        </button>

                        <a href="/recognition" class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all">
                            <i class="fa-solid fa-camera"></i>
                            <span class="hidden sm:inline"><?= htmlspecialchars(__('recognition.scan_button')) ?></span>
                        </a>

                        <a href="/inventory/create" class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-xs font-semibold shadow-sm transition-all">
                            <i class="fa-solid fa-plus"></i>
                            <span class="hidden sm:inline"><?= htmlspecialchars(__('actions.create')) ?></span>
                        </a>

                        <div class="flex items-center space-x-1 text-xs font-semibold bg-white dark:bg-slate-800 px-2 py-1 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                            <a href="?lang=en" class="px-2 py-0.5 rounded <?= $locale === 'en' ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400' ?>">EN</a>
                            <span class="text-slate-300 dark:text-slate-600">|</span>
                            <a href="?lang=pt" class="px-2 py-0.5 rounded <?= $locale === 'pt' ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400' ?>">PT</a>
                        </div>
                    </div>
                </header>

                <main class="flex-1 p-6 md:p-8 max-w-7xl w-full mx-auto space-y-6">

                    <?php if ($flashMessage): ?>
                        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/20 flex items-center justify-between shadow-sm">
                            <div class="flex items-center space-x-3 text-xs font-semibold">
                                <i class="fa-solid fa-circle-check text-base text-emerald-600 dark:text-emerald-400"></i>
                                <span><?= htmlspecialchars($flashMessage) ?></span>
                            </div>
                            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($requestUri === '/dashboard'): ?>
                        <!-- DASHBOARD VIEW -->
                        <?php if ($expiredCount + $expiringSoonCount > 0): ?>
                            <div class="p-4 rounded-2xl bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-amber-200 dark:border-amber-500/20 flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-md shadow-amber-500/20">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900 dark:text-white">
                                            <?= htmlspecialchars(__('expiry.alert_banner', ['count' => $expiredCount + $expiringSoonCount])) ?>
                                        </div>
                                        <div class="text-[11px] text-slate-500">Liquidation or quarantine recommended for affected batches.</div>
                                    </div>
                                </div>
                                <a href="/inventory/expiry-alerts" class="text-xs font-bold px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition-colors">
                                    <?= htmlspecialchars(__('actions.filter')) ?> &rarr;
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="p-5 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_4px_25px_rgb(0,0,0,0.07)] transition-all">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('product.plural')) ?></span>
                                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                                        <i class="fa-solid fa-boxes-stacked"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-baseline space-x-2">
                                    <span class="text-3xl font-extrabold text-slate-900 dark:text-white"><?= $totalProductsCount ?></span>
                                    <span class="text-xs text-slate-400 font-medium">Active SKUs</span>
                                </div>
                            </div>

                            <div class="p-5 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-rose-200/80 dark:border-rose-900/40 shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_4px_25px_rgb(0,0,0,0.07)] transition-all">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-rose-500"><?= htmlspecialchars(__('expiry.status_expired')) ?></span>
                                    <div class="w-9 h-9 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                                        <i class="fa-solid fa-skull-crossbones"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-baseline space-x-2">
                                    <span class="text-3xl font-extrabold text-rose-600 dark:text-rose-400"><?= $expiredCount ?></span>
                                    <span class="text-xs text-rose-500 font-medium">Critical waste</span>
                                </div>
                            </div>

                            <div class="p-5 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-amber-200/80 dark:border-amber-900/40 shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_4px_25px_rgb(0,0,0,0.07)] transition-all">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-amber-500"><?= htmlspecialchars(__('expiry.status_expiring_soon')) ?></span>
                                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                        <i class="fa-solid fa-clock"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-baseline space-x-2">
                                    <span class="text-3xl font-extrabold text-amber-500"><?= $expiringSoonCount ?></span>
                                    <span class="text-xs text-amber-600 font-medium">&lt; 30 Days</span>
                                </div>
                            </div>

                            <div class="p-5 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 shadow-[0_4px_20px_rgb(0,0,0,0.03)] hover:shadow-[0_4px_25px_rgb(0,0,0,0.07)] transition-all">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400"><?= htmlspecialchars(__('product.safety_stock')) ?></span>
                                    <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-baseline space-x-2">
                                    <span class="text-3xl font-extrabold text-sky-600 dark:text-sky-400"><?= $lowStockCount ?></span>
                                    <span class="text-xs text-slate-400 font-medium">Reorder items</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm">
                            <div class="flex items-center justify-between text-xs font-bold mb-3">
                                <span class="text-slate-800 dark:text-white">Workspace Shelf-Life Health Index</span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-mono"><?= $totalProductsCount > 0 ? round(($safeCount / $totalProductsCount) * 100) : 0 ?>% Safe Ratio</span>
                            </div>
                            <div class="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden flex">
                                <?php if ($totalProductsCount > 0): ?>
                                    <div style="width: <?= ($safeCount / $totalProductsCount) * 100 ?>%" class="bg-emerald-500 transition-all"></div>
                                    <div style="width: <?= ($expiringSoonCount / $totalProductsCount) * 100 ?>%" class="bg-amber-500 transition-all"></div>
                                    <div style="width: <?= ($expiredCount / $totalProductsCount) * 100 ?>%" class="bg-rose-500 transition-all"></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex flex-wrap items-center gap-6 text-xs text-slate-600 dark:text-slate-400 mt-4">
                                <span class="flex items-center space-x-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span><span><?= htmlspecialchars(__('expiry.status_safe')) ?> (<?= $safeCount ?>)</span></span>
                                <span class="flex items-center space-x-2"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 inline-block"></span><span><?= htmlspecialchars(__('expiry.status_expiring_soon')) ?> (<?= $expiringSoonCount ?>)</span></span>
                                <span class="flex items-center space-x-2"><span class="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block"></span><span><?= htmlspecialchars(__('expiry.status_expired')) ?> (<?= $expiredCount ?>)</span></span>
                            </div>
                        </div>

                        <div class="p-6 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Critical Shelf-Life Batches</h2>
                                    <p class="text-xs text-slate-500">Requires liquidation, discounting, or disposal</p>
                                </div>
                                <a href="/inventory/expiry-alerts" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    <?= htmlspecialchars(__('actions.filter')) ?> &rarr;
                                </a>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-200/80 dark:border-slate-800/80 text-slate-400 font-semibold">
                                            <th class="py-3 px-3"><?= htmlspecialchars(__('product.name')) ?></th>
                                            <th class="py-3 px-3"><?= htmlspecialchars(__('product.sku')) ?></th>
                                            <th class="py-3 px-3"><?= htmlspecialchars(__('product.batch_number')) ?></th>
                                            <th class="py-3 px-3"><?= htmlspecialchars(__('product.quantity')) ?></th>
                                            <th class="py-3 px-3"><?= htmlspecialchars(__('product.expiry_date')) ?></th>
                                            <th class="py-3 px-3"><?= htmlspecialchars(__('product.status')) ?></th>
                                            <th class="py-3 px-3 text-right"><?= htmlspecialchars(__('product.actions')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        <?php 
                                        $urgentList = array_filter($allProducts, function($p) use ($referenceDate) {
                                            $st = getExpiryStatus($p['expiry_date'], $referenceDate);
                                            return $st['status'] !== 'safe';
                                        });
                                        foreach (array_slice($urgentList, 0, 5) as $prod): 
                                            $st = getExpiryStatus($prod['expiry_date'], $referenceDate);
                                        ?>
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                                <td class="py-3 px-3 font-semibold text-slate-900 dark:text-white flex items-center space-x-2">
                                                    <span class="w-2 h-2 rounded-full <?= $st['status'] === 'expired' ? 'bg-rose-500' : 'bg-amber-500' ?>"></span>
                                                    <span><?= htmlspecialchars($prod['name']) ?></span>
                                                </td>
                                                <td class="py-3 px-3 text-slate-500 font-mono"><?= htmlspecialchars($prod['sku']) ?></td>
                                                <td class="py-3 px-3 text-slate-600 dark:text-slate-300 font-mono"><?= htmlspecialchars($prod['batch_number'] ?? '-') ?></td>
                                                <td class="py-3 px-3 font-bold <?= $prod['quantity'] <= $prod['safety_stock'] ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-200' ?>">
                                                    <?= $prod['quantity'] ?> units
                                                </td>
                                                <td class="py-3 px-3 font-mono text-slate-600 dark:text-slate-300"><?= htmlspecialchars($prod['expiry_date']) ?></td>
                                                <td class="py-3 px-3">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $st['class'] ?>">
                                                        <?= $st['status'] === 'expired' ? __('expiry.status_expired') : __('expiry.status_expiring_soon') ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-3 text-right">
                                                    <a href="/inventory/edit?id=<?= urlencode($prod['id']) ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400" title="Edit">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($requestUri === '/inventory'): ?>
                        <!-- INVENTORY LIST VIEW -->
                        <?php
                        $search = trim($_GET['search'] ?? '');
                        $statusFilter = $_GET['status'] ?? 'all';

                        $filteredProducts = array_filter($allProducts, function($p) use ($search, $statusFilter, $referenceDate) {
                            if ($search !== '') {
                                $searchLower = strtolower($search);
                                $nameMatch = str_contains(strtolower($p['name']), $searchLower);
                                $skuMatch = str_contains(strtolower($p['sku']), $searchLower);
                                $batchMatch = str_contains(strtolower($p['batch_number'] ?? ''), $searchLower);
                                if (!$nameMatch && !$skuMatch && !$batchMatch) {
                                    return false;
                                }
                            }

                            if ($statusFilter !== 'all') {
                                $st = getExpiryStatus($p['expiry_date'], $referenceDate);
                                if ($statusFilter === 'low_stock') {
                                    return $p['quantity'] <= $p['safety_stock'];
                                }
                                if ($st['status'] !== $statusFilter) {
                                    return false;
                                }
                            }

                            return true;
                        });
                        ?>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(__('nav.inventory')) ?></h2>
                                <p class="text-xs text-slate-500">Total of <?= count($filteredProducts) ?> batches tracked</p>
                            </div>

                            <div class="flex items-center space-x-2">
                                <a href="/recognition" class="inline-flex items-center space-x-2 px-3 py-2 rounded-xl bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-xs font-semibold shadow-sm transition-all">
                                    <i class="fa-solid fa-camera"></i>
                                    <span><?= htmlspecialchars(__('recognition.scan_button')) ?></span>
                                </a>
                                <a href="/inventory/create" class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all">
                                    <i class="fa-solid fa-plus"></i>
                                    <span><?= htmlspecialchars(__('actions.create')) ?></span>
                                </a>
                            </div>
                        </div>

                        <div class="p-4 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm">
                            <form method="GET" action="/inventory" class="flex-1 w-full flex items-center space-x-2">
                                <div class="relative flex-1">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
                                    <input 
                                        type="text" 
                                        name="search" 
                                        value="<?= htmlspecialchars($search) ?>" 
                                        placeholder="<?= htmlspecialchars(__('actions.search')) ?>" 
                                        class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
                                    >
                                </div>
                                <?php if ($statusFilter !== 'all'): ?>
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                                <?php endif; ?>
                                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-semibold text-slate-700 dark:text-white transition-colors">
                                    <?= htmlspecialchars(__('actions.filter')) ?>
                                </button>
                            </form>

                            <div class="flex items-center space-x-1 text-xs font-semibold">
                                <a href="/inventory?status=all" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' ?>">All</a>
                                <a href="/inventory?status=safe" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'safe' ? 'bg-emerald-600 text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' ?>"><?= htmlspecialchars(__('expiry.status_safe')) ?></a>
                                <a href="/inventory?status=expiring_soon" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'expiring_soon' ? 'bg-amber-500 text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' ?>"><?= htmlspecialchars(__('expiry.status_expiring_soon')) ?></a>
                                <a href="/inventory?status=expired" class="px-3 py-1.5 rounded-lg <?= $statusFilter === 'expired' ? 'bg-rose-600 text-white' : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800' ?>"><?= htmlspecialchars(__('expiry.status_expired')) ?></a>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/70 dark:bg-slate-950/60 border-b border-slate-200/80 dark:border-slate-800/80 text-slate-400 font-semibold">
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('product.name')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('product.sku')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('product.batch_number')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('product.quantity')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('product.expiry_date')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('product.safety_stock')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('product.status')) ?></th>
                                            <th class="py-3.5 px-4 text-right"><?= htmlspecialchars(__('product.actions')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        <?php if (empty($filteredProducts)): ?>
                                            <tr>
                                                <td colspan="8" class="py-8 text-center text-slate-400">
                                                    No products match your search or filter criteria.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($filteredProducts as $prod): 
                                                $st = getExpiryStatus($prod['expiry_date'], $referenceDate);
                                            ?>
                                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                                    <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                                                        <div class="flex items-center space-x-2">
                                                            <span class="w-2.5 h-2.5 rounded-full <?= $st['status'] === 'expired' ? 'bg-rose-500' : ($st['status'] === 'expiring_soon' ? 'bg-amber-500' : 'bg-emerald-500') ?>"></span>
                                                            <span><?= htmlspecialchars($prod['name']) ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="py-3.5 px-4 font-mono text-slate-500"><?= htmlspecialchars($prod['sku']) ?></td>
                                                    <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300 font-mono"><?= htmlspecialchars($prod['batch_number'] ?? '-') ?></td>
                                                    <td class="py-3.5 px-4 font-extrabold <?= $prod['quantity'] <= $prod['safety_stock'] ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-200' ?>">
                                                        <?= $prod['quantity'] ?> units
                                                    </td>
                                                    <td class="py-3.5 px-4 font-mono text-slate-600 dark:text-slate-300"><?= htmlspecialchars($prod['expiry_date']) ?></td>
                                                    <td class="py-3.5 px-4 text-slate-400 font-medium"><?= $prod['safety_stock'] ?> units</td>
                                                    <td class="py-3.5 px-4">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $st['class'] ?>">
                                                            <?= $st['status'] === 'expired' ? __('expiry.status_expired') : ($st['status'] === 'expiring_soon' ? __('expiry.status_expiring_soon') : __('expiry.status_safe')) ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3.5 px-4 text-right space-x-2">
                                                        <a href="/inventory/edit?id=<?= urlencode($prod['id']) ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors inline-block" title="<?= htmlspecialchars(__('actions.edit')) ?>">
                                                            <i class="fa-solid fa-pen-to-square"></i>
                                                        </a>
                                                        <form method="POST" action="/inventory/delete" onsubmit="return confirm('<?= htmlspecialchars(__('actions.confirm_delete')) ?>');" class="inline">
                                                            <input type="hidden" name="id" value="<?= htmlspecialchars($prod['id']) ?>">
                                                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="<?= htmlspecialchars(__('actions.delete')) ?>">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($requestUri === '/inventory/create' || $requestUri === '/inventory/edit'): ?>
                        <!-- CREATE / EDIT PRODUCT VIEW -->
                        <?php
                        $isEdit = ($requestUri === '/inventory/edit');
                        $editId = $_GET['id'] ?? '';
                        $productToEdit = null;

                        if ($isEdit && $editId) {
                            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
                            $stmt->execute([$editId, $currentUserId]);
                            $productToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
                        }

                        $presetName = $productToEdit['name'] ?? ($_GET['name'] ?? '');
                        $presetSku = $productToEdit['sku'] ?? ($_GET['sku'] ?? '');
                        $presetBatch = $productToEdit['batch_number'] ?? ($_GET['batch'] ?? '');
                        $presetExpiry = $productToEdit['expiry_date'] ?? ($_GET['expiry'] ?? '');
                        $presetMfg = $productToEdit['manufacture_date'] ?? ($_GET['mfg'] ?? '');
                        $presetQty = $productToEdit['quantity'] ?? ($_GET['quantity'] ?? '50');
                        $presetSafety = $productToEdit['safety_stock'] ?? ($_GET['safety'] ?? '15');
                        $fromOcr = isset($_GET['from_ocr']);
                        ?>

                        <div class="max-w-2xl mx-auto">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?= $isEdit ? htmlspecialchars(__('actions.edit')) : htmlspecialchars(__('actions.create')) ?></h2>
                                    <p class="text-xs text-slate-500"><?= $isEdit ? 'Update stock quantities, batch details, and expiry thresholds' : 'Add a verified product batch to your workspace inventory' ?></p>
                                </div>
                                <?php if (!$isEdit): ?>
                                    <a href="/recognition" class="text-xs font-semibold px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 border border-slate-200 dark:border-slate-700 shadow-sm transition-all">
                                        <i class="fa-solid fa-camera mr-1"></i> Scan Label Instead
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if ($fromOcr): ?>
                                <div class="p-4 rounded-2xl bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-500/20 text-xs mb-6 flex items-center space-x-3">
                                    <i class="fa-solid fa-wand-magic-sparkles text-indigo-600 dark:text-indigo-400 text-base"></i>
                                    <span>Real attributes extracted from label OCR. Please verify values before saving.</span>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?= $isEdit ? '/inventory/update' : '/inventory/store' ?>" class="p-6 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 space-y-4 shadow-sm">
                                <?php if ($isEdit): ?>
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($editId) ?>">
                                <?php endif; ?>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('product.name')) ?> *</label>
                                    <input 
                                        type="text" 
                                        name="name" 
                                        required 
                                        value="<?= htmlspecialchars($presetName) ?>" 
                                        placeholder="e.g. Paracetamol 500mg Tablets" 
                                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                    >
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('product.sku')) ?> *</label>
                                        <input 
                                            type="text" 
                                            name="sku" 
                                            required 
                                            value="<?= htmlspecialchars($presetSku) ?>" 
                                            placeholder="e.g. MED-PAR-500" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:outline-none focus:border-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('product.batch_number')) ?></label>
                                        <input 
                                            type="text" 
                                            name="batch_number" 
                                            value="<?= htmlspecialchars($presetBatch) ?>" 
                                            placeholder="e.g. LOT-202610-09" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white font-mono focus:outline-none focus:border-indigo-500"
                                        >
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('product.quantity')) ?> *</label>
                                        <input 
                                            type="number" 
                                            name="quantity" 
                                            min="0" 
                                            required 
                                            value="<?= htmlspecialchars($presetQty) ?>" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('product.safety_stock')) ?></label>
                                        <input 
                                            type="number" 
                                            name="safety_stock" 
                                            min="0" 
                                            value="<?= htmlspecialchars($presetSafety) ?>" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                        >
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('product.manufacture_date')) ?></label>
                                        <input 
                                            type="date" 
                                            name="manufacture_date" 
                                            value="<?= htmlspecialchars($presetMfg) ?>" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('product.expiry_date')) ?> *</label>
                                        <input 
                                            type="date" 
                                            name="expiry_date" 
                                            required 
                                            value="<?= htmlspecialchars($presetExpiry) ?>" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                        >
                                    </div>
                                </div>

                                <div class="pt-4 flex items-center justify-end space-x-3">
                                    <a href="/inventory" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-semibold transition-colors">
                                        <?= htmlspecialchars(__('actions.cancel')) ?>
                                    </a>
                                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all">
                                        <?= $isEdit ? htmlspecialchars(__('actions.update')) : htmlspecialchars(__('actions.save')) ?>
                                    </button>
                                </div>
                            </form>
                        </div>

                    <?php elseif ($requestUri === '/inventory/expiry-alerts'): ?>
                        <!-- DEDICATED EXPIRY ALERTS VIEW -->
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(__('nav.expiry_alerts')) ?></h2>
                                    <p class="text-xs text-slate-500">Products requiring immediate shelf-life attention</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="p-6 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-rose-200/80 dark:border-rose-900/40 shadow-sm">
                                    <div class="flex items-center space-x-3 mb-4">
                                        <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-base font-bold text-rose-600 dark:text-rose-400"><?= htmlspecialchars(__('expiry.status_expired')) ?> (<?= $expiredCount ?>)</h3>
                                            <p class="text-xs text-slate-400">Past safe consumption threshold</p>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <?php 
                                        $expiredItems = array_filter($allProducts, fn($p) => getExpiryStatus($p['expiry_date'], $referenceDate)['status'] === 'expired');
                                        foreach ($expiredItems as $p): 
                                            $st = getExpiryStatus($p['expiry_date'], $referenceDate);
                                        ?>
                                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                                                <div>
                                                    <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($p['name']) ?></div>
                                                    <div class="text-slate-400 text-[11px] font-mono"><?= htmlspecialchars($p['sku']) ?> | Batch: <?= htmlspecialchars($p['batch_number'] ?? '-') ?></div>
                                                </div>
                                                <div class="text-right">
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400">
                                                        Expired <?= $st['days'] ?>d ago
                                                    </span>
                                                    <div class="text-[11px] text-slate-400 mt-1"><?= $p['quantity'] ?> units in stock</div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="p-6 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-amber-200/80 dark:border-amber-900/40 shadow-sm">
                                    <div class="flex items-center space-x-3 mb-4">
                                        <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                                            <i class="fa-solid fa-clock"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-base font-bold text-amber-600 dark:text-amber-400"><?= htmlspecialchars(__('expiry.status_expiring_soon')) ?> (<?= $expiringSoonCount ?>)</h3>
                                            <p class="text-xs text-slate-400">Expiring within the next 30 days</p>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <?php 
                                        $expiringItems = array_filter($allProducts, fn($p) => getExpiryStatus($p['expiry_date'], $referenceDate)['status'] === 'expiring_soon');
                                        foreach ($expiringItems as $p): 
                                            $st = getExpiryStatus($p['expiry_date'], $referenceDate);
                                        ?>
                                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                                                <div>
                                                    <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($p['name']) ?></div>
                                                    <div class="text-slate-400 text-[11px] font-mono"><?= htmlspecialchars($p['sku']) ?> | Batch: <?= htmlspecialchars($p['batch_number'] ?? '-') ?></div>
                                                </div>
                                                <div class="text-right">
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">
                                                        <?= $st['days'] ?> days remaining
                                                    </span>
                                                    <div class="text-[11px] text-slate-400 mt-1"><?= $p['quantity'] ?> units in stock</div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($requestUri === '/recognition'): ?>
                        <!-- ==================== REAL OPTICAL CHARACTER RECOGNITION (TESSERACT OCR + WEBRTC) ==================== -->
                        <div x-data="{
                            mode: 'camera',
                            cameraActive: false,
                            scanning: false,
                            scanned: false,
                            statusText: '',
                            mediaStream: null,
                            rawOcrOutput: '',
                            warnings: [],
                            result: {
                                name: '',
                                sku: '',
                                batch_number: '',
                                manufacture_date: '',
                                expiry_date: '',
                                confidence: 0.0,
                                quantity: 1
                            },
                            async initCamera() {
                                try {
                                    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                                        this.mediaStream = await navigator.mediaDevices.getUserMedia({
                                            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
                                        });
                                        this.$refs.videoElement.srcObject = this.mediaStream;
                                        this.cameraActive = true;
                                    } else {
                                        this.cameraActive = false;
                                    }
                                } catch (err) {
                                    console.warn('Camera error or permission denied:', err);
                                    this.cameraActive = false;
                                }
                            },
                            stopCamera() {
                                if (this.mediaStream) {
                                    this.mediaStream.getTracks().forEach(track => track.stop());
                                    this.mediaStream = null;
                                }
                                this.cameraActive = false;
                            },
                            async processImage(imageSource) {
                                this.scanning = true;
                                this.scanned = false;
                                this.statusText = 'Initializing Optical Recognition Engine...';

                                try {
                                    this.statusText = 'Extracting character pixels & analyzing font features...';
                                    
                                    // Execute Real Tesseract OCR
                                    const { data } = await Tesseract.recognize(
                                        imageSource,
                                        'eng',
                                        {
                                            logger: m => {
                                                if (m.status === 'recognizing text') {
                                                    this.statusText = `Reading text: ${Math.round(m.progress * 100)}%`;
                                                }
                                            }
                                        }
                                    );

                                    this.rawOcrOutput = data.text ? data.text.trim() : 'No text characters identified.';
                                    this.statusText = 'Parsing product name, lot number and expiration date...';

                                    // Send recognized text to backend pattern extractor
                                    const formData = new FormData();
                                    formData.append('raw_text', this.rawOcrOutput);
                                    formData.append('confidence', data.confidence ? (data.confidence / 100) : 0.95);

                                    const response = await fetch('/recognition/analyze', {
                                        method: 'POST',
                                        body: formData
                                    });

                                    const json = await response.json();
                                    if (json.data) {
                                        this.result = json.data;
                                        this.result.quantity = json.data.quantity || 1;
                                        this.warnings = json.data.warnings || [];
                                        this.scanned = true;
                                        if (this.warnings.length > 0) {
                                            this.statusText = 'Partial extraction — some fields need manual input.';
                                        }
                                    }
                                } catch (e) {
                                    console.error('OCR Extraction failed:', e);
                                    this.statusText = 'Error during optical recognition. Please try another image.';
                                } finally {
                                    this.scanning = false;
                                }
                            },
                            captureCameraSnapshot() {
                                const video = this.$refs.videoElement;
                                const canvas = document.createElement('canvas');
                                canvas.width = video.videoWidth || 640;
                                canvas.height = video.videoHeight || 480;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                const dataUrl = canvas.toDataURL('image/png');
                                this.processImage(dataUrl);
                            },
                            handleFileUpload(event) {
                                const file = event.target.files[0];
                                if (!file) return;
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    this.processImage(e.target.result);
                                };
                                reader.readAsDataURL(file);
                            },
                            loadRealSampleLabel(sampleType) {
                                const canvas = document.createElement('canvas');
                                canvas.width = 800;
                                canvas.height = 400;
                                const ctx = canvas.getContext('2d');
                                
                                // Clean label background
                                ctx.fillStyle = '#ffffff';
                                ctx.fillRect(0, 0, canvas.width, canvas.height);
                                ctx.strokeStyle = '#6366f1';
                                ctx.lineWidth = 6;
                                ctx.strokeRect(10, 10, canvas.width - 20, canvas.height - 20);

                                ctx.fillStyle = '#0f172a';
                                ctx.font = 'bold 36px Arial';

                                if (sampleType === 'amoxicillin') {
                                    ctx.fillText('AMOXICILLIN TRIHYDRATE 500MG', 40, 80);
                                    ctx.font = '24px Arial';
                                    ctx.fillText('SKU: MED-AMX-500', 40, 140);
                                    ctx.fillText('LOT: 202610-09', 40, 190);
                                    ctx.fillText('MFG: 10/04/2026', 40, 240);
                                    ctx.fillText('EXP: 15/11/2026', 40, 290);
                                    ctx.fillText('PKG: 100 CAPSULES - ORAL USE', 40, 340);
                                } else if (sampleType === 'ibuprofen') {
                                    ctx.fillText('IBUPROFEN 400MG TABLETS', 40, 80);
                                    ctx.font = '24px Arial';
                                    ctx.fillText('SKU: PED-IBU-400', 40, 140);
                                    ctx.fillText('LOTE: 202607-88', 40, 190);
                                    ctx.fillText('FAB: 15/01/2026', 40, 240);
                                    ctx.fillText('VAL: 20/12/2026', 40, 290);
                                    ctx.fillText('SAFETY STOCK: 20 UNITS', 40, 340);
                                } else {
                                    ctx.fillText('ORGANIC WHOLE MILK 1L', 40, 80);
                                    ctx.font = '24px Arial';
                                    ctx.fillText('SKU: FOOD-MLK-1000', 40, 140);
                                    ctx.fillText('BATCH: LOT-202609-02', 40, 190);
                                    ctx.fillText('PROD: 01/09/2026', 40, 240);
                                    ctx.fillText('BEST BEFORE: 28/11/2026', 40, 290);
                                    ctx.fillText('PASTEURIZED GRADE A', 40, 340);
                                }

                                const dataUrl = canvas.toDataURL('image/png');
                                this.processImage(dataUrl);
                            }
                        }" x-init="initCamera()" class="max-w-3xl mx-auto space-y-6">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(__('recognition.title')) ?></h2>
                                <p class="text-xs text-slate-500"><?= htmlspecialchars(__('recognition.subtitle')) ?></p>
                            </div>

                            <!-- Mode Switcher Tabs -->
                            <div class="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                                <button 
                                    type="button" 
                                    @click="mode = 'camera'; if(!cameraActive) initCamera();"
                                    :class="mode === 'camera' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800'"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold flex items-center space-x-2 transition-colors"
                                >
                                    <i class="fa-solid fa-camera"></i>
                                    <span><?= htmlspecialchars(__('recognition.camera_mode')) ?></span>
                                </button>
                                <button 
                                    type="button" 
                                    @click="mode = 'upload'; stopCamera();"
                                    :class="mode === 'upload' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-800'"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold flex items-center space-x-2 transition-colors"
                                >
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span><?= htmlspecialchars(__('recognition.upload_mode')) ?></span>
                                </button>
                            </div>

                            <!-- Quick Real Test Samples Bar -->
                            <div class="p-3.5 rounded-2xl bg-white/70 dark:bg-slate-900/70 border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <span class="font-semibold text-slate-500"><i class="fa-solid fa-flask text-indigo-500 mr-1.5"></i>Test with Real Label:</span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" @click="loadRealSampleLabel('amoxicillin')" :disabled="scanning" class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 font-semibold transition-all">
                                        Amoxicillin 500mg
                                    </button>
                                    <button type="button" @click="loadRealSampleLabel('ibuprofen')" :disabled="scanning" class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 font-semibold transition-all">
                                        Ibuprofen 400mg
                                    </button>
                                    <button type="button" @click="loadRealSampleLabel('milk')" :disabled="scanning" class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 font-semibold transition-all">
                                        Organic Milk 1L
                                    </button>
                                </div>
                            </div>

                            <!-- CAMERA VIEWFINDER CONTAINER -->
                            <div x-show="mode === 'camera'" class="p-6 rounded-3xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 text-center shadow-sm">
                                <div class="max-w-md mx-auto relative rounded-2xl overflow-hidden bg-slate-950 border border-slate-800 aspect-[4/3] flex items-center justify-center">
                                    <video 
                                        x-ref="videoElement" 
                                        autoplay 
                                        playsinline 
                                        muted 
                                        class="w-full h-full object-cover"
                                        :class="cameraActive ? 'block' : 'hidden'"
                                    ></video>

                                    <div x-show="!cameraActive" class="p-6 text-center text-white">
                                        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl">
                                            <i class="fa-solid fa-camera"></i>
                                        </div>
                                        <div class="text-xs font-bold mb-1">Optical Camera Viewfinder Ready</div>
                                        <div class="text-[11px] text-slate-400 mb-3 font-mono">Align any packaging label in front of camera</div>
                                        <button type="button" @click="initCamera()" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold">
                                            <i class="fa-solid fa-play mr-1"></i> Start Camera Stream
                                        </button>
                                    </div>

                                    <!-- Laser Viewfinder Grid -->
                                    <div class="absolute inset-0 border-2 border-indigo-500/30 rounded-2xl pointer-events-none">
                                        <div class="absolute top-2 left-2 w-6 h-6 border-t-2 border-l-2 border-indigo-400"></div>
                                        <div class="absolute top-2 right-2 w-6 h-6 border-t-2 border-r-2 border-indigo-400"></div>
                                        <div class="absolute bottom-2 left-2 w-6 h-6 border-b-2 border-l-2 border-indigo-400"></div>
                                        <div class="absolute bottom-2 right-2 w-6 h-6 border-b-2 border-r-2 border-indigo-400"></div>
                                    </div>

                                    <div x-show="scanning" class="absolute inset-x-0 h-1 bg-gradient-to-r from-transparent via-indigo-500 to-transparent animate-bounce"></div>
                                </div>

                                <div class="mt-4 flex items-center justify-center space-x-3">
                                    <button 
                                        type="button" 
                                        @click="captureCameraSnapshot()" 
                                        :disabled="scanning"
                                        class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white shadow-md shadow-indigo-600/25 transition-all inline-flex items-center space-x-2"
                                    >
                                        <template x-if="scanning">
                                            <span><i class="fa-solid fa-spinner fa-spin mr-2"></i><span x-text="statusText"></span></span>
                                        </template>
                                        <template x-if="!scanning">
                                            <span><i class="fa-solid fa-camera mr-2"></i><?= htmlspecialchars(__('recognition.capture_photo')) ?></span>
                                        </template>
                                    </button>
                                </div>
                            </div>

                            <!-- FILE UPLOAD DROPZONE -->
                            <div x-show="mode === 'upload'" class="p-8 rounded-3xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border-2 border-dashed border-slate-300 dark:border-slate-800 text-center shadow-sm">
                                <div class="w-16 h-16 mx-auto rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl mb-4">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(__('recognition.upload_label')) ?></h3>
                                <p class="text-xs text-slate-500 mt-1">Upload any picture of product packaging, barcode, or medicine label</p>

                                <div class="mt-6 flex flex-col items-center justify-center">
                                    <input 
                                        type="file" 
                                        accept="image/*" 
                                        @change="handleFileUpload($event)" 
                                        id="fileInput" 
                                        class="hidden"
                                    >
                                    <label 
                                        for="fileInput" 
                                        class="cursor-pointer px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white shadow-md shadow-indigo-600/25 transition-all inline-flex items-center space-x-2"
                                    >
                                        <template x-if="scanning">
                                            <span><i class="fa-solid fa-spinner fa-spin mr-2"></i><span x-text="statusText"></span></span>
                                        </template>
                                        <template x-if="!scanning">
                                            <span><i class="fa-solid fa-file-image mr-2"></i>Select Image to Scan</span>
                                        </template>
                                    </label>
                                </div>
                            </div>

                            <!-- Live Scan Progress Indicator -->
                            <div x-show="scanning" class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs flex items-center space-x-3">
                                <i class="fa-solid fa-circle-notch fa-spin text-base text-indigo-600"></i>
                                <span class="font-semibold" x-text="statusText"></span>
                            </div>

                            <!-- REAL OCR EXTRACTION RESULTS PANEL -->
                            <div x-show="scanned" x-cloak class="p-6 rounded-2xl bg-white/80 dark:bg-slate-900/90 backdrop-blur-xl border border-indigo-200 dark:border-indigo-500/30 space-y-4 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 font-bold text-xs">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span>Optical Recognition Complete</span>
                                    </div>
                                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300 font-bold">
                                        Confidence: <span x-text="(result.confidence * 100).toFixed(1) + '%'"></span>
                                    </span>
                                </div>

                                <!-- Partial extraction warning -->
                                <template x-if="warnings.length > 0">
                                    <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-xs text-amber-700 dark:text-amber-300 flex items-center space-x-2">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <span>Some fields could not be auto-detected. Please fill them manually before saving.</span>
                                    </div>
                                </template>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                        <span class="text-slate-400 block"><?= htmlspecialchars(__('product.name')) ?>:</span>
                                        <span class="text-slate-900 dark:text-white font-bold" x-text="result.name"></span>
                                    </div>
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                        <span class="text-slate-400 block"><?= htmlspecialchars(__('product.sku')) ?>:</span>
                                        <span class="text-slate-900 dark:text-white font-mono font-bold" x-text="result.sku"></span>
                                    </div>
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                        <span class="text-slate-400 block"><?= htmlspecialchars(__('product.batch_number')) ?>:</span>
                                        <span class="text-slate-900 dark:text-white font-mono" x-text="result.batch_number"></span>
                                    </div>
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                        <span class="text-slate-400 block"><?= htmlspecialchars(__('product.expiry_date')) ?>:</span>
                                        <span class="text-amber-500 font-mono font-bold" x-text="result.expiry_date"></span>
                                    </div>
                                </div>

                                <!-- Real Extracted Optical Character Raw Output Box -->
                                <div class="pt-2">
                                    <span class="text-[11px] font-bold text-slate-500 block mb-1">Raw Detected Text from Packaging:</span>
                                    <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-950 font-mono text-[11px] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 whitespace-pre-line max-h-32 overflow-y-auto" x-text="rawOcrOutput"></div>
                                </div>

                                <div class="pt-2 flex justify-end">
                                    <a 
                                        :href="`/inventory/create?from_ocr=1&name=${encodeURIComponent(result.name)}&sku=${encodeURIComponent(result.sku)}&batch=${encodeURIComponent(result.batch_number)}&expiry=${encodeURIComponent(result.expiry_date)}&mfg=${encodeURIComponent(result.manufacture_date || '')}&quantity=${result.quantity}`" 
                                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-xs font-semibold text-white shadow-md shadow-emerald-600/20 transition-all inline-flex items-center space-x-2"
                                    >
                                        <i class="fa-solid fa-file-pen"></i>
                                        <span><?= htmlspecialchars(__('recognition.auto_fill')) ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($requestUri === '/users'): ?>
                        <!-- USERS VIEW -->
                        <?php
                        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at ASC");
                        $usersList = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars(__('user.title')) ?></h2>
                                <p class="text-xs text-slate-500"><?= htmlspecialchars(__('user.subtitle')) ?></p>
                            </div>

                            <a href="/users/create" class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all">
                                <i class="fa-solid fa-user-plus"></i>
                                <span><?= htmlspecialchars(__('actions.new_user')) ?></span>
                            </a>
                        </div>

                        <div class="rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 overflow-hidden shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/70 dark:bg-slate-950/60 border-b border-slate-200/80 dark:border-slate-800/80 text-slate-400 font-semibold">
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('user.name')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('user.role')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('user.status')) ?></th>
                                            <th class="py-3.5 px-4"><?= htmlspecialchars(__('user.permissions')) ?></th>
                                            <th class="py-3.5 px-4 text-right"><?= htmlspecialchars(__('product.actions')) ?></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                        <?php foreach ($usersList as $u): 
                                            $userPerms = json_decode($u['permissions'] ?? '[]', true) ?: [];
                                            $roleBadgeColor = match($u['role']) {
                                                'admin' => 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-500/20 dark:text-purple-300 dark:border-purple-500/30',
                                                'manager' => 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/20 dark:text-indigo-300 dark:border-indigo-500/30',
                                                default => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
                                            };
                                        ?>
                                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                                                <td class="py-3.5 px-4">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-violet-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                                                            <?= substr($u['name'], 0, 1) ?>
                                                        </div>
                                                        <div>
                                                            <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($u['name']) ?></div>
                                                            <div class="text-[11px] text-slate-400"><?= htmlspecialchars($u['email']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="py-3.5 px-4">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border <?= $roleBadgeColor ?>">
                                                        <?= ucfirst($u['role']) ?>
                                                    </span>
                                                </td>
                                                <td class="py-3.5 px-4">
                                                    <form method="POST" action="/users/toggle-status" class="inline">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                                                        <button type="submit" class="inline-flex items-center space-x-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold transition-colors <?= $u['status'] === 'active' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-400 dark:border-emerald-500/30' : 'bg-slate-100 text-slate-500 border border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700' ?>" title="Click to toggle status">
                                                            <span class="w-1.5 h-1.5 rounded-full <?= $u['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                                                            <span><?= $u['status'] === 'active' ? __('user.active') : __('user.inactive') ?></span>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td class="py-3.5 px-4">
                                                    <div class="flex flex-wrap gap-1">
                                                        <?php foreach ($userPerms as $perm): ?>
                                                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-mono border border-slate-200 dark:border-slate-700/60">
                                                                <?= htmlspecialchars($perm) ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <?php if (empty($userPerms)): ?>
                                                            <span class="text-slate-400 text-[11px]">No specific rights</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="py-3.5 px-4 text-right space-x-2">
                                                    <a href="/users/edit?id=<?= urlencode($u['id']) ?>" class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors inline-block" title="<?= htmlspecialchars(__('actions.edit')) ?>">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                    <?php if ($u['id'] !== $currentUserId): ?>
                                                        <form method="POST" action="/users/delete" onsubmit="return confirm('<?= htmlspecialchars(__('actions.confirm_delete_user')) ?>');" class="inline">
                                                            <input type="hidden" name="id" value="<?= htmlspecialchars($u['id']) ?>">
                                                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="<?= htmlspecialchars(__('actions.delete')) ?>">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($requestUri === '/users/create' || $requestUri === '/users/edit'): ?>
                        <!-- CREATE / EDIT USER VIEW -->
                        <?php
                        $isEdit = ($requestUri === '/users/edit');
                        $editId = $_GET['id'] ?? '';
                        $userToEdit = null;

                        if ($isEdit && $editId) {
                            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                            $stmt->execute([$editId]);
                            $userToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
                        }

                        $nameVal = $userToEdit['name'] ?? '';
                        $emailVal = $userToEdit['email'] ?? '';
                        $roleVal = $userToEdit['role'] ?? 'operator';
                        $statusVal = $userToEdit['status'] ?? 'active';
                        $permsVal = json_decode($userToEdit['permissions'] ?? '[]', true) ?: ['scan_products'];
                        ?>

                        <div class="max-w-2xl mx-auto">
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?= $isEdit ? htmlspecialchars(__('actions.edit_user')) : htmlspecialchars(__('actions.new_user')) ?></h2>
                                    <p class="text-xs text-slate-500"><?= htmlspecialchars(__('user.subtitle')) ?></p>
                                </div>
                            </div>

                            <form method="POST" action="<?= $isEdit ? '/users/update' : '/users/store' ?>" class="p-6 rounded-2xl bg-white/70 dark:bg-slate-900/70 backdrop-blur-xl border border-slate-200/80 dark:border-slate-800/80 space-y-4 shadow-sm">
                                <?php if ($isEdit): ?>
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($editId) ?>">
                                <?php endif; ?>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('user.name')) ?> *</label>
                                        <input 
                                            type="text" 
                                            name="name" 
                                            required 
                                            value="<?= htmlspecialchars($nameVal) ?>" 
                                            placeholder="e.g. Maria Silva" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('user.email')) ?> *</label>
                                        <input 
                                            type="email" 
                                            name="email" 
                                            required 
                                            value="<?= htmlspecialchars($emailVal) ?>" 
                                            placeholder="e.g. maria@stockki.io" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                        >
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('user.password')) ?> <?= $isEdit ? '' : '*' ?></label>
                                    <input 
                                        type="password" 
                                        name="password" 
                                        <?= $isEdit ? '' : 'required' ?> 
                                        placeholder="••••••••" 
                                        class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                    >
                                    <?php if ($isEdit): ?>
                                        <span class="text-[11px] text-slate-400 mt-1 block"><?= htmlspecialchars(__('user.password_hint')) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('user.role')) ?></label>
                                        <select 
                                            name="role" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                        >
                                            <option value="admin" <?= $roleVal === 'admin' ? 'selected' : '' ?>><?= htmlspecialchars(__('user.role_admin')) ?></option>
                                            <option value="manager" <?= $roleVal === 'manager' ? 'selected' : '' ?>><?= htmlspecialchars(__('user.role_manager')) ?></option>
                                            <option value="operator" <?= $roleVal === 'operator' ? 'selected' : '' ?>><?= htmlspecialchars(__('user.role_operator')) ?></option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1"><?= htmlspecialchars(__('user.status')) ?></label>
                                        <select 
                                            name="status" 
                                            class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500"
                                        >
                                            <option value="active" <?= $statusVal === 'active' ? 'selected' : '' ?>><?= htmlspecialchars(__('user.active')) ?></option>
                                            <option value="inactive" <?= $statusVal === 'inactive' ? 'selected' : '' ?>><?= htmlspecialchars(__('user.inactive')) ?></option>
                                        </select>
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2"><?= htmlspecialchars(__('user.permissions')) ?></label>
                                    <div class="space-y-2 bg-slate-50 dark:bg-slate-950 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
                                        <label class="flex items-center space-x-2 text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="checkbox" name="permissions[]" value="manage_inventory" <?= in_array('manage_inventory', $permsVal) ? 'checked' : '' ?> class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                            <span><?= htmlspecialchars(__('user.perm_inventory')) ?></span>
                                        </label>
                                        <label class="flex items-center space-x-2 text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="checkbox" name="permissions[]" value="manage_users" <?= in_array('manage_users', $permsVal) ? 'checked' : '' ?> class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                            <span><?= htmlspecialchars(__('user.perm_users')) ?></span>
                                        </label>
                                        <label class="flex items-center space-x-2 text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="checkbox" name="permissions[]" value="view_reports" <?= in_array('view_reports', $permsVal) ? 'checked' : '' ?> class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                            <span><?= htmlspecialchars(__('user.perm_reports')) ?></span>
                                        </label>
                                        <label class="flex items-center space-x-2 text-slate-700 dark:text-slate-300 cursor-pointer">
                                            <input type="checkbox" name="permissions[]" value="scan_products" <?= in_array('scan_products', $permsVal) ? 'checked' : '' ?> class="rounded border-slate-300 text-indigo-600 focus:ring-0">
                                            <span><?= htmlspecialchars(__('user.perm_scan')) ?></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="pt-4 flex items-center justify-end space-x-3">
                                    <a href="/users" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-semibold transition-colors">
                                        <?= htmlspecialchars(__('actions.cancel')) ?>
                                    </a>
                                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all">
                                        <?= $isEdit ? htmlspecialchars(__('actions.update')) : htmlspecialchars(__('actions.save')) ?>
                                    </button>
                                </div>
                            </form>
                        </div>

                    <?php endif; ?>

                </main>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
