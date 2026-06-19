<?php
/**
 * Migration: Add Semester System
 * Run once via browser: http://localhost/new/Face-Recognition-Attendance-System/database/add_semester.php
 * Safe to re-run — uses IF NOT EXISTS guards throughout.
 */

require_once __DIR__ . '/database_connection.php';

$steps = [];

// -------------------------------------------------------------------
// 1. Create tblsemester
// -------------------------------------------------------------------
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tblsemester` (
            `Id`          INT(10)      NOT NULL AUTO_INCREMENT,
            `facultyCode` VARCHAR(50)  NOT NULL,
            `name`        VARCHAR(100) NOT NULL,
            `startDate`   DATE         NOT NULL,
            `endDate`     DATE         NOT NULL,
            `isActive`    TINYINT(1)   NOT NULL DEFAULT 0,
            `dateCreated` DATE         NOT NULL,
            PRIMARY KEY (`Id`),
            KEY `idx_faculty` (`facultyCode`),
            KEY `idx_active`  (`facultyCode`, `isActive`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    $steps[] = ['ok', 'Created (or already exists): tblsemester'];
} catch (PDOException $e) {
    $steps[] = ['err', 'tblsemester: ' . $e->getMessage()];
}

// -------------------------------------------------------------------
// 2. Add semesterID column to tblfacultycalendar (if not present)
// -------------------------------------------------------------------
try {
    // Check if column already exists
    $check = $pdo->query("SHOW COLUMNS FROM `tblfacultycalendar` LIKE 'semesterID'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `tblfacultycalendar` ADD COLUMN `semesterID` INT(10) NOT NULL DEFAULT 0 AFTER `facultyCode`");
        $steps[] = ['ok', 'Added semesterID column to tblfacultycalendar'];
    } else {
        $steps[] = ['skip', 'semesterID column already exists in tblfacultycalendar'];
    }
} catch (PDOException $e) {
    $steps[] = ['err', 'Add semesterID column: ' . $e->getMessage()];
}

// -------------------------------------------------------------------
// 3. Drop old unique key on (facultyCode, classDate) if it exists,
//    and add new unique key on (facultyCode, semesterID, classDate)
// -------------------------------------------------------------------
try {
    // Check existing indexes
    $indexes = $pdo->query("SHOW INDEX FROM `tblfacultycalendar`")->fetchAll(PDO::FETCH_ASSOC);
    $keyNames = array_column($indexes, 'Key_name');

    // Drop old unique if present
    foreach (['facultyCode', 'faculty_date_unique', 'unique_faculty_date'] as $oldKey) {
        if (in_array($oldKey, $keyNames)) {
            $pdo->exec("ALTER TABLE `tblfacultycalendar` DROP INDEX `$oldKey`");
            $steps[] = ['ok', "Dropped old index: $oldKey"];
        }
    }

    // Add new unique index if not already there
    $newKeyExists = false;
    foreach ($indexes as $idx) {
        if ($idx['Key_name'] === 'unique_faculty_semester_date') {
            $newKeyExists = true; break;
        }
    }
    if (!$newKeyExists) {
        $pdo->exec("ALTER TABLE `tblfacultycalendar` ADD UNIQUE KEY `unique_faculty_semester_date` (`facultyCode`, `semesterID`, `classDate`)");
        $steps[] = ['ok', 'Added unique index (facultyCode, semesterID, classDate) to tblfacultycalendar'];
    } else {
        $steps[] = ['skip', 'Unique index unique_faculty_semester_date already exists'];
    }
} catch (PDOException $e) {
    $steps[] = ['err', 'Index update: ' . $e->getMessage()];
}

// -------------------------------------------------------------------
// 4. Verify final table structure
// -------------------------------------------------------------------
try {
    $semCount = $pdo->query("SELECT COUNT(*) FROM tblsemester")->fetchColumn();
    $calCols  = array_column($pdo->query("SHOW COLUMNS FROM tblfacultycalendar")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $steps[]  = ['ok', "tblsemester exists with $semCount rows. tblfacultycalendar columns: " . implode(', ', $calCols)];
} catch (PDOException $e) {
    $steps[] = ['err', 'Verification: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Semester Migration</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 40px; }
        h1   { color: #38bdf8; margin-bottom: 30px; }
        .step { display: flex; gap: 16px; padding: 10px 0; border-bottom: 1px solid #1e293b; }
        .badge { padding: 2px 10px; border-radius: 4px; font-size: 0.82rem; font-weight: 700; min-width: 50px; text-align: center; }
        .ok   { background: #166534; color: #bbf7d0; }
        .err  { background: #7f1d1d; color: #fecaca; }
        .skip { background: #1e3a5f; color: #bae6fd; }
        .done { margin-top: 30px; padding: 16px; background: #14532d; border-radius: 8px; color: #86efac; font-size: 1.1rem; }
    </style>
</head>
<body>
    <h1>🗂️ Semester System Migration</h1>
    <?php foreach ($steps as [$type, $msg]): ?>
        <div class="step">
            <span class="badge <?= $type ?>"><?= strtoupper($type) ?></span>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
    <?php endforeach; ?>
    <div class="done">✅ Migration complete. You can now close this page.</div>
</body>
</html>
