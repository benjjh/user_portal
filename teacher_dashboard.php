<?php
require_once 'config.php';

if (!isset($_SESSION["user_loggedin"]) || $_SESSION["user_loggedin"] !== true) {
    header("location: user_login.php");
    exit;
}
if (($_SESSION["role"] ?? '') !== 'teacher') {
    header("location: student_dashboard.php");
    exit;
}

$teacher_id = (int)$_SESSION["user_id"];

// Fetch teacher data
$stmt = $conn->prepare("SELECT name, surname, photo FROM users WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$tdata = $stmt->get_result()->fetch_assoc();
$stmt->close();

$full_name = trim(($tdata['name'] ?? '') . ' ' . ($tdata['surname'] ?? ''));

// Count teacher's subjects
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM subjects WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subject_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Count students assigned (enrolled in teacher's subjects)
$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT e.student_id) AS cnt
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     WHERE s.teacher_id = ?"
);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$student_count = $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f0; min-height: 100vh; display: flex; flex-direction: column; }

        .navbar {
            background: linear-gradient(135deg, #1a3a2a 0%, #2d5a3d 100%);
            color: white; padding: 15px 40px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; letter-spacing: 0.5px; }
        .navbar a { color: #a8d5b5; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }

        .hero {
            background: linear-gradient(135deg, #1a3a2a 0%, #2d5a3d 100%);
            color: white; padding: 36px 40px 60px;
        }
        .hero-inner { max-width: 1100px; margin: 0 auto; display: flex; align-items: center; gap: 24px; }
        .hero-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.4); background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 2rem; }
        .hero-avatar img { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.4); }
        .hero h1 { font-size: 1.7rem; font-weight: 700; }
        .hero p { color: #a8d5b5; margin-top: 4px; }

        .main { flex: 1; max-width: 1100px; margin: -32px auto 40px; padding: 0 20px; width: 100%; }

        /* Stats */
        .stats { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
        .stat-card { background: white; border-radius: 10px; padding: 20px 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); flex: 1; min-width: 140px; text-align: center; }
        .stat-card .num { font-size: 2rem; font-weight: 800; color: #1a3a2a; }
        .stat-card .lbl { font-size: 0.85rem; color: #666; margin-top: 4px; }

        /* Menu cards */
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .menu-card {
            background: white; border-radius: 12px; padding: 24px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            text-decoration: none; color: #1a3a2a;
            display: flex; flex-direction: column; align-items: flex-start; gap: 10px;
            border-left: 4px solid #2d5a3d;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .menu-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .menu-card .icon { font-size: 2rem; }
        .menu-card .title { font-size: 1rem; font-weight: 700; }
        .menu-card .desc { font-size: 0.83rem; color: #666; }
        .menu-card.danger { border-left-color: #dc3545; }
        .menu-card.danger .title { color: #dc3545; }

        .footer { background: #1a3a2a; color: #a8d5b5; padding: 24px 40px; font-size: 0.88rem; }
        .footer p { margin-top: 4px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Teacher Portal</div>
    <div>
        <a href="index.php">Home</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="hero">
    <div class="hero-inner">
        <?php if (!empty($tdata['photo']) && file_exists($tdata['photo'])): ?>
            <img src="<?php echo htmlspecialchars($tdata['photo']); ?>?t=<?php echo time(); ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,0.4);" alt="avatar">
        <?php else: ?>
            <div class="hero-avatar">🧑‍🏫</div>
        <?php endif; ?>
        <div>
            <h1>Welcome<?php echo $full_name ? ", $full_name" : ", " . htmlspecialchars($_SESSION["user_username"]); ?>!</h1>
            <p>Teacher Dashboard — manage your subjects, attendance, and grades.</p>
        </div>
    </div>
</div>

<div class="main">

    <div class="stats">
        <div class="stat-card">
            <div class="num"><?php echo $subject_count; ?></div>
            <div class="lbl">My Subjects</div>
        </div>
        <div class="stat-card">
            <div class="num"><?php echo $student_count; ?></div>
            <div class="lbl">My Students</div>
        </div>
    </div>

    <div class="menu-grid">
        <a href="subjects_teacher.php" class="menu-card">
            <div class="icon">📚</div>
            <div class="title">Manage Subjects</div>
            <div class="desc">View and add your subjects</div>
        </a>
        <a href="my_students_teacher.php" class="menu-card">
            <div class="icon">👥</div>
            <div class="title">My Students</div>
            <div class="desc">View students assigned to you</div>
        </a>
        <a href="attendance_teacher.php" class="menu-card">
            <div class="icon"></div>
            <div class="title">Mark Attendance</div>
            <div class="desc">Record student attendance by subject</div>
        </a>
        <a href="my_attendance_teacher.php" class="menu-card">
            <div class="icon">📅</div>
            <div class="title">My Attendance</div>
            <div class="desc">View your own attendance records</div>
        </a>
        <a href="grades_teacher.php" class="menu-card">
            <div class="icon">📝</div>
            <div class="title">Enter Grades</div>
            <div class="desc">Add grades for your students</div>
        </a>
        <a href="homework_teacher.php" class="menu-card">
            <div class="icon">📎</div>
            <div class="title">Upload Homework</div>
            <div class="desc">Post tasks and assignments</div>
        </a>
        <a href="user_profile.php" class="menu-card">
            <div class="icon">👤</div>
            <div class="title">My Profile</div>
            <div class="desc">View and edit your profile</div>
        </a>
        <a href="user_logout.php" class="menu-card danger">
            <div class="icon"></div>
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