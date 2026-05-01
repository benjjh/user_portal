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

// Load students enrolled in this teacher's subjects
$stmt = $conn->prepare(
    "SELECT DISTINCT u.id, u.username, u.name, u.surname, u.photo,
            GROUP_CONCAT(s.subject_name ORDER BY s.subject_name SEPARATOR ', ') AS subjects
     FROM enrollments e
     JOIN users u ON u.id = e.student_id
     JOIN subjects s ON s.id = e.subject_id
     WHERE s.teacher_id = ?
     GROUP BY u.id
     ORDER BY u.username ASC"
);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Students</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f0; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: linear-gradient(135deg, #1a3a2a 0%, #2d5a3d 100%); color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a8d5b5; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }
        .container { max-width: 1000px; margin: 36px auto; padding: 0 20px; flex: 1; }
        h1 { font-size: 1.7rem; color: #1a3a2a; margin-bottom: 6px; }
        .card { background: white; border-radius: 10px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1a3a2a; color: white; padding: 12px 14px; text-align: left; font-size: 0.88rem; }
        td { padding: 12px 14px; border-bottom: 1px solid #eee; font-size: 0.92rem; vertical-align: middle; }
        tr:hover td { background: #f6fbf7; }
        .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
        .no-photo { width: 40px; height: 40px; border-radius: 50%; background: #dde3f0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; border: 2px solid #ddd; }
        .note { color: #888; font-size: 0.9rem; margin-top: 16px; }
    </style>
</head>
<body>
<div class="navbar">
    <div class="logo">Teacher Portal</div>
    <div>
        <a href="teacher_dashboard.php">Dashboard</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h1>My Students</h1>
    <p style="color:#666;margin-bottom:24px;">Students enrolled in your subjects. Enrollment is managed by the admin.</p>

    <div class="card">
        <table>
            <tr>
                <th>Photo</th>
                <th>Username</th>
                <th>Full Name</th>
                <th>Enrolled In</th>
            </tr>
            <?php
            $count = 0;
            while ($row = $result->fetch_assoc()):
                $count++;
                $full = trim(($row['name'] ?? '') . ' ' . ($row['surname'] ?? ''));
                $photo = $row['photo'] ?? '';
            ?>
            <tr>
                <td>
                    <?php if (!empty($photo) && file_exists($photo)): ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>?t=<?php echo time(); ?>" class="user-photo" alt="">
                    <?php else: ?>
                        <span class="no-photo">👤</span>
                    <?php endif; ?>
                </td>
                <td><strong><?php echo htmlspecialchars($row['username']); ?></strong></td>
                <td><?php echo htmlspecialchars($full ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($row['subjects']); ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count === 0): ?>
            <tr><td colspan="4" style="text-align:center;color:#888;padding:28px;">No students are assigned to your subjects yet. Contact the admin to enroll students.</td></tr>
            <?php endif; ?>
        </table>
        <?php if ($count > 0): ?>
        <p class="note">Showing <?php echo $count; ?> student(s). To add or remove students, contact the admin.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php $stmt->close(); ?>