<?php
require_once "resources/lib/analytics_logic.php";
require_once "resources/lib/nepali_calendar.php";

$user = user();
$reg = $user->registrationNumber;

$sql = "SELECT a.*, c.courseName, u.name as unitName 
        FROM tblattendance a
        JOIN tblcourse c ON a.course = c.courseCode
        JOIN tblunit u ON a.unit = u.unitCode
        WHERE a.studentRegistrationNumber = :reg
        ORDER BY a.dateMarked DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':reg' => $reg]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Attendance</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
</head>
<body>
    <?php include 'resources/pages/administrator/includes/topbar.php' ?>
    <section class="main">
        <div class="sidebar">
            <ul class="sidebar--items">
                <li><a href="home"><span class="icon"><i class="ri-dashboard-line"></i></span>Dashboard</a></li>
                <li><a href="attendance" id="active--link"><span class="icon"><i class="ri-calendar-check-line"></i></span>My Attendance</a></li>
                <li><a href="notices"><span class="icon"><i class="ri-notification-3-line"></i></span>Notice Board</a></li>
            </ul>
            <ul class="sidebar--bottom-items">
                <li><a href="logout"><span class="icon"><i class="ri-logout-box-r-line"></i></span>Logout</a></li>
            </ul>
        </div>

        <div class="main--content">
            <div class="title">
                <h2 class="section--title">Detailed Attendance History</h2>
            </div>

            <div class="table-container">
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Confidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr><td colspan="5">No attendance records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($records as $row): ?>
                                    <tr>
                                        <td><?php echo formatNepaliDate($row['dateMarked']); ?></td>
                                        <td><?php echo $row['courseName']; ?></td>
                                        <td><?php echo $row['unitName']; ?></td>
                                        <td>
                                            <span style="padding: 4px 12px; border-radius: 99px; font-size: 0.8rem; background: <?php echo $row['attendanceStatus'] == 'Present' ? '#dcfce7' : '#fee2e2'; ?>; color: <?php echo $row['attendanceStatus'] == 'Present' ? '#166534' : '#991b1b'; ?>;">
                                                <?php echo $row['attendanceStatus']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $row['confidence'] ? round($row['confidence'], 1) . '%' : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
