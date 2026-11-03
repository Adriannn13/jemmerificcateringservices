<?php
// auth/migrate_venue_ai.php
require_once __DIR__ . '/../db_config.php';

function checkAndAddColumn($conn, $table, $column, $definition) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows === 0) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        $conn->query($sql);
    }
}

try {
    checkAndAddColumn($conn, 'bookings', 'venue_image', 'VARCHAR(255) NULL AFTER special_requests');
    checkAndAddColumn($conn, 'bookings', 'ai_venue_analysis', 'TEXT NULL AFTER venue_image');
    checkAndAddColumn($conn, 'bookings', 'ai_conceptual_image', 'VARCHAR(255) NULL AFTER ai_venue_analysis');
    checkAndAddColumn($conn, 'bookings', 'layout_approval_status', "VARCHAR(40) NOT NULL DEFAULT 'none' AFTER ai_conceptual_image");
    checkAndAddColumn($conn, 'bookings', 'layout_generated_by', "VARCHAR(100) NULL AFTER layout_approval_status");
    checkAndAddColumn($conn, 'bookings', 'layout_generated_at', "DATETIME NULL AFTER layout_generated_by");
    checkAndAddColumn($conn, 'bookings', 'layout_approved_by', "VARCHAR(100) NULL AFTER layout_generated_at");
    checkAndAddColumn($conn, 'bookings', 'layout_approved_at', "DATETIME NULL AFTER layout_approved_by");
    checkAndAddColumn($conn, 'bookings', 'layout_admin_notes', "TEXT NULL AFTER layout_approved_at");
    
    // Ensure uploads directory and subdirectories exist
    $upload_dir = __DIR__ . '/../uploads';
    $ai_concept_dir = __DIR__ . '/../uploads/ai_conceptual';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    if (!file_exists($ai_concept_dir)) {
        mkdir($ai_concept_dir, 0777, true);
    }
} catch (Exception $e) {
    error_log("Migration error: " . $e->getMessage());
}
?>
