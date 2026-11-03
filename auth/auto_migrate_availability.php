<?php
// auth/auto_migrate_availability.php - Auto-ensures availability_schedule table exists
require_once __DIR__ . '/../db_config.php';

function ensureAvailabilityTableExists($conn) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `availability_schedule` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `schedule_date` DATE NOT NULL,
          `status` ENUM('Available', 'Limited Slots', 'Fully Booked', 'Closed', 'Holiday') DEFAULT 'Available',
          `max_events` INT(11) DEFAULT 3,
          `current_events` INT(11) DEFAULT 0,
          `remaining_slots` INT(11) DEFAULT 3,
          `reason` VARCHAR(255) DEFAULT NULL,
          `notes` TEXT DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `schedule_date_idx` (`schedule_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

        $conn->query($sql);
    } catch (Exception $e) {
        // Table already exists or initialized
    }
}

// Automatically invoke table check
ensureAvailabilityTableExists($conn);
