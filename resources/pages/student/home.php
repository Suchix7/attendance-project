<?php
require_once "resources/lib/analytics_logic.php";

$user = user();
$risk = calculateAttendanceRisk($user->registrationNumber);
$notices = getLatestNotices(3);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        .risk-card {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        .progress-container {
            height: 12px;
            background: #f1f5f9;
            border-radius: 6px;
            margin: 16px 0;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            transition: width 1s ease-in-out;
        }
        .notice-item {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
        }
        .notice-item:last-child { border-bottom: none; }
        .notice-meta { font-size: 0.8rem; color: #64748b; margin-top: 4px; }
    </style>
</head>
<body>
    <?php include 'resources/pages/administrator/includes/topbar.php' ?>
    <section class="main">
        <div class="sidebar">
            <ul class="sidebar--items">
                <li><a href="home" id="active--link"><span class="icon"><i class="ri-dashboard-line"></i></span>Dashboard</a></li>
                <li><a href="attendance"><span class="icon"><i class="ri-calendar-check-line"></i></span>My Attendance</a></li>
                <li><a href="notices"><span class="icon"><i class="ri-notification-3-line"></i></span>Notice Board</a></li>
            </ul>
            <ul class="sidebar--bottom-items">
                <li><a href="logout"><span class="icon"><i class="ri-logout-box-r-line"></i></span>Logout</a></li>
            </ul>
        </div>

        <div class="main--content">
            <div class="title">
                <h2 class="section--title">Welcome, <?php echo $user->name; ?></h2>
            </div>

            <div class="risk-card">
                <h3>Overall Attendance Progress</h3>
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?php echo $risk['percentage']; ?>%; background-color: <?php echo $risk['color']; ?>;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Status: <strong style="color: <?php echo $risk['color']; ?>;"><?php echo $risk['level']; ?></strong></span>
                    <span><?php echo $risk['percentage']; ?>% (<?php echo $risk['present']; ?>/<?php echo $risk['total']; ?> Classes)</span>
                </div>
            </div>

            <div class="cards">
                <div class="card card-1">
                    <div class="card--data">
                        <div class="card--content">
                            <h5 class="card--title">Total Present</h5>
                            <h1><?php echo $risk['present']; ?></h1>
                        </div>
                        <i class="ri-user-check-line card--icon--lg"></i>
                    </div>
                </div>
                <div class="card card-1" style="border-left-color: #f59e0b;">
                    <div class="card--data">
                        <div class="card--content">
                            <h5 class="card--title">Risk Level</h5>
                            <h1 style="color: <?php echo $risk['color']; ?>;"><?php echo $risk['level']; ?></h1>
                        </div>
                        <i class="ri-alert-line card--icon--lg" style="color: <?php echo $risk['color']; ?>;"></i>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="title">
                    <h2 class="section--title">Latest Notices</h2>
                    <a href="notices" class="add">View All</a>
                </div>
                <div class="notices-list">
                    <?php if (empty($notices)): ?>
                        <p style="padding: 20px; color: #64748b;">No active notices.</p>
                    <?php else: ?>
                        <?php foreach ($notices as $notice): ?>
                            <div class="notice-item">
                                <strong><?php echo htmlspecialchars($notice['title']); ?></strong>
                                <p style="margin-top: 5px;"><?php echo htmlspecialchars(substr($notice['message'], 0, 100)) . '...'; ?></p>
                                <div class="notice-meta">
                                    Posted by <?php echo $notice['postedByRole']; ?> on <?php echo date('M d, Y', strtotime($notice['postedDate'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
