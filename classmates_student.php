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

// Load subjects this student is enrolled in
$stmt = $conn->prepare(
    "SELECT s.id, s.subject_name, s.subject_code,
            u.id AS teacher_id, u.username AS teacher_username,
            u.name AS teacher_name, u.surname AS teacher_surname,
            u.photo AS teacher_photo
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     JOIN users u ON u.id = s.teacher_id
     WHERE e.student_id = ?
     ORDER BY s.subject_name ASC"
);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subjects_res = $stmt->get_result();
$my_subjects = [];
while ($row = $subjects_res->fetch_assoc()) $my_subjects[] = $row;
$stmt->close();

// For each subject, load all enrolled students (classmates)
$classmates_by_subject = [];
foreach ($my_subjects as $subj) {
    $sid = (int)$subj['id'];
    $stmt = $conn->prepare(
        "SELECT u.id, u.username, u.name, u.surname, u.photo
         FROM enrollments e
         JOIN users u ON u.id = e.student_id
         WHERE e.subject_id = ?
         ORDER BY u.username ASC"
    );
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    $res = $stmt->get_result();
    $classmates = [];
    while ($row = $res->fetch_assoc()) $classmates[] = $row;
    $stmt->close();
    $classmates_by_subject[$sid] = $classmates;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classmates</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f8; min-height: 100vh; display: flex; flex-direction: column; }

        .navbar { background: linear-gradient(135deg,#1a1a2e,#16213e); padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { color: white; font-size: 1.1rem; font-weight: 700; }
        .navbar .nav-links a { color: #a0c4ff; text-decoration: none; margin-left: 20px; font-weight: 600; font-size: 0.92rem; }
        .navbar .nav-links a:hover { color: white; }

        .container { max-width: 1050px; margin: 36px auto; padding: 0 20px; flex: 1; width: 100%; }

        .page-title { margin-bottom: 26px; }
        .page-title h1 { font-size: 1.8rem; color: #1a1a2e; font-weight: 800; }
        .page-title p  { color: #666; margin-top: 5px; font-size: 0.95rem; }

        /* Subject section */
        .subject-section { margin-bottom: 32px; }
        .subject-header {
            display: flex; align-items: center; justify-content: space-between;
            background: linear-gradient(135deg,#1a1a2e,#2d2d6e);
            color: white; padding: 16px 24px; border-radius: 12px 12px 0 0;
            flex-wrap: wrap; gap: 10px;
        }
        .subject-header .sub-name { font-size: 1.1rem; font-weight: 800; }
        .subject-header .sub-code { background: rgba(255,255,255,0.15); padding: 3px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }
        .subject-header .student-count { font-size: 0.85rem; color: #a0c4ff; }

        /* Teacher card inside subject */
        .teacher-strip {
            background: #fff8e8; border: 1px solid #ffe082; border-top: none;
            padding: 14px 24px; display: flex; align-items: center; gap: 14px;
        }
        .teacher-strip .t-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #856404; letter-spacing: 0.5px; }
        .teacher-strip .t-info { display: flex; align-items: center; gap: 10px; }
        .t-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ffe082; flex-shrink: 0; }
        .t-avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: #fff3cd; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 2px solid #ffe082; flex-shrink: 0; }
        .t-name { font-weight: 700; color: #1a1a2e; font-size: 0.93rem; }
        .t-username { font-size: 0.8rem; color: #888; }

        /* Classmates grid */
        .classmates-grid {
            background: white; border: 1px solid #eee; border-top: none;
            border-radius: 0 0 12px 12px; padding: 20px 24px;
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
        }

        /* Student card */
        .student-card {
            background: #f7f9ff; border-radius: 10px; padding: 16px 14px;
            text-align: center; border: 1px solid #e8eef8;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .student-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,0.09); }
        .student-card.is-me { background: #e8f0ff; border-color: #1a1a2e; }
        .s-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #dde3f0; margin: 0 auto 10px; display: block; }
        .s-avatar-placeholder { width: 56px; height: 56px; border-radius: 50%; background: #e8eef8; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 2px solid #dde3f0; margin: 0 auto 10px; }
        .s-name { font-weight: 700; color: #1a1a2e; font-size: 0.9rem; }
        .s-username { font-size: 0.78rem; color: #888; margin-top: 3px; }
        .you-badge { display: inline-block; background: #1a1a2e; color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; margin-top: 6px; }

        /* Empty */
        .empty-section { background: white; border: 1px solid #eee; border-top: none; border-radius: 0 0 12px 12px; padding: 32px; text-align: center; color: #bbb; font-size: 0.9rem; }

        /* No subjects */
        .no-subjects { background: white; border-radius: 12px; padding: 48px; text-align: center; box-shadow: 0 2px 12px rgba(0,0,0,0.07); color: #bbb; }
        .no-subjects .icon { font-size: 3rem; margin-bottom: 12px; }

        .footer { background: #1a1a2e; color: #a0c4ff; padding: 22px 40px; font-size: 0.88rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .footer p { margin-top: 3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Student Portal</div>
    <div class="nav-links">
        <a href="student_dashboard.php">Dashboard</a>
        <a href="grades_student.php">My Grades</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>My Classmates</h1>
        <p>See who is enrolled in your subjects and who teaches each one.</p>
    </div>

    <?php if (count($my_subjects) === 0): ?>
        <div class="no-subjects">
            <div class="icon">📭</div>
            <p>You are not enrolled in any subjects yet.</p>
            <p style="margin-top:8px;font-size:0.88rem;">Contact the admin to get enrolled.</p>
        </div>
    <?php else: ?>

        <?php foreach ($my_subjects as $subj):
            $sid        = (int)$subj['id'];
            $classmates = $classmates_by_subject[$sid] ?? [];
            $sub_label  = $subj['subject_name'] . (!empty($subj['subject_code']) ? " ({$subj['subject_code']})" : "");
            $t_full     = trim(($subj['teacher_name'] ?? '') . ' ' . ($subj['teacher_surname'] ?? ''));
            $t_label    = $t_full ?: $subj['teacher_username'];
            $t_photo    = $subj['teacher_photo'] ?? '';
            $t_photo_abs = __DIR__ . '/' . $t_photo;
            $count      = count($classmates);
        ?>
        <div class="subject-section">

            <!-- Subject header -->
            <div class="subject-header">
                <div>
                    <div class="sub-name">📚 <?php echo htmlspecialchars($subj['subject_name']); ?></div>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <?php if (!empty($subj['subject_code'])): ?>
                        <span class="sub-code"><?php echo htmlspecialchars($subj['subject_code']); ?></span>
                    <?php endif; ?>
                    <span class="student-count">👥 <?php echo $count; ?> student<?php echo $count !== 1 ? 's' : ''; ?></span>
                </div>
            </div>

            <!-- Teacher strip -->
            <div class="teacher-strip">
                <span class="t-label">🧑‍🏫 Teacher</span>
                <div class="t-info">
                    <?php if (!empty($t_photo) && file_exists($t_photo_abs)): ?>
                        <img src="<?php echo htmlspecialchars($t_photo); ?>?t=<?php echo time(); ?>" class="t-avatar" alt="">
                    <?php else: ?>
                        <div class="t-avatar-placeholder">🧑‍🏫</div>
                    <?php endif; ?>
                    <div>
                        <div class="t-name"><?php echo htmlspecialchars($t_label); ?></div>
                        <div class="t-username">@<?php echo htmlspecialchars($subj['teacher_username']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Classmates grid -->
            <?php if ($count === 0): ?>
                <div class="empty-section">No other students enrolled yet.</div>
            <?php else: ?>
            <div class="classmates-grid">
                <?php foreach ($classmates as $cm):
                    $is_me    = ((int)$cm['id'] === $student_id);
                    $cm_full  = trim(($cm['name'] ?? '') . ' ' . ($cm['surname'] ?? ''));
                    $cm_label = $cm_full ?: $cm['username'];
                    $cm_photo = $cm['photo'] ?? '';
                    $cm_photo_abs = __DIR__ . '/' . $cm_photo;
                ?>
                <div class="student-card <?php echo $is_me ? 'is-me' : ''; ?>">
                    <?php if (!empty($cm_photo) && file_exists($cm_photo_abs)): ?>
                        <img src="<?php echo htmlspecialchars($cm_photo); ?>?t=<?php echo time(); ?>" class="s-avatar" alt="">
                    <?php else: ?>
                        <div class="s-avatar-placeholder">👤</div>
                    <?php endif; ?>
                    <div class="s-name"><?php echo htmlspecialchars($cm_label); ?></div>
                    <div class="s-username">@<?php echo htmlspecialchars($cm['username']); ?></div>
                    <?php if ($is_me): ?>
                        <div class="you-badge">You</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

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