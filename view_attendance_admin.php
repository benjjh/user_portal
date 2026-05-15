<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$subject_id = (int)($_GET["subject_id"] ?? 0);
$filter_status = $_GET["status"] ?? "";

// Load subjects
$subjects = [];
$sql = "SELECT s.id, s.subject_name, s.subject_code, u.username AS teacher_username
        FROM subjects s
        JOIN users u ON u.id = s.teacher_id
        ORDER BY s.subject_name ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) $subjects[] = $row;

// Build attendance query with filters
$where = ["1=1"];
$params = [];
$types  = "";

if ($subject_id > 0) {
    $where[] = "a.subject_id = ?";
    $params[] = $subject_id;
    $types   .= "i";
}
if ($filter_status !== "" && in_array($filter_status, ['present','absent','late','excused'])) {
    $where[] = "a.status = ?";
    $params[] = $filter_status;
    $types   .= "s";
}

$where_sql = implode(" AND ", $where);

$sql = "SELECT a.id, a.attendance_date, a.status,
               st.username AS student_username, st.name AS student_name, st.surname AS student_surname,
               s.subject_name, s.subject_code,
               u.username AS marker_username, u.name AS marker_name,
               u.surname AS marker_surname, u.role AS marker_role
        FROM attendance a
        JOIN users st ON st.id = a.student_id
        JOIN subjects s ON s.id = a.subject_id
        LEFT JOIN users u ON u.id = a.marked_by
        WHERE $where_sql
        ORDER BY a.attendance_date DESC, st.username ASC";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $attendance_res = $stmt->get_result();
} else {
    $attendance_res = $conn->query($sql);
}

$records = [];
while ($row = $attendance_res->fetch_assoc()) $records[] = $row;

// Overall summary counts
$summary_sql = "SELECT status, COUNT(*) AS cnt FROM attendance GROUP BY status";
$summary_res = $conn->query($summary_sql);
$summary = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
$total_all = 0;
while ($s = $summary_res->fetch_assoc()) {
    $summary[$s['status']] = (int)$s['cnt'];
    $total_all += (int)$s['cnt'];
}

function markerLabel($row) {
    if (empty($row['marker_username'])) return ['label'=>'🛡️ Admin','class'=>'marker-admin'];
    $full = trim(($row['marker_name']??'').' '.($row['marker_surname']??''));
    $name = $full ?: $row['marker_username'];
    if (($row['marker_role']??'') === 'teacher') return ['label'=>'🧑‍🏫 '.$name,'class'=>'marker-teacher'];
    return ['label'=>'🛡️ Admin','class'=>'marker-admin'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Attendance | Admin</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; min-height:100vh; display:flex; flex-direction:column; }
        .navbar { background:#1a1a2e; padding:15px 40px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { color:white; font-size:1.1rem; font-weight:700; }
        .navbar .nav-links a { color:#a0c4ff; text-decoration:none; margin-left:20px; font-weight:600; font-size:0.92rem; }
        .navbar .nav-links a:hover { color:white; }
        .container { max-width:1100px; margin:36px auto; padding:0 20px; flex:1; width:100%; }
        .page-title { margin-bottom:26px; }
        .page-title h1 { font-size:1.8rem; color:#1a1a2e; font-weight:800; }
        .page-title p  { color:#666; margin-top:5px; font-size:0.95rem; }

        /* Stats */
        .stats-bar { display:flex; gap:14px; margin-bottom:26px; flex-wrap:wrap; }
        .stat { background:white; border-radius:10px; padding:16px 20px; flex:1; min-width:110px; text-align:center; box-shadow:0 2px 10px rgba(0,0,0,0.06); }
        .stat .num { font-size:1.8rem; font-weight:800; }
        .stat .lbl { font-size:0.78rem; color:#777; margin-top:3px; text-transform:uppercase; letter-spacing:0.4px; }
        .stat-total   { border-left:4px solid #1a1a2e; } .stat-total .num   { color:#1a1a2e; }
        .stat-present { border-left:4px solid #28a745; } .stat-present .num { color:#28a745; }
        .stat-absent  { border-left:4px solid #dc3545; } .stat-absent .num  { color:#dc3545; }
        .stat-late    { border-left:4px solid #fd7e14; } .stat-late .num    { color:#fd7e14; }
        .stat-excused { border-left:4px solid #6c757d; } .stat-excused .num { color:#6c757d; }

        /* Cards */
        .card { background:white; border-radius:12px; padding:26px; box-shadow:0 2px 14px rgba(0,0,0,0.07); margin-bottom:26px; }
        .card h2 { font-size:1.05rem; color:#1a1a2e; font-weight:700; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid #f0f2f5; }

        /* Filter form */
        .filter-grid { display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; }
        .filter-grid > div { flex:1; min-width:160px; }
        label { display:block; font-weight:600; color:#444; font-size:0.87rem; margin-bottom:6px; }
        select { width:100%; padding:10px 13px; border:1.5px solid #ddd; border-radius:8px; font-size:0.92rem; background:#fafafa; font-family:inherit; }
        select:focus { outline:none; border-color:#1a1a2e; background:white; }
        .btn { padding:10px 22px; border:none; border-radius:8px; cursor:pointer; font-size:0.88rem; font-weight:700; transition:opacity 0.15s; }
        .btn:hover { opacity:0.85; }
        .btn-primary { background:#1a1a2e; color:white; }
        .btn-reset   { background:#e9ecef; color:#333; text-decoration:none; display:inline-block; }

        /* Table */
        table { width:100%; border-collapse:collapse; }
        thead tr { background:#1a1a2e; }
        thead th { color:white; padding:12px 14px; text-align:left; font-size:0.84rem; font-weight:700; }
        thead th:first-child { border-radius:6px 0 0 0; }
        thead th:last-child  { border-radius:0 6px 0 0; }
        tbody tr { border-bottom:1px solid #eee; transition:background 0.1s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:#f7f9ff; }
        tbody td { padding:12px 14px; font-size:0.88rem; color:#333; vertical-align:middle; }

        .date-cell { font-weight:700; color:#1a1a2e; font-size:0.9rem; }
        .day-name  { font-size:0.75rem; color:#aaa; }
        .student-name { font-weight:700; color:#1a1a2e; }
        .student-user { font-size:0.78rem; color:#aaa; }
        .sub-badge { display:inline-block; background:#e8eef8; color:#1a1a2e; padding:3px 10px; border-radius:20px; font-size:0.8rem; font-weight:700; }

        .status-badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:800; }
        .status-present { background:#d4edda; color:#155724; }
        .status-absent  { background:#f8d7da; color:#721c24; }
        .status-late    { background:#fff3cd; color:#856404; }
        .status-excused { background:#e2e3e5; color:#383d41; }

        .marker-badge { display:inline-flex; align-items:center; gap:4px; padding:4px 11px; border-radius:20px; font-size:0.8rem; font-weight:700; }
        .marker-teacher { background:#e8f5e9; color:#1a3a2a; }
        .marker-admin   { background:#f8e8e8; color:#7b1f1f; }

        .empty { text-align:center; padding:48px; color:#bbb; }
        .empty .icon { font-size:3rem; margin-bottom:12px; }
        .result-count { font-size:0.85rem; color:#888; margin-bottom:14px; }

        .footer { background:#1a1a2e; color:#a0c4ff; padding:22px 40px; font-size:0.88rem; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; }
        .footer p { margin-top:3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Admin Panel</div>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>View Attendance Records</h1>
        <p>Browse, filter, and review all student attendance across subjects.</p>
    </div>

    <!-- Overall summary stats -->
    <div class="stats-bar">
        <div class="stat stat-total">
            <div class="num"><?php echo $total_all; ?></div>
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
    </div>

    <!-- Filters -->
    <div class="card">
        <h2>🔍 Filter Records</h2>
        <form method="get">
            <div class="filter-grid">
                <div>
                    <label>Subject</label>
                    <select name="subject_id">
                        <option value="0">All Subjects</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "") . " — " . $s['teacher_username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="present"  <?php echo $filter_status==='present'  ? 'selected':''; ?>> Present</option>
                        <option value="absent"   <?php echo $filter_status==='absent'   ? 'selected':''; ?>> Absent</option>
                        <option value="late"     <?php echo $filter_status==='late'     ? 'selected':''; ?>> Late</option>
                        <option value="excused"  <?php echo $filter_status==='excused'  ? 'selected':''; ?>> Excused</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;padding-bottom:0;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="view_attendance_admin.php" class="btn btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Records table -->
    <div class="card">
        <h2>📋 Attendance Records</h2>
        <p class="result-count">Showing <strong><?php echo count($records); ?></strong> record<?php echo count($records) !== 1 ? 's' : ''; ?></p>

        <?php if (count($records) === 0): ?>
            <div class="empty">
                <div class="icon">📭</div>
                <p>No attendance records found for the selected filters.</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Marked By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $row):
                    $marker    = markerLabel($row);
                    $sub_label = $row['subject_name'] . (!empty($row['subject_code']) ? " ({$row['subject_code']})" : "");
                    $st_full   = trim(($row['student_name']??'').' '.($row['student_surname']??''));
                    $day_name  = date("l", strtotime($row['attendance_date']));
                    $icons     = ['present'=>'','absent'=>'','late'=>'','excused'=>''];
                ?>
                <tr>
                    <td>
                        <div class="date-cell"><?php echo htmlspecialchars($row['attendance_date']); ?></div>
                        <div class="day-name"><?php echo $day_name; ?></div>
                    </td>
                    <td>
                        <div class="student-name"><?php echo htmlspecialchars($st_full ?: $row['student_username']); ?></div>
                        <div class="student-user">@<?php echo htmlspecialchars($row['student_username']); ?></div>
                    </td>
                    <td><span class="sub-badge"><?php echo htmlspecialchars($sub_label); ?></span></td>
                    <td>
                        <span class="status-badge status-<?php echo $row['status']; ?>">
                            <?php echo ($icons[$row['status']]??'').' '.ucfirst($row['status']); ?>
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
        <p>• Phone: +37000000002</p>
        <p>• Email: admin@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#555;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

</body>
</html>