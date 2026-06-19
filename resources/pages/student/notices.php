<?php
require_once "resources/lib/analytics_logic.php";
require_once "resources/lib/nepali_calendar.php";

$user = user();

if (isset($_POST['addNotice'])) {
    $title = htmlspecialchars($_POST['title']);
    $message = htmlspecialchars($_POST['message']);
    
    $stmt = $pdo->prepare("INSERT INTO tblnotices (title, message, postedBy, postedByRole) VALUES (:title, :message, :by, :role)");
    $stmt->execute([
        ':title' => $title,
        ':message' => $message,
        ':by' => $user->registrationNumber,
        ':role' => 'Student (' . $user->name . ')'
    ]);
    $_SESSION['message'] = "Notice posted successfully";
}

$notices = getLatestNotices(20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Notice Board</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
    <style>
        .notice-card {
            background: #fff;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            position: relative;
        }
        .notice-badge {
            position: absolute;
            top: 24px;
            right: 24px;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 99px;
            background: #eff6ff;
            color: #2563eb;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group textarea {
            width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include 'resources/pages/administrator/includes/topbar.php' ?>
    <section class="main">
        <div class="sidebar">
            <ul class="sidebar--items">
                <li><a href="home"><span class="icon"><i class="ri-dashboard-line"></i></span>Dashboard</a></li>
                <li><a href="attendance"><span class="icon"><i class="ri-calendar-check-line"></i></span>My Attendance</a></li>
                <li><a href="notices" id="active--link"><span class="icon"><i class="ri-notification-3-line"></i></span>Notice Board</a></li>
            </ul>
            <ul class="sidebar--bottom-items">
                <li><a href="logout"><span class="icon"><i class="ri-logout-box-r-line"></i></span>Logout</a></li>
            </ul>
        </div>

        <div class="main--content">
            <div class="title">
                <h2 class="section--title">Notice Board</h2>
                <button class="add" onclick="document.getElementById('noticeForm').style.display='block'"><i class="ri-add-line"></i>Post Notice</button>
            </div>

            <?php showMessage(); ?>

            <div id="noticeForm" class="table-container" style="display:none; margin-bottom: 30px;">
                <h3>Post New Notice</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" required placeholder="e.g. Study Group Meeting">
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" rows="4" required placeholder="Details about the notice..."></textarea>
                    </div>
                    <button type="submit" name="addNotice" class="submit">Post Announcement</button>
                    <button type="button" class="submit" style="background:#64748b;" onclick="document.getElementById('noticeForm').style.display='none'">Cancel</button>
                </form>
            </div>

            <div class="notices-container">
                <?php foreach ($notices as $notice): ?>
                    <div class="notice-card">
                        <span class="notice-badge"><?php echo $notice['postedByRole']; ?></span>
                        <h3 style="margin-bottom: 10px; padding-right: 120px;"><?php echo htmlspecialchars($notice['title']); ?></h3>
                        <p style="color: #475569; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($notice['message'])); ?></p>
                        <div style="margin-top: 15px; font-size: 0.85rem; color: #94a3b8;">
                            <i class="ri-time-line"></i> Posted on <?php echo formatNepaliDate($notice['postedDate']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</body>
</html>
