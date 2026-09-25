<?php
/**
 * WIP Management Portal - MSSQL Database Setup Script
 * Run from CLI or browser: http://localhost/wip-excel/setup_database.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "====================================================\n";
echo " WIP Management Portal - MSSQL Database Setup\n";
echo "====================================================\n\n";

$serversToTry = ['localhost', 'localhost\\SQLEXPRESS', '127.0.0.1'];
$connectedServer = null;
$pdoMaster = null;

foreach ($serversToTry as $srv) {
    try {
        $dsn = "sqlsrv:Server={$srv};Database=master;TrustServerCertificate=true";
        $pdoMaster = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $connectedServer = $srv;
        echo "[OK] Connected to MSSQL Server on: {$srv}\n";
        break;
    } catch (PDOException $e) {
        // try next
    }
}

if (!$pdoMaster) {
    die("[ERROR] Could not connect to any MSSQL Server instance (tried: " . implode(', ', $serversToTry) . ")\n");
}

$dbName = 'wip_excel_db';
$dbDir = 'c:\\xampp\\htdocs\\wip-excel\\database';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

// 1. Check & Create Database
try {
    $checkDb = $pdoMaster->query("SELECT database_id FROM sys.databases WHERE name = '{$dbName}'")->fetch();

    if (!$checkDb) {
        echo "[INFO] Creating database '{$dbName}' in {$dbDir}...\n";
        $mdfPath = $dbDir . '\\wip_excel_db.mdf';
        $ldfPath = $dbDir . '\\wip_excel_db_log.ldf';

        $createDbSql = "
            CREATE DATABASE [{$dbName}]
            ON PRIMARY (
                NAME = N'{$dbName}',
                FILENAME = N'{$mdfPath}'
            )
            LOG ON (
                NAME = N'{$dbName}_log',
                FILENAME = N'{$ldfPath}'
            )
        ";
        $pdoMaster->exec($createDbSql);
        echo "[OK] Database '{$dbName}' created successfully.\n";
    } else {
        echo "[OK] Database '{$dbName}' already exists.\n";
    }
} catch (PDOException $e) {
    die("[ERROR] Failed while creating database: " . $e->getMessage() . "\n");
}

// 2. Connect to the wip_excel_db
try {
    $dsnApp = "sqlsrv:Server={$connectedServer};Database={$dbName};TrustServerCertificate=true";
    $pdoApp = new PDO($dsnApp, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "[OK] Connected to '{$dbName}'.\n";
} catch (PDOException $e) {
    die("[ERROR] Failed to connect to '{$dbName}': " . $e->getMessage() . "\n");
}

// 3. Create users Table
try {
    $createTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='users' AND xtype='U')
    BEGIN
        CREATE TABLE users (
            id INT IDENTITY(1,1) PRIMARY KEY,
            email NVARCHAR(150) NOT NULL UNIQUE,
            password NVARCHAR(255) NOT NULL,
            full_name NVARCHAR(100) NOT NULL,
            role NVARCHAR(50) DEFAULT 'User',
            created_at DATETIME DEFAULT GETDATE(),
            last_login DATETIME NULL
        );
    END
    ";
    $pdoApp->exec($createTableSql);
    echo "[OK] 'users' table is ready.\n";

    // Create rm_master Table
    $createRmTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='rm_master' AND xtype='U')
    BEGIN
        CREATE TABLE rm_master (
            id INT IDENTITY(1,1) PRIMARY KEY,
            rm_code NVARCHAR(50) NOT NULL UNIQUE,
            rm_name NVARCHAR(150) NOT NULL,
            grade_spec NVARCHAR(100) NULL,
            size_dimension NVARCHAR(100) NULL,
            current_stock DECIMAL(18, 3) NOT NULL DEFAULT 0.000,
            uom NVARCHAR(20) NOT NULL,
            status NVARCHAR(50) DEFAULT 'Active',
            is_active BIT DEFAULT 1,
            created_at DATETIME DEFAULT GETDATE(),
            updated_at DATETIME DEFAULT GETDATE()
        );
    END
    ELSE
    BEGIN
        IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='rm_master' AND COLUMN_NAME='current_stock')
        BEGIN
            ALTER TABLE rm_master ADD current_stock DECIMAL(18, 3) NOT NULL DEFAULT 0.000;
        END
    END
    ";
    $pdoApp->exec($createRmTableSql);
    echo "[OK] 'rm_master' table is ready.\n";

    // Create vendor_master Table
    $createVendorTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='vendor_master' AND xtype='U')
    BEGIN
        CREATE TABLE vendor_master (
            id INT IDENTITY(1,1) PRIMARY KEY,
            vendor_code NVARCHAR(50) NOT NULL UNIQUE,
            vendor_name NVARCHAR(150) NOT NULL,
            contact_person NVARCHAR(100) NULL,
            phone NVARCHAR(50) NULL,
            email NVARCHAR(100) NULL,
            gstin NVARCHAR(50) NULL,
            address NVARCHAR(255) NULL,
            status NVARCHAR(50) DEFAULT 'Active',
            created_at DATETIME DEFAULT GETDATE(),
            updated_at DATETIME DEFAULT GETDATE()
        );
    END
    ";
    $pdoApp->exec($createVendorTableSql);
    echo "[OK] 'vendor_master' table is ready.\n";

    // Create child_part_master Table
    $createChildPartTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='child_part_master' AND xtype='U')
    BEGIN
        CREATE TABLE child_part_master (
            id INT IDENTITY(1,1) PRIMARY KEY,
            part_code NVARCHAR(50) NOT NULL UNIQUE,
            part_name NVARCHAR(150) NOT NULL,
            grade_spec NVARCHAR(100) NULL,
            size_dimension NVARCHAR(100) NULL,
            nos_per_kg NVARCHAR(50) NULL,
            current_stock DECIMAL(18, 3) NOT NULL DEFAULT 0.000,
            uom NVARCHAR(20) NOT NULL,
            status NVARCHAR(50) DEFAULT 'Active',
            created_at DATETIME DEFAULT GETDATE(),
            updated_at DATETIME DEFAULT GETDATE()
        );
    END
    ELSE
    BEGIN
        IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='child_part_master' AND COLUMN_NAME='current_stock')
        BEGIN
            ALTER TABLE child_part_master ADD current_stock DECIMAL(18, 3) NOT NULL DEFAULT 0.000;
        END
    END
    ";
    $pdoApp->exec($createChildPartTableSql);
    echo "[OK] 'child_part_master' table is ready.\n";

    // Create part_master Table
    $createPartTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='part_master' AND xtype='U')
    BEGIN
        CREATE TABLE part_master (
            id INT IDENTITY(1,1) PRIMARY KEY,
            part_code NVARCHAR(50) NOT NULL UNIQUE,
            part_name NVARCHAR(150) NOT NULL,
            child_parts NVARCHAR(MAX) NULL,
            status NVARCHAR(50) DEFAULT 'Active',
            created_at DATETIME DEFAULT GETDATE(),
            updated_at DATETIME DEFAULT GETDATE()
        );
    END
    ";
    $pdoApp->exec($createPartTableSql);
    echo "[OK] 'part_master' table is ready.\n";

    // Create process_master Table
    $createProcessTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='process_master' AND xtype='U')
    BEGIN
        CREATE TABLE process_master (
            id INT IDENTITY(1,1) PRIMARY KEY,
            process_code NVARCHAR(50) NOT NULL UNIQUE,
            process_name NVARCHAR(150) NOT NULL,
            status NVARCHAR(50) DEFAULT 'Active',
            remarks NVARCHAR(500) NULL,
            created_at DATETIME DEFAULT GETDATE(),
            updated_at DATETIME DEFAULT GETDATE()
        );
    END
    ";
    $pdoApp->exec($createProcessTableSql);
    echo "[OK] 'process_master' table is ready.\n";

    // Create rm_inward Table
    $createRmInwardTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='rm_inward' AND xtype='U')
    BEGIN
        CREATE TABLE rm_inward (
            id INT IDENTITY(1,1) PRIMARY KEY,
            inward_no NVARCHAR(50) NOT NULL,
            inward_date DATE NOT NULL,
            vendor_id INT NULL,
            vendor_name NVARCHAR(150) NOT NULL,
            invoice_no NVARCHAR(50) NULL,
            invoice_date DATE NULL,
            rm_code NVARCHAR(50) NOT NULL,
            rm_name NVARCHAR(150) NOT NULL,
            received_qty DECIMAL(18, 3) NOT NULL,
            uom NVARCHAR(20) NOT NULL DEFAULT 'KG',
            created_at DATETIME DEFAULT GETDATE(),
            updated_at DATETIME DEFAULT GETDATE()
        );
    END
    ";
    $pdoApp->exec($createRmInwardTableSql);
    echo "[OK] 'rm_inward' table is ready.\n";

    // Create child_part_inward Table
    $createChildPartInwardTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='child_part_inward' AND xtype='U')
    BEGIN
        CREATE TABLE child_part_inward (
            id INT IDENTITY(1,1) PRIMARY KEY,
            inward_no NVARCHAR(50) NOT NULL,
            inward_date DATE NOT NULL,
            vendor_id INT NULL,
            vendor_name NVARCHAR(150) NOT NULL,
            invoice_no NVARCHAR(50) NULL,
            invoice_date DATE NULL,
            part_code NVARCHAR(50) NOT NULL,
            part_name NVARCHAR(150) NOT NULL,
            received_qty DECIMAL(18, 3) NOT NULL,
            uom NVARCHAR(20) NOT NULL DEFAULT 'NOS',
            created_at DATETIME DEFAULT GETDATE(),
            updated_at DATETIME DEFAULT GETDATE()
        );
    END
    ";
    $pdoApp->exec($createChildPartInwardTableSql);
    echo "[OK] 'child_part_inward' table is ready.\n";

    // Create material_issue Table (MIP)
    $createMipTableSql = "
    IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='material_issue' AND xtype='U')
    BEGIN
        CREATE TABLE material_issue (
            id INT IDENTITY(1,1) PRIMARY KEY,
            issue_no NVARCHAR(50) NOT NULL UNIQUE,
            issue_date DATE NOT NULL,
            part_code NVARCHAR(50) NOT NULL,
            part_name NVARCHAR(150) NOT NULL,
            issued_qty DECIMAL(18, 3) NOT NULL,
            uom NVARCHAR(20) NOT NULL DEFAULT 'NOS',
            work_order NVARCHAR(100) NULL,
            department NVARCHAR(100) NULL,
            status NVARCHAR(50) DEFAULT 'Issued',
            remarks NVARCHAR(500) NULL,
            child_parts_details NVARCHAR(MAX) NULL,
            created_at DATETIME DEFAULT GETDATE(),
            updated_at DATETIME DEFAULT GETDATE()
        );
    END
    ";
    $pdoApp->exec($createMipTableSql);
    echo "[OK] 'material_issue' table is ready.\n";
} catch (PDOException $e) {
    die("[ERROR] Failed to create tables: " . $e->getMessage() . "\n");
}

// 4. Seed Default Users
$seedUsers = [
    [
        'email' => 'admin@wip.com',
        'password' => 'admin123',
        'full_name' => 'Administrator',
        'role' => 'Administrator'
    ],
    [
        'email' => 'user@wip.com',
        'password' => 'user123',
        'full_name' => 'Demo User',
        'role' => 'User'
    ]
];

foreach ($seedUsers as $u) {
    try {
        $stmt = $pdoApp->prepare("SELECT COUNT(*) AS cnt FROM users WHERE email = ?");
        $stmt->execute([$u['email']]);
        $row = $stmt->fetch();

        if ($row['cnt'] == 0) {
            $insertStmt = $pdoApp->prepare("INSERT INTO users (email, password, full_name, role) VALUES (?, ?, ?, ?)");
            $insertStmt->execute([$u['email'], $u['password'], $u['full_name'], $u['role']]);
            echo "[OK] Created user: {$u['email']} (Password: {$u['password']}) [Role: {$u['role']}]\n";
        } else {
            echo "[INFO] User '{$u['email']}' already exists.\n";
        }
    } catch (PDOException $e) {
        echo "[ERROR] Failed to seed user {$u['email']}: " . $e->getMessage() . "\n";
    }
}

echo "\n====================================================\n";
echo " Setup Completed Successfully!\n";
echo " Default Logins:\n";
echo " 1) Email: admin@wip.com | Password: admin123\n";
echo " 2) Email: user@wip.com  | Password: user123\n";
echo "====================================================\n";
