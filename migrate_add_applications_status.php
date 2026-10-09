<?php
require_once 'config/db.php';

echo "Migration: ensure applications table and status column exist\n";

$check = $conn->query("SHOW TABLES LIKE 'applications'");
if ($check && $check->num_rows === 0) {
    echo "Creating applications table...\n";
        $sql = "CREATE TABLE applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            scholarship_program_id INT NOT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('For Review','Pending','Accepted','Rejected') NOT NULL DEFAULT 'For Review',
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (scholarship_program_id) REFERENCES scholarship_programs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB";
    if ($conn->query($sql) === TRUE) {
        echo "applications table created.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
} else {
    echo "applications table exists. Checking for status column...\n";
    $col = $conn->query("SHOW COLUMNS FROM applications LIKE 'status'");
    if ($col && $col->num_rows === 0) {
        echo "Adding status column...\n";
        $alter = "ALTER TABLE applications ADD COLUMN status ENUM('For Review','Pending','Accepted','Rejected') NOT NULL DEFAULT 'For Review' AFTER applied_at";
        if ($conn->query($alter) === TRUE) {
            echo "status column added.\n";
        } else {
            echo "Error adding column: " . $conn->error . "\n";
        }
    } else {
        echo "status column already present.\n";
    }
}

// Optional: show count
$r = $conn->query('SELECT COUNT(*) AS n FROM applications');
if ($r) {
    echo 'Total applications: ' . $r->fetch_assoc()['n'] . "\n";
}

echo "Migration complete.\n";
