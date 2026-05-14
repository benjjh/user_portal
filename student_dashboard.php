<?php
require_once 'config.php';

if (!isset($_SESSION["user_loggedin"]) || $_SESSION["user_loggedin"] !== true) {
    header("location: user_login.php");
    exit;
}
if (($_SESSION["role"] ?? '') !== 'student') {
    header("location: teacher_dashboard.php");
    exit;
}

$student_id = (int)$_SESSION["user_id"];

$stmt = $conn->prepare("SELECT name, surname, photo FROM users WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$sdata = $stmt->get_result()->fetch_assoc();
$stmt->close();

$full_name = trim(($sdata['name'] ?? '') . ' ' . ($sdata['surname'] ?? ''));

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM enrollments WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subject_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM grades WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$grade_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM attendance WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$att_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Count pending homework (due today or in future) for enrolled subjects
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt
     FROM homework h
     JOIN enrollments e ON e.subject_id = h.subject_id
     WHERE e.student_id = ? AND (h.due_date IS NULL OR h.due_date >= CURDATE())"
);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$hw_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f8; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a0c4ff; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }
        .hero { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: white; padding: 36px 40px 60px; }
        .hero-inner { max-width: 1100px; margin: 0 auto; display: flex; align-items: center; gap: 24px; }
        .hero-avatar { width: 72px; height: 72px; border-radius: 50%; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 2rem; border: 3px solid rgba(255,255,255,0.3); flex-shrink: 0; }
        .hero h1 { font-size: 1.7rem; font-weight: 700; }
        .hero p { color: #a0c4ff; margin-top: 4px; }
        .main { flex: 1; max-width: 1100px; margin: -32px auto 40px; padding: 0 20px; width: 100%; }
        .stats { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
        .stat-card { background: white; border-radius: 10px; padding: 20px 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); flex: 1; min-width: 130px; text-align: center; }
        .stat-card .num { font-size: 2rem; font-weight: 800; color: #1a1a2e; }
        .stat-card .lbl { font-size: 0.85rem; color: #666; margin-top: 4px; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .menu-card { background: white; border-radius: 12px; padding: 24px 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); text-decoration: none; color: #1a1a2e; display: flex; flex-direction: column; align-items: flex-start; gap: 10px; border-left: 4px solid #1a1a2e; transition: transform 0.15s, box-shadow 0.15s; }
        .menu-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .menu-card .icon { font-size: 2rem; }
        .menu-card .title { font-size: 1rem; font-weight: 700; }
        .menu-card .desc { font-size: 0.83rem; color: #666; }
        .menu-card.danger { border-left-color: #dc3545; }
        .menu-card.danger .title { color: #dc3545; }
        .menu-card.highlight { border-left-color: #fd7e14; }
        .badge-count { background: #dc3545; color: white; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; margin-left: 6px; vertical-align: middle; }
        .footer { background: #1a1a2e; color: #a0c4ff; padding: 24px 40px; font-size: 0.88rem; }
        .footer p { margin-top: 4px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Student Portal</div>
    <div>
        <a href="index.php">Home</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="hero">
    <div class="hero-inner">
        <?php if (!empty($sdata['photo']) && file_exists($sdata['photo'])): ?>
            <img src="<?php echo htmlspecialchars($sdata['photo']); ?>?t=<?php echo time(); ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,0.4);flex-shrink:0;" alt="avatar">
        <?php else: ?>
            <div class="hero-avatar">🎓</div>
        <?php endif; ?>
        <div>
            <h1>Welcome<?php echo $full_name ? ", $full_name" : ", " . htmlspecialchars($_SESSION["user_username"]); ?>!</h1>
            <p>Student Dashboard — your subjects, grades, attendance and assignments.</p>
        </div>
    </div>
</div>

<div class="main">

    <div class="stats">
        <div class="stat-card"><div class="num"><?php echo $subject_count; ?></div><div class="lbl">Subjects</div></div>
        <div class="stat-card"><div class="num"><?php echo $grade_count; ?></div><div class="lbl">Grade Records</div></div>
        <div class="stat-card"><div class="num"><?php echo $att_count; ?></div><div class="lbl">Attendance Records</div></div>
        <div class="stat-card"><div class="num"><?php echo $hw_count; ?></div><div class="lbl">Open Assignments</div></div>
    </div>

    <div class="menu-grid">
        <a href="subjects_student.php" class="menu-card">
            <div class="icon">📚</div>
            <div class="title">My Subjects</div>
            <div class="desc">View subjects you are enrolled in</div>
        </a>
        <a href="attendance_student.php" class="menu-card">
            <div class="icon"></div>
            <div class="title">My Attendance</div>
            <div class="desc">Check your attendance records</div>
        </a>
        <a href="grades_student.php" class="menu-card">
            <div class="icon">📝</div>
            <div class="title">My Grades</div>
            <div class="desc">View grades entered by your teachers</div>
        </a>
        <a href="homework_student.php" class="menu-card <?php echo ($hw_count > 0) ? 'highlight' : ''; ?>">
            <div class="icon">📎</div>
            <div class="title">
                Homework & Tasks
                <?php if ($hw_count > 0): ?>
                    <span class="badge-count"><?php echo $hw_count; ?></span>
                <?php endif; ?>
            </div>
            <div class="desc">View assignments posted by your teachers</div>
        </a>
        <a href="classmates_student.php" class="menu-card">
            <div class="icon">👥</div>
            <div class="title">My Classmates</div>
            <div class="desc">View coursemates and teachers per subject</div>
        </a>
        <a href="user_profile.php" class="menu-card">
            <div class="icon">👤</div>
            <div class="title">My Profile</div>
            <div class="desc">View and edit your profile</div>
        </a>
        <a href="user_logout.php" class="menu-card danger">
            <div class="icon">🚪</div>
            <div class="title">Logout</div>
            <div class="desc">Sign out of your account</div>
        </a>
    </div>

</div>

<div class="footer">
    <p>• Phone: +37000000001</p>
    <p>• Email: info@portal.com</p>
</div>
</body>
</html>