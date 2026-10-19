<?php

$dbPath = __DIR__ . '/database.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Drop existing tables for fresh schema upgrade in dev
$pdo->exec("DROP TABLE IF EXISTS products;");
$pdo->exec("DROP TABLE IF EXISTS users;");

// Create users table with Role-Based Access Control (RBAC) & Status
$pdo->exec("
    CREATE TABLE users (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'operator',
        status TEXT NOT NULL DEFAULT 'active',
        permissions TEXT,
        preferred_locale TEXT DEFAULT 'en',
        created_at TEXT,
        updated_at TEXT
    );
");

// Create products table
$pdo->exec("
    CREATE TABLE products (
        id TEXT PRIMARY KEY,
        user_id TEXT NOT NULL,
        name TEXT NOT NULL,
        sku TEXT NOT NULL,
        quantity INTEGER DEFAULT 0,
        batch_number TEXT,
        manufacture_date TEXT,
        expiry_date TEXT NOT NULL,
        safety_stock INTEGER DEFAULT 0,
        image_url TEXT,
        extraction_metadata TEXT,
        created_at TEXT,
        updated_at TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
");

// Seed Initial Team Users with distinct roles and permission sets
$users = [
    [
        'id' => '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
        'name' => 'Kaio Borges',
        'email' => 'admin@stockki.io',
        'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
        'role' => 'admin',
        'status' => 'active',
        'permissions' => json_encode(['manage_inventory', 'manage_users', 'view_reports', 'scan_products']),
        'preferred_locale' => 'en',
    ],
    [
        'id' => 'a22deb4d-3b7d-4bad-9bdd-2b0d7b3dcba2',
        'name' => 'Maria Silva',
        'email' => 'maria.silva@stockki.io',
        'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
        'role' => 'manager',
        'status' => 'active',
        'permissions' => json_encode(['manage_inventory', 'view_reports', 'scan_products']),
        'preferred_locale' => 'pt',
    ],
    [
        'id' => 'b33deb4d-3b7d-4bad-9bdd-2b0d7b3dcbb3',
        'name' => 'Carlos Souza',
        'email' => 'carlos.souza@stockki.io',
        'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
        'role' => 'operator',
        'status' => 'active',
        'permissions' => json_encode(['scan_products']),
        'preferred_locale' => 'pt',
    ],
    [
        'id' => 'c44deb4d-3b7d-4bad-9bdd-2b0d7b3dcbc4',
        'name' => 'Juliana Mendes',
        'email' => 'juliana.mendes@stockki.io',
        'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
        'role' => 'operator',
        'status' => 'inactive',
        'permissions' => json_encode(['scan_products']),
        'preferred_locale' => 'en',
    ],
];

$insertUser = $pdo->prepare("
    INSERT INTO users (id, name, email, password_hash, role, status, permissions, preferred_locale, created_at, updated_at)
    VALUES (:id, :name, :email, :password_hash, :role, :status, :permissions, :preferred_locale, datetime('now'), datetime('now'))
");

foreach ($users as $u) {
    $insertUser->execute([
        ':id' => $u['id'],
        ':name' => $u['name'],
        ':email' => $u['email'],
        ':password_hash' => $u['password_hash'],
        ':role' => $u['role'],
        ':status' => $u['status'],
        ':permissions' => $u['permissions'],
        ':preferred_locale' => $u['preferred_locale'],
    ]);
}

// Seed Products linked to Primary Tenant
$userId = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';
$now = new DateTime('2026-10-18');

$products = [
    [
        'id' => '11111111-1111-4111-8111-111111111111',
        'name' => 'Amoxicillin Trihydrate 500mg',
        'sku' => 'MED-AMX-500',
        'quantity' => 14,
        'batch_number' => 'LOT-202604-01',
        'manufacture_date' => '2025-10-01',
        'expiry_date' => (clone $now)->modify('-12 days')->format('Y-m-d'), // Expired 12 days ago
        'safety_stock' => 20,
        'image_url' => null,
        'extraction_metadata' => json_encode(['confidence' => 0.98, 'source' => 'vision_scan']),
    ],
    [
        'id' => '22222222-2222-4222-8222-222222222222',
        'name' => 'Hydrocortisone Cream 1%',
        'sku' => 'DERM-HYD-01',
        'quantity' => 6,
        'batch_number' => 'LOT-202603-14',
        'manufacture_date' => '2025-09-15',
        'expiry_date' => (clone $now)->modify('-3 days')->format('Y-m-d'), // Expired 3 days ago
        'safety_stock' => 15,
        'image_url' => null,
        'extraction_metadata' => json_encode(['confidence' => 0.94, 'source' => 'manual']),
    ],
    [
        'id' => '33333333-3333-4333-8333-333333333333',
        'name' => 'Ibuprofen Suspension 100mg/5ml',
        'sku' => 'PED-IBU-100',
        'quantity' => 32,
        'batch_number' => 'LOT-202607-88',
        'manufacture_date' => '2026-01-10',
        'expiry_date' => (clone $now)->modify('+4 days')->format('Y-m-d'), // Expiring in 4 days
        'safety_stock' => 10,
        'image_url' => null,
        'extraction_metadata' => json_encode(['confidence' => 0.96, 'source' => 'vision_scan']),
    ],
    [
        'id' => '44444444-4444-4444-8444-444444444444',
        'name' => 'Paracetamol Oral Solution 200mg',
        'sku' => 'PED-PAR-200',
        'quantity' => 18,
        'batch_number' => 'LOT-202608-20',
        'manufacture_date' => '2026-02-01',
        'expiry_date' => (clone $now)->modify('+14 days')->format('Y-m-d'), // Expiring in 14 days
        'safety_stock' => 25,
        'image_url' => null,
        'extraction_metadata' => json_encode(['confidence' => 0.91, 'source' => 'vision_scan']),
    ],
    [
        'id' => '55555555-5555-4555-8555-555555555555',
        'name' => 'Ascorbic Acid (Vitamin C) 1000mg',
        'sku' => 'VIT-ASC-1000',
        'quantity' => 85,
        'batch_number' => 'LOT-202609-02',
        'manufacture_date' => '2026-03-01',
        'expiry_date' => (clone $now)->modify('+27 days')->format('Y-m-d'), // Expiring in 27 days
        'safety_stock' => 30,
        'image_url' => null,
        'extraction_metadata' => json_encode(['confidence' => 0.97, 'source' => 'manual']),
    ],
    [
        'id' => '66666666-6666-4666-8666-666666666666',
        'name' => 'Omeprazole Delayed-Release 20mg',
        'sku' => 'MED-OME-020',
        'quantity' => 120,
        'batch_number' => 'LOT-202610-15',
        'manufacture_date' => '2026-04-10',
        'expiry_date' => (clone $now)->modify('+180 days')->format('Y-m-d'), // Safe
        'safety_stock' => 40,
        'image_url' => null,
        'extraction_metadata' => json_encode(['confidence' => 0.99, 'source' => 'vision_scan']),
    ],
    [
        'id' => '77777777-7777-4777-8777-777777777777',
        'name' => 'Sodium Chloride 0.9% IV 500ml',
        'sku' => 'HOSP-SAL-500',
        'quantity' => 240,
        'batch_number' => 'LOT-202611-09',
        'manufacture_date' => '2026-05-15',
        'expiry_date' => (clone $now)->modify('+320 days')->format('Y-m-d'), // Safe
        'safety_stock' => 50,
        'image_url' => null,
        'extraction_metadata' => json_encode(['confidence' => 0.98, 'source' => 'manual']),
    ],
    [
        'id' => '88888888-8888-4888-8888-888888888888',
        'name' => 'Chlorhexidine Antiseptic Spray 100ml',
        'sku' => 'SURG-CHX-100',
        'quantity' => 64,
        'batch_number' => 'LOT-202612-44',
        'manufacture_date' => '2026-06-01',
        'expiry_date' => (clone $now)->modify('+410 days')->format('Y-m-d'), // Safe
        'safety_stock' => 20,
        'image_url' => null,
        'extraction_metadata' => json_encode(['confidence' => 0.95, 'source' => 'vision_scan']),
    ],
];

$insertProduct = $pdo->prepare("
    INSERT INTO products (id, user_id, name, sku, quantity, batch_number, manufacture_date, expiry_date, safety_stock, image_url, extraction_metadata, created_at, updated_at)
    VALUES (:id, :user_id, :name, :sku, :quantity, :batch_number, :manufacture_date, :expiry_date, :safety_stock, :image_url, :extraction_metadata, datetime('now'), datetime('now'))
");

foreach ($products as $p) {
    $insertProduct->execute([
        ':id' => $p['id'],
        ':user_id' => $userId,
        ':name' => $p['name'],
        ':sku' => $p['sku'],
        ':quantity' => $p['quantity'],
        ':batch_number' => $p['batch_number'],
        ':manufacture_date' => $p['manufacture_date'],
        ':expiry_date' => $p['expiry_date'],
        ':safety_stock' => $p['safety_stock'],
        ':image_url' => $p['image_url'],
        ':extraction_metadata' => $p['extraction_metadata'],
    ]);
}

echo "Database upgraded and seeded with RBAC users and inventory records.\n";
