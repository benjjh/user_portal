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
$message    = "";
$msg_type   = "success";

// Teacher's subjects
$stmt = $conn->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? ORDER BY subject_name ASC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects_res = $stmt->get_result();
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) $subjects[] = $row;
$stmt->close();

$subject_id      = (int)($_GET["subject_id"] ?? 0);
$attendance_date = $_GET["date"] ?? date("Y-m-d");

// ── SAVE ATTENDANCE ──
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject_id      = (int)($_POST["subject_id"] ?? 0);
    $attendance_date = $_POST["attendance_date"] ?? date("Y-m-d");

    // Verify subject belongs to teacher
    $stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $subject_id, $teacher_id);
    $stmt->execute();
    $ok = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ok) {
        $message  = "Invalid subject selection.";
        $msg_type = "error";
    } else {
        $attendance_data = $_POST["attendance"] ?? [];
        $saved = 0;
        foreach ($attendance_data as $student_id_str => $status) {
            $student_id = (int)$student_id_str;
            $stmt = $conn->prepare(
                "INSERT INTO attendance (student_id, subject_id, attendance_date, status, marked_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)"
            );
            $stmt->bind_param("iissi", $student_id, $subject_id, $attendance_date, $status, $teacher_id);
            $stmt->execute();
            $stmt->close();
            $saved++;
        }
        $message  = "Attendance saved for " . date("d M Y", strtotime($attendance_date)) . ". ($saved students recorded)";
        $msg_type = "success";
    }
}

// Load enrolled students for selected subject
$students = [];
if ($subject_id > 0) {
    $stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $subject_id, $teacher_id);
    $stmt->execute();
    $ok = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($ok) {
        $stmt = $conn->prepare(
            "SELECT u.id, u.username, u.name, u.surname, u.photo
             FROM enrollments e
             JOIN users u ON u.id = e.student_id
             WHERE e.subject_id = ?
             ORDER BY u.username ASC"
        );
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $students[] = $row;
        $stmt->close();
    }
}

// Load existing attendance for prefill
$existing = [];
if ($subject_id > 0) {
    $stmt = $conn->prepare("SELECT student_id, status FROM attendance WHERE subject_id = ? AND attendance_date = ?");
    $stmt->bind_param("is", $subject_id, $attendance_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $existing[(int)$row["student_id"]] = $row["status"];
    }
    $stmt->close();
}

// Attendance summary counts for selected date
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
foreach ($existing as $status) {
    if (isset($summary[$status])) $summary[$status]++;
}

function sel($a, $b) { return $a === $b ? "selected" : ""; }

// Get selected subject name for display
$selected_subject_name = "";
foreach ($subjects as $s) {
    if ((int)$s['id'] === $subject_id) {
        $selected_subject_name = $s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f0; min-height: 100vh; display: flex; flex-direction: column; }

        /* ── Navbar ── */
        .navbar {
            background: linear-gradient(135deg, #1a3a2a 0%, #2d5a3d 100%);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .navbar .logo { color: white; font-size: 1.1rem; font-weight: 700; letter-spacing: 0.5px; }
        .navbar .nav-links a { color: #a8d5b5; text-decoration: none; margin-left: 20px; font-weight: 600; font-size: 0.92rem; }
        .navbar .nav-links a:hover { color: white; }

        /* ── Container ── */
        .container { max-width: 1050px; margin: 36px auto; padding: 0 20px; flex: 1; width: 100%; }

        /* ── Page title ── */
        .page-title { margin-bottom: 26px; }
        .page-title h1 { font-size: 1.8rem; color: #1a3a2a; font-weight: 800; }
        .page-title p  { color: #666; margin-top: 5px; font-size: 0.95rem; }

        /* ── Messages ── */
        .msg { padding: 13px 18px; border-radius: 8px; margin-bottom: 22px; font-size: 0.93rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* ── Cards ── */
        .card { background: white; border-radius: 12px; padding: 26px; box-shadow: 0 2px 14px rgba(0,0,0,0.07); margin-bottom: 26px; }
        .card h2 { font-size: 1.05rem; color: #1a3a2a; font-weight: 700; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #f0f4f0; display: flex; align-items: center; gap: 8px; }

        /* ── Selector form ── */
        .selector-grid { display: grid; grid-template-columns: 2fr 1.5fr auto; gap: 16px; align-items: end; }
        label { display: block; font-weight: 600; color: #444; font-size: 0.87rem; margin-bottom: 6px; }
        select, input[type="date"] {
            width: 100%; padding: 11px 14px; border: 1.5px solid #ddd; border-radius: 8px;
            font-size: 0.93rem; background: #fafafa; font-family: inherit;
            transition: border-color 0.15s, background 0.15s;
        }
        select:focus, input[type="date"]:focus { outline: none; border-color: #2d5a3d; background: white; }

        .btn { padding: 11px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 700; transition: opacity 0.15s; text-decoration: none; display: inline-block; text-align: center; }
        .btn:hover { opacity: 0.87; }
        .btn-primary { background: #1a3a2a; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-load    { width: 100%; }

        /* ── Summary stats ── */
        .summary-bar { display: flex; gap: 14px; margin-bottom: 22px; flex-wrap: wrap; }
        .sum-card { flex: 1; min-width: 100px; background: white; border-radius: 10px; padding: 14px 18px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .sum-card .num { font-size: 1.8rem; font-weight: 800; }
        .sum-card .lbl { font-size: 0.78rem; color: #888; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.4px; }
        .sum-present .num { color: #28a745; }
        .sum-absent  .num { color: #dc3545; }
        .sum-late    .num { color: #fd7e14; }
        .sum-excused .num { color: #6c757d; }

        /* ── Students table ── */
        .students-table { width: 100%; border-collapse: collapse; }
        .students-table thead tr { background: #1a3a2a; }
        .students-table thead th { color: white; padding: 13px 16px; text-align: left; font-size: 0.87rem; font-weight: 700; }
        .students-table thead th:first-child { border-radius: 6px 0 0 0; }
        .students-table thead th:last-child  { border-radius: 0 6px 0 0; }
        .students-table tbody tr { border-bottom: 1px solid #eef2ee; transition: background 0.1s; }
        .students-table tbody tr:last-child { border-bottom: none; }
        .students-table tbody tr:hover { background: #f6fbf7; }
        .students-table tbody td { padding: 13px 16px; font-size: 0.92rem; color: #333; vertical-align: middle; }

        /* Student info cell */
        .student-info { display: flex; align-items: center; gap: 12px; }
        .stu-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; flex-shrink: 0; }
        .stu-avatar-placeholder { width: 38px; height: 38px; border-radius: 50%; background: #e8f5e9; display: flex; align-items: center; justify-content: center; font-size: 1rem; border: 2px solid #ddd; flex-shrink: 0; }
        .stu-name { font-weight: 700; color: #1a3a2a; font-size: 0.93rem; }
        .stu-user { font-size: 0.8rem; color: #888; margin-top: 2px; }

        /* Row number */
        .row-num { color: #ccc; font-size: 0.82rem; font-weight: 600; }

        /* Status select styling */
        .status-select {
            padding: 8px 12px; border: 1.5px solid #ddd; border-radius: 8px;
            font-size: 0.88rem; font-weight: 600; background: #fafafa;
            cursor: pointer; min-width: 130px; font-family: inherit;
            transition: border-color 0.15s;
        }
        .status-select:focus { outline: none; border-color: #2d5a3d; }
        /* Colour the select based on current value via JS */
        .status-present { border-color: #28a745; background: #f0fff4; color: #155724; }
        .status-absent  { border-color: #dc3545; background: #fff5f5; color: #721c24; }
        .status-late    { border-color: #fd7e14; background: #fff8f0; color: #854000; }
        .status-excused { border-color: #6c757d; background: #f8f8f8; color: #383d41; }

        /* Quick-set all buttons */
        .quick-set { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; align-items: center; }
        .quick-set span { font-size: 0.85rem; color: #666; font-weight: 600; }
        .btn-quick { padding: 6px 16px; border: none; border-radius: 20px; cursor: pointer; font-size: 0.82rem; font-weight: 700; transition: opacity 0.15s; }
        .btn-quick:hover { opacity: 0.85; }
        .bq-present { background: #d4edda; color: #155724; }
        .bq-absent  { background: #f8d7da; color: #721c24; }
        .bq-late    { background: #fff3cd; color: #856404; }
        .bq-excused { background: #e2e3e5; color: #383d41; }

        /* Save button row */
        .save-row { display: flex; justify-content: flex-end; margin-top: 20px; gap: 12px; }

        /* Date display badge */
        .date-badge { display: inline-flex; align-items: center; gap: 6px; background: #e8f5e9; color: #1a3a2a; padding: 5px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; margin-bottom: 16px; }

        /* Empty state */
        .empty { text-align: center; padding: 48px; color: #aaa; }
        .empty .icon { font-size: 3rem; margin-bottom: 12px; }

        /* No subject selected state */
        .select-prompt { text-align: center; padding: 48px 20px; color: #aaa; }
        .select-prompt .icon { font-size: 3rem; margin-bottom: 12px; }
        .select-prompt p { font-size: 1rem; }

        /* Footer */
        .footer { background: #1a3a2a; color: #a8d5b5; padding: 22px 40px; font-size: 0.88rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .footer p { margin-top: 3px; }
    </style>
</head>
<body>

<!-- Navbar: logo left, links right -->
<div class="navbar">
    <div class="logo">Teacher Portal</div>
    <div class="nav-links">
        <a href="teacher_dashboard.php">Dashboard</a>
        <a href="my_students_teacher.php">My Students</a>
        <a href="user_profile.php">My Profile</a>
        <a href="user_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>Mark Attendance</h1>
        <p>Select a subject and date, then set the attendance status for each enrolled student.</p>
    </div>

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $msg_type; ?>">
            <?php echo $msg_type === 'success' ? '' : ''; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Subject + Date selector -->
    <div class="card">
        <h2>📚 Select Subject &amp; Date</h2>
        <form method="get">
            <div class="selector-grid">
                <div>
                    <label>Subject</label>
                    <select name="subject_id" required>
                        <option value="">-- choose subject --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "")); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Attendance Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($attendance_date); ?>" required>
                </div>
                <div style="padding-bottom: 0;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-load">Load Students</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($subject_id <= 0): ?>
        <!-- No subject chosen yet -->
        <div class="card">
            <div class="select-prompt">
                <div class="icon">📋</div>
                <p>Choose a subject and date above, then click <strong>Load Students</strong> to begin marking attendance.</p>
            </div>
        </div>

    <?php elseif (count($students) === 0): ?>
        <!-- Subject chosen but no students enrolled -->
        <div class="card">
            <div class="empty">
                <div class="icon">👥</div>
                <p>No students are enrolled in this subject yet.</p>
                <p style="margin-top:8px;font-size:0.88rem;">Ask the admin to enroll students before marking attendance.</p>
            </div>
        </div>

    <?php else: ?>

        <!-- Summary stats (only if existing records for this date) -->
        <?php if (array_sum($summary) > 0): ?>
        <div class="summary-bar">
            <div class="sum-card sum-present">
                <div class="num"><?php echo $summary['present']; ?></div>
                <div class="lbl">Present</div>
            </div>
            <div class="sum-card sum-absent">
                <div class="num"><?php echo $summary['absent']; ?></div>
                <div class="lbl">Absent</div>
            </div>
            <div class="sum-card sum-late">
                <div class="num"><?php echo $summary['late']; ?></div>
                <div class="lbl">Late</div>
            </div>
            <div class="sum-card sum-excused">
                <div class="num"><?php echo $summary['excused']; ?></div>
                <div class="lbl">Excused</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attendance form -->
        <div class="card">
            <h2>
                👥 <?php echo htmlspecialchars($selected_subject_name); ?>
                &nbsp;—&nbsp;
                <span style="font-weight:400;color:#666;font-size:0.95rem;">
                    <?php echo date("d M Y", strtotime($attendance_date)); ?>
                </span>
            </h2>

            <!-- Quick-set all buttons -->
            <div class="quick-set">
                <span>Mark all as:</span>
                <button type="button" class="btn-quick bq-present" onclick="setAll('present')"> All Present</button>
                <button type="button" class="btn-quick bq-absent"  onclick="setAll('absent')"> All Absent</button>
                <button type="button" class="btn-quick bq-late"    onclick="setAll('late')"> All Late</button>
                <button type="button" class="btn-quick bq-excused" onclick="setAll('excused')"> All Excused</button>
            </div>

            <form method="post" id="attendanceForm">
                <input type="hidden" name="subject_id"      value="<?php echo (int)$subject_id; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>">

                <table class="students-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Student</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $st):
                            $sid     = (int)$st["id"];
                            $full    = trim(($st['name'] ?? '') . ' ' . ($st['surname'] ?? ''));
                            $current = $existing[$sid] ?? "present";
                            $photo   = $st['photo'] ?? '';
                            $photo_abs = __DIR__ . '/' . $photo;
                        ?>
                        <tr>
                            <td class="row-num"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="student-info">
                                    <?php if (!empty($photo) && file_exists($photo_abs)): ?>
                                        <img src="<?php echo htmlspecialchars($photo); ?>?t=<?php echo time(); ?>" class="stu-avatar" alt="">
                                    <?php else: ?>
                                        <div class="stu-avatar-placeholder">👤</div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="stu-name"><?php echo htmlspecialchars($full ?: $st['username']); ?></div>
                                        <div class="stu-user">@<?php echo htmlspecialchars($st['username']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <select name="attendance[<?php echo $sid; ?>]"
                                        class="status-select status-<?php echo $current; ?>"
                                        onchange="colorSelect(this)">
                                    <option value="present" <?php echo sel($current, "present"); ?>> Present</option>
                                    <option value="absent"  <?php echo sel($current, "absent");  ?>> Absent</option>
                                    <option value="late"    <?php echo sel($current, "late");    ?>> Late</option>
                                    <option value="excused" <?php echo sel($current, "excused"); ?>> Excused</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="save-row">
                    <a href="attendance_teacher.php?subject_id=<?php echo $subject_id; ?>&date=<?php echo $attendance_date; ?>"
                       class="btn" style="background:#e9ecef;color:#333;">Reset</a>
                    <button type="submit" class="btn btn-success">💾 Save Attendance</button>
                </div>
            </form>
        </div>

    <?php endif; ?>

</div>

<!-- Footer: matching teacher portal style -->
<div class="footer">
    <div>
        <p>• Phone: +37000000001</p>
        <p>• Email: info@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#4a7a5a;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

<script>
// Colour the dropdown based on selected value
function colorSelect(sel) {
    sel.className = 'status-select status-' + sel.value;
}

// Set all students to the same status at once
function setAll(status) {
    var selects = document.querySelectorAll('.status-select');
    selects.forEach(function(s) {
        s.value = status;
        colorSelect(s);
    });
}

// Apply colours on page load (for prefilled values)
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.status-select').forEach(function(s) {
        colorSelect(s);
    });
});
</script>

</body>
</html>