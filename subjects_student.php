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

// Load only subjects this student is enrolled in, with teacher and classmate count
$stmt = $conn->prepare(
    "SELECT s.id, s.subject_name, s.subject_code, s.created_at,
            u.username AS teacher_username,
            u.name AS teacher_name,
            u.surname AS teacher_surname,
            u.photo AS teacher_photo,
            (SELECT COUNT(*) FROM enrollments e2 WHERE e2.subject_id = s.id) AS student_count
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     JOIN users u ON u.id = s.teacher_id
     WHERE e.student_id = ?
     ORDER BY s.subject_name ASC"
);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$subjects = [];
while ($row = $result->fetch_assoc()) $subjects[] = $row;
$stmt->close();

// For each subject get pending homework count
$hw_counts = [];
foreach ($subjects as $s) {
    $sid = (int)$s['id'];
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM homework
         WHERE subject_id = ? AND (due_date IS NULL OR due_date >= CURDATE())"
    );
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $hw_counts[$sid] = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

// Subject accent colours — cycle through a set
$colours = [
    ['bg' => '#1a1a2e', 'light' => '#e8eef8'],
    ['bg' => '#1a3a2a', 'light' => '#e8f5e9'],
    ['bg' => '#4a0f0f', 'light' => '#fce8e8'],
    ['bg' => '#0d3b6e', 'light' => '#e3f0ff'],
    ['bg' => '#4a2f0a', 'light' => '#fff3e0'],
    ['bg' => '#2d0a4a', 'light' => '#f3e8ff'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Subjects</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f8; min-height: 100vh; display: flex; flex-direction: column; }

        /* Navbar */
        .navbar { background: linear-gradient(135deg,#1a1a2e,#16213e); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { color: white; font-size: 1.1rem; font-weight: 700; }
        .navbar .nav-links a { color: #a0c4ff; text-decoration: none; margin-left: 20px; font-weight: 600; font-size: 0.92rem; }
        .navbar .nav-links a:hover { color: white; }

        .container { max-width: 1050px; margin: 36px auto; padding: 0 20px; flex: 1; width: 100%; }

        .page-title { margin-bottom: 26px; }
        .page-title h1 { font-size: 1.8rem; color: #1a1a2e; font-weight: 800; }
        .page-title p  { color: #666; margin-top: 5px; font-size: 0.95rem; }

        /* Stats */
        .stats-bar { display: flex; gap: 14px; margin-bottom: 26px; flex-wrap: wrap; }
        .stat { background: white; border-radius: 10px; padding: 18px 22px; flex: 1; min-width: 120px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.06); border-left: 4px solid #1a1a2e; }
        .stat .num { font-size: 1.9rem; font-weight: 800; color: #1a1a2e; }
        .stat .lbl { font-size: 0.78rem; color: #777; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px; }

        /* Subject cards grid */
        .subjects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }

        /* Individual subject card */
        .subject-card { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 14px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
        .subject-card:hover { transform: translateY(-4px); box-shadow: 0 8px 28px rgba(0,0,0,0.12); }

        /* Card top band */
        .card-band { padding: 22px 22px 18px; color: white; position: relative; }
        .card-band .sub-name { font-size: 1.15rem; font-weight: 800; letter-spacing: 0.3px; }
        .card-band .sub-code { display: inline-block; background: rgba(255,255,255,0.2); padding: 2px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; margin-top: 6px; }
        .card-band .sub-icon { position: absolute; right: 20px; top: 18px; font-size: 2.2rem; opacity: 0.25; }

        /* Card body */
        .card-body { padding: 18px 22px 20px; }

        /* Teacher row */
        .teacher-row { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid #f0f2f8; }
        .t-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; flex-shrink: 0; }
        .t-placeholder { width: 38px; height: 38px; border-radius: 50%; background: #f0f2f8; display: flex; align-items: center; justify-content: center; font-size: 1rem; border: 2px solid #ddd; flex-shrink: 0; }
        .t-label { font-size: 0.75rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 700; }
        .t-name  { font-size: 0.9rem; font-weight: 700; color: #1a1a2e; margin-top: 1px; }
        .t-user  { font-size: 0.78rem; color: #aaa; }

        /* Info chips */
        .info-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
        .chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
        .chip-students { background: #e8eef8; color: #1a1a2e; }
        .chip-hw       { background: #fff3cd; color: #856404; }
        .chip-hw-none  { background: #f0f2f5; color: #aaa; }

        /* Action buttons */
        .card-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .act-btn { flex: 1; text-align: center; padding: 9px 12px; border-radius: 8px; text-decoration: none; font-size: 0.82rem; font-weight: 700; transition: opacity 0.15s; }
        .act-btn:hover { opacity: 0.85; }
        .btn-grades     { background: #e8eef8; color: #1a1a2e; }
        .btn-attendance { background: #e8f5e9; color: #1a3a2a; }
        .btn-homework   { background: #fff3cd; color: #856404; }

        /* Empty state */
        .empty-state { background: white; border-radius: 14px; padding: 56px; text-align: center; box-shadow: 0 2px 14px rgba(0,0,0,0.07); color: #bbb; }
        .empty-state .icon { font-size: 3.5rem; margin-bottom: 14px; }
        .empty-state p { font-size: 1rem; }

        .footer { background: #1a1a2e; color: #a0c4ff; padding: 22px 40px; font-size: 0.88rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .footer p { margin-top: 3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Student Portal</div>
    <div class="nav-links">
        <a href="student_dashboard.php">Dashboard</a>
        <a href="classmates_student.php">My Classmates</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>My Subjects</h1>
        <p>All subjects you are currently enrolled in.</p>
    </div>

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat">
            <div class="num"><?php echo count($subjects); ?></div>
            <div class="lbl">Enrolled Subjects</div>
        </div>
        <div class="stat">
            <div class="num"><?php echo array_sum($hw_counts); ?></div>
            <div class="lbl">Open Assignments</div>
        </div>
    </div>

    <?php if (count($subjects) === 0): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <p>You are not enrolled in any subjects yet.</p>
            <p style="margin-top:8px;font-size:0.88rem;">Contact the admin to get enrolled in subjects.</p>
        </div>
    <?php else: ?>

    <div class="subjects-grid">
        <?php foreach ($subjects as $i => $s):
            $colour  = $colours[$i % count($colours)];
            $sid     = (int)$s['id'];
            $t_full  = trim(($s['teacher_name'] ?? '') . ' ' . ($s['teacher_surname'] ?? ''));
            $t_label = $t_full ?: $s['teacher_username'];
            $t_photo = $s['teacher_photo'] ?? '';
            $t_abs   = __DIR__ . '/' . $t_photo;
            $hw_cnt  = $hw_counts[$sid] ?? 0;
        ?>
        <div class="subject-card">

            <!-- Coloured top band -->
            <div class="card-band" style="background:<?php echo $colour['bg']; ?>;">
                <div class="sub-icon">📚</div>
                <div class="sub-name"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                <?php if (!empty($s['subject_code'])): ?>
                    <span class="sub-code"><?php echo htmlspecialchars($s['subject_code']); ?></span>
                <?php endif; ?>
            </div>

            <!-- Card body -->
            <div class="card-body">

                <!-- Teacher info -->
                <div class="teacher-row">
                    <?php if (!empty($t_photo) && file_exists($t_abs)): ?>
                        <img src="<?php echo htmlspecialchars($t_photo); ?>?t=<?php echo time(); ?>" class="t-avatar" alt="">
                    <?php else: ?>
                        <div class="t-placeholder">🧑‍🏫</div>
                    <?php endif; ?>
                    <div>
                        <div class="t-label">Teacher</div>
                        <div class="t-name"><?php echo htmlspecialchars($t_label); ?></div>
                        <div class="t-user">@<?php echo htmlspecialchars($s['teacher_username']); ?></div>
                    </div>
                </div>

                <!-- Info chips -->
                <div class="info-chips">
                    <span class="chip chip-students">
                        👥 <?php echo (int)$s['student_count']; ?> student<?php echo $s['student_count'] != 1 ? 's' : ''; ?>
                    </span>
                    <?php if ($hw_cnt > 0): ?>
                        <span class="chip chip-hw">📎 <?php echo $hw_cnt; ?> assignment<?php echo $hw_cnt != 1 ? 's' : ''; ?></span>
                    <?php else: ?>
                        <span class="chip chip-hw-none">📎 No open tasks</span>
                    <?php endif; ?>
                </div>

                <!-- Quick action buttons -->
                <div class="card-actions">
                    <a href="grades_student.php?subject_id=<?php echo $sid; ?>" class="act-btn btn-grades">📝 My Grades</a>
                    <a href="attendance_student.php?subject_id=<?php echo $sid; ?>" class="act-btn btn-attendance"> Attendance</a>
                    <a href="homework_student.php?subject_id=<?php echo $sid; ?>" class="act-btn btn-homework">📎 Homework</a>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>

<div class="footer">
    <div>
        <p>• Phone: +37000000001</p>
        <p>• Email: info@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#3a3a6e;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

</body>
</html>