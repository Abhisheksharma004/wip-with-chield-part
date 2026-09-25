<?php
/**
 * One-time migration: Add received_by column to material_issue table
 */
require_once __DIR__ . '/config/db.php';

$pdo = getDBConnection();
if (!$pdo) {
    die('DB connection failed');
}

try {
    $sql = "
        IF NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'material_issue' AND COLUMN_NAME = 'received_by'
        )
        BEGIN
            ALTER TABLE material_issue ADD received_by NVARCHAR(150) NULL
            PRINT 'Column received_by added successfully.'
        END
        ELSE
        BEGIN
            PRINT 'Column received_by already exists.'
        END
    ";
    $pdo->exec($sql);
    echo '<strong style="color:green;">✓ Migration complete: received_by column is ready in material_issue table.</strong>';
} catch (Exception $e) {
    echo '<strong style="color:red;">Error: ' . htmlspecialchars($e->getMessage()) . '</strong>';
}
?>
