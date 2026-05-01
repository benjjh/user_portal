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

$stmt = $conn->prepare(
    "SELECT ta.attendance_date, ta.status
     FROM teacher_attendance ta
     WHERE ta.teacher_id = ?
     ORDER BY ta.attendance_date DESC"
);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

// Summary counts
$stmt2 = $conn->prepare(
    "SELECT status, COUNT(*) AS cnt
     FROM teacher_attendance
     WHERE teacher_id = ?
     GROUP BY status"
);
$stmt2->bind_param("i", $teacher_id);
$stmt2->execute();
$summary_res = $stmt2->get_result();
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
while ($s = $summary_res->fetch_assoc()) {
    $summary[$s['status']] = (int)$s['cnt'];
}
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Attendance</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f0; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: linear-gradient(135deg, #1a3a2a 0%, #2d5a3d 100%); color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a8d5b5; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }
        .container { max-width: 900px; margin: 36px auto; padding: 0 20px; flex: 1; }
        h1 { font-size: 1.7rem; color: #1a3a2a; margin-bottom: 6px; }
        .stats { display: flex; gap: 14px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat { background: white; border-radius: 10px; padding: 16px 24px; text-align: center; flex: 1; min-width: 110px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .stat .num { font-size: 1.8rem; font-weight: 800; }
        .stat .lbl { font-size: 0.82rem; color: #666; margin-top: 2px; }
        .num.present { color: #28a745; }
        .num.absent { color: #dc3545; }
        .num.late { color: #fd7e14; }
        .num.excused { color: #6c757d; }
        .card { background: white; border-radius: 10px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1a3a2a; color: white; padding: 12px 14px; text-align: left; font-size: 0.88rem; }
        td { padding: 12px 14px; border-bottom: 1px solid #eee; font-size: 0.92rem; }
        tr:hover td { background: #f6fbf7; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; text-transform: capitalize; }
        .badge-present { background: #d4edda; color: #155724; }
        .badge-absent { background: #f8d7da; color: #721c24; }
        .badge-late { background: #fff3cd; color: #856404; }
        .badge-excused { background: #e2e3e5; color: #383d41; }
        .empty { text-align: center; color: #888; padding: 28px; }
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
    <h1>My Attendance</h1>
    <p style="color:#666;margin-bottom:20px;">Your attendance is recorded by the admin.</p>

    <div class="stats">
        <div class="stat"><div class="num present"><?php echo $summary['present']; ?></div><div class="lbl">Present</div></div>
        <div class="stat"><div class="num absent"><?php echo $summary['absent']; ?></div><div class="lbl">Absent</div></div>
        <div class="stat"><div class="num late"><?php echo $summary['late']; ?></div><div class="lbl">Late</div></div>
        <div class="stat"><div class="num excused"><?php echo $summary['excused']; ?></div><div class="lbl">Excused</div></div>
    </div>

    <div class="card">
        <table>
            <tr>
                <th>Date</th>
                <th>Status</th>
            </tr>
            <?php
            $count = 0;
            while ($row = $result->fetch_assoc()):
                $count++;
                $s = $row['status'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['attendance_date']); ?></td>
                <td><span class="badge badge-<?php echo $s; ?>"><?php echo ucfirst($s); ?></span></td>
            </tr>
            <?php endwhile; ?>
            <?php if ($count === 0): ?>
            <tr><td colspan="2" class="empty">No attendance records found yet. The admin marks your attendance.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
</body>
</html>
<?php $stmt->close(); ?>