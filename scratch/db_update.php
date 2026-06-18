<?php
require_once "database/database_connection.php";

try {
    // Add password column to tblStudents
    $pdo->exec("ALTER TABLE tblStudents ADD COLUMN password VARCHAR(255) DEFAULT NULL");
    echo "Added password column to tblStudents\n";

    // Update existing students with a default password '123456'
    $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
    $pdo->exec("UPDATE tblStudents SET password = '$defaultPassword' WHERE password IS NULL");
    echo "Set default passwords for existing students\n";

    // Create tblnotices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS tblnotices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        postedBy VARCHAR(100) NOT NULL,
        postedByRole VARCHAR(50) NOT NULL,
        postedDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Created tblnotices table\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
