<?php
require 'database/database_connection.php';

// 1. Check if CIT faculty exists, if not insert it
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tblfaculty WHERE facultyCode = 'CIT'");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
    $stmtInsert = $pdo->prepare("INSERT INTO tblfaculty (facultyName, facultyCode, dateRegistered) VALUES ('Computing and Information Technology', 'CIT', '2024-04-07')");
    $stmtInsert->execute();
    echo "Inserted CIT faculty\n";
}

// 2. Check if mark@gmail.com exists, if so update password, if not insert
$stmt = $pdo->prepare("SELECT Id FROM tbllecture WHERE emailAddress = 'mark@gmail.com'");
$stmt->execute();
$lecturer = $stmt->fetch();

$passwordHash = password_hash('@mark_', PASSWORD_DEFAULT);

if ($lecturer) {
    $stmtUpdate = $pdo->prepare("UPDATE tbllecture SET password = :pass, firstName = 'mark', lastName = 'lila', facultyCode = 'CIT' WHERE Id = :id");
    $stmtUpdate->execute([':pass' => $passwordHash, ':id' => $lecturer['Id']]);
    echo "Updated existing lecturer 'mark@gmail.com'\n";
} else {
    $stmtInsert = $pdo->prepare("INSERT INTO tbllecture (firstName, lastName, emailAddress, password, phoneNo, facultyCode, dateCreated) VALUES ('mark', 'lila', 'mark@gmail.com', :pass, '07123456789', 'CIT', '2024-04-07')");
    $stmtInsert->execute([':pass' => $passwordHash]);
    echo "Inserted new lecturer 'mark@gmail.com'\n";
}
?>
