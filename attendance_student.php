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
$subject_id = (int)($_GET["subject_id"] ?? 0);

// Subjects student is enrolled in
$stmt = $conn->prepare(
    "SELECT s.id, s.subject_name, s.subject_code
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     WHERE e.student_id = ?
     ORDER BY s.subject_name ASC"
);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subjects_res = $stmt->get_result();
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) $subjects[] = $row;
$stmt->close();

// Load attendance with marked_by info
// marked_by references users table (teacher), NULL means admin
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT a.attendance_date, a.status,
                s.subject_name, s.subject_code,
                u.username AS marker_username,
                u.name AS marker_name,
                u.surname AS marker_surname,
                u.role AS marker_role
         FROM attendance a
         JOIN subjects s ON s.id = a.subject_id
         LEFT JOIN users u ON u.id = a.marked_by
         WHERE a.student_id = ? AND a.subject_id = ?
         ORDER BY a.attendance_date DESC"
    );
    $stmt->bind_param("ii", $student_id, $subject_id);
} else {
    $stmt = $conn->prepare(
        "SELECT a.attendance_date, a.status,
                s.subject_name, s.subject_code,
                u.username AS marker_username,
                u.name AS marker_name,
                u.surname AS marker_surname,
                u.role AS marker_role
         FROM attendance a
         JOIN subjects s ON s.id = a.subject_id
         LEFT JOIN users u ON u.id = a.marked_by
         WHERE a.student_id = ?
         ORDER BY a.attendance_date DESC"
    );
    $stmt->bind_param("i", $student_id);
}
$stmt->execute();
$att_res = $stmt->get_result();
$records = [];
while ($row = $att_res->fetch_assoc()) $records[] = $row;
$stmt->close();

// Summary counts
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
foreach ($records as $r) {
    if (isset($summary[$r['status']])) $summary[$r['status']]++;
}
$total = count($records);
$attendance_rate = $total > 0 ? round(($summary['present'] / $total) * 100) : 0;

// Marker label helper
function getMarkerLabel($row) {
    if (empty($row['marker_username'])) {
        return ['label' => '🛡️ Admin', 'class' => 'marker-admin'];
    }
    $full = trim(($row['marker_name'] ?? '') . ' ' . ($row['marker_surname'] ?? ''));
    $name = $full ?: $row['marker_username'];
    if (($row['marker_role'] ?? '') === 'teacher') {
        return ['label' => '🧑‍🏫 ' . $name, 'class' => 'marker-teacher'];
    }
    return ['label' => '🛡️ Admin', 'class' => 'marker-admin'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance</title>
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

        /* Stats bar */
        .stats-bar { display: flex; gap: 14px; margin-bottom: 26px; flex-wrap: wrap; }
        .stat { background: white; border-radius: 10px; padding: 18px 20px; flex: 1; min-width: 110px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .stat .num { font-size: 1.9rem; font-weight: 800; }
        .stat .lbl { font-size: 0.78rem; color: #777; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.4px; }
        .stat-total   { border-left: 4px solid #1a1a2e; }
        .stat-total   .num { color: #1a1a2e; }
        .stat-present { border-left: 4px solid #28a745; }
        .stat-present .num { color: #28a745; }
        .stat-absent  { border-left: 4px solid #dc3545; }
        .stat-absent  .num { color: #dc3545; }
        .stat-late    { border-left: 4px solid #fd7e14; }
        .stat-late    .num { color: #fd7e14; }
        .stat-excused { border-left: 4px solid #6c757d; }
        .stat-excused .num { color: #6c757d; }
        .stat-rate    { border-left: 4px solid #0d6efd; }
        .stat-rate    .num { color: #0d6efd; }

        /* Attendance rate bar */
        .rate-card { background: white; border-radius: 12px; padding: 20px 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 24px; }
        .rate-card .rate-label { display: flex; justify-content: space-between; font-size: 0.88rem; font-weight: 700; color: #444; margin-bottom: 8px; }
        .rate-bar-wrap { height: 12px; background: #eee; border-radius: 6px; overflow: hidden; }
        .rate-bar { height: 100%; border-radius: 6px; transition: width 0.6s ease; }
        .rate-high   { background: linear-gradient(90deg,#28a745,#20c997); }
        .rate-medium { background: linear-gradient(90deg,#fd7e14,#ffc107); }
        .rate-low    { background: linear-gradient(90deg,#dc3545,#e74c3c); }

        /* Filter card */
        .card { background: white; border-radius: 12px; padding: 26px; box-shadow: 0 2px 14px rgba(0,0,0,0.07); margin-bottom: 26px; }
        .card h2 { font-size: 1.05rem; color: #1a1a2e; font-weight: 700; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #f0f2f8; }

        label { display: block; font-weight: 600; color: #444; font-size: 0.87rem; margin-bottom: 6px; }
        select { width: 100%; max-width: 420px; padding: 10px 14px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 0.93rem; background: #fafafa; font-family: inherit; }
        select:focus { outline: none; border-color: #1a1a2e; background: white; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #1a1a2e; }
        thead th { color: white; padding: 13px 15px; text-align: left; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.3px; }
        thead th:first-child { border-radius: 6px 0 0 0; }
        thead th:last-child  { border-radius: 0 6px 0 0; }
        tbody tr { border-bottom: 1px solid #eef0f8; transition: background 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f5f7ff; }
        tbody td { padding: 13px 15px; font-size: 0.9rem; color: #333; vertical-align: middle; }

        /* Date cell */
        .date-cell { font-weight: 700; color: #1a1a2e; }
        .day-name  { font-size: 0.78rem; color: #aaa; font-weight: 400; margin-top: 2px; }

        /* Subject badge */
        .sub-badge { display: inline-block; background: #e8eef8; color: #1a1a2e; padding: 3px 11px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }

        /* Status badges */
        .status-badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: 800; text-transform: capitalize; }
        .status-present { background: #d4edda; color: #155724; }
        .status-absent  { background: #f8d7da; color: #721c24; }
        .status-late    { background: #fff3cd; color: #856404; }
        .status-excused { background: #e2e3e5; color: #383d41; }

        /* Marker badges */
        .marker-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }
        .marker-teacher { background: #e8f5e9; color: #1a3a2a; }
        .marker-admin   { background: #f8e8e8; color: #7b1f1f; }

        /* Empty state */
        .empty { text-align: center; padding: 48px; color: #bbb; }
        .empty .icon { font-size: 3rem; margin-bottom: 12px; }

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
        <h1>My Attendance</h1>
        <p>View your attendance records across all subjects.</p>
    </div>

    <!-- Summary stats -->
    <div class="stats-bar">
        <div class="stat stat-total">
            <div class="num"><?php echo $total; ?></div>
            <div class="lbl">Total Records</div>
        </div>
        <div class="stat stat-present">
            <div class="num"><?php echo $summary['present']; ?></div>
            <div class="lbl">Present</div>
        </div>
        <div class="stat stat-absent">
            <div class="num"><?php echo $summary['absent']; ?></div>
            <div class="lbl">Absent</div>
        </div>
        <div class="stat stat-late">
            <div class="num"><?php echo $summary['late']; ?></div>
            <div class="lbl">Late</div>
        </div>
        <div class="stat stat-excused">
            <div class="num"><?php echo $summary['excused']; ?></div>
            <div class="lbl">Excused</div>
        </div>
        <div class="stat stat-rate">
            <div class="num"><?php echo $attendance_rate; ?>%</div>
            <div class="lbl">Attendance Rate</div>
        </div>
    </div>

    <!-- Attendance rate bar -->
    <?php if ($total > 0):
        $bar_class = $attendance_rate >= 75 ? 'rate-high' : ($attendance_rate >= 50 ? 'rate-medium' : 'rate-low');
    ?>
    <div class="rate-card">
        <div class="rate-label">
            <span>Attendance Rate</span>
            <span><?php echo $attendance_rate; ?>%</span>
        </div>
        <div class="rate-bar-wrap">
            <div class="rate-bar <?php echo $bar_class; ?>" style="width:<?php echo $attendance_rate; ?>%"></div>
        </div>
        <p style="font-size:0.8rem;color:#aaa;margin-top:8px;">
            <?php
            if ($attendance_rate >= 75)     echo "Great attendance — keep it up!";
            elseif ($attendance_rate >= 50) echo "Average attendance — try to improve.";
            else                            echo "Low attendance — please speak with your teacher.";
            ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Filter -->
    <div class="card">
        <h2>🔍 Filter by Subject</h2>
        <form method="get">
            <label>Select Subject</label>
            <select name="subject_id" onchange="this.form.submit()">
                <option value="0">All Subjects</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "")); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Records table -->
    <div class="card">
        <h2>📋 Attendance Records</h2>
        <?php if (count($records) === 0): ?>
            <div class="empty">
                <div class="icon">📭</div>
                <p>No attendance records found<?php echo $subject_id > 0 ? ' for this subject' : ''; ?>.</p>
                <p style="margin-top:8px;font-size:0.88rem;">Records will appear here once your teacher marks attendance.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Marked By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row):
                    $marker = getMarkerLabel($row);
                    $sub_label = $row['subject_name'] . (!empty($row['subject_code']) ? " ({$row['subject_code']})" : "");
                    $day_name  = date("l", strtotime($row['attendance_date']));
                ?>
                <tr>
                    <td>
                        <div class="date-cell"><?php echo htmlspecialchars($row['attendance_date']); ?></div>
                        <div class="day-name"><?php echo $day_name; ?></div>
                    </td>
                    <td><span class="sub-badge"><?php echo htmlspecialchars($sub_label); ?></span></td>
                    <td>
                        <span class="status-badge status-<?php echo $row['status']; ?>">
                            <?php
                            $icons = ['present'=>'','absent'=>'','late'=>'','excused'=>''];
                            echo ($icons[$row['status']] ?? '') . ' ' . ucfirst($row['status']);
                            ?>
                        </span>
                    </td>
                    <td>
                        <span class="marker-badge <?php echo $marker['class']; ?>">
                            <?php echo htmlspecialchars($marker['label']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

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