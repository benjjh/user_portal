<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message = "";

// Load all subjects
$subjects = [];
$sql = "SELECT s.id, s.subject_name, s.subject_code, u.username AS teacher_username
        FROM subjects s
        JOIN users u ON u.id = s.teacher_id
        ORDER BY s.subject_name ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) $subjects[] = $row;

// Read subject_id and date — from POST after save, or GET when loading
$subject_id     = 0;
$attendance_date = date("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Save attendance
    $subject_id      = (int)$_POST["subject_id"];
    $attendance_date = $_POST["attendance_date"];
    $attendance_data = $_POST["attendance"] ?? [];

    foreach ($attendance_data as $sid => $status) {
        $sid = (int)$sid;
        // marked_by = NULL for admin (FK now nullable)
        $stmt = $conn->prepare(
            "INSERT INTO attendance (student_id, subject_id, attendance_date, status, marked_by)
             VALUES (?, ?, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = NULL"
        );
        $stmt->bind_param("iiss", $sid, $subject_id, $attendance_date, $status);
        $stmt->execute();
        $stmt->close();
    }
    $message = "Attendance recorded successfully for " . htmlspecialchars($attendance_date) . ".";

} else {
    $subject_id      = (int)($_GET["subject_id"] ?? 0);
    $attendance_date = $_GET["date"] ?? date("Y-m-d");
}

// Load enrolled students for selected subject
$students = [];
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT u.id, u.username, u.name, u.surname
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

function sel($a, $b) { return $a === $b ? "selected" : ""; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Attendance | Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; min-height: 100vh; }
        .navbar { background: #1a1a2e; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #a0c4ff; text-decoration: none; margin-left: 16px; font-weight: 600; }
        .navbar a:hover { color: white; }
        .container { max-width: 950px; margin: 36px auto; padding: 0 20px; }
        h1 { font-size: 1.8rem; color: #1a1a2e; margin-bottom: 6px; }
        .msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; }
        .card { background: white; border-radius: 10px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 28px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; font-size: 0.9rem; }
        select, input[type="date"] {
            width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px;
            font-size: 0.95rem; margin-bottom: 18px; background: #fafafa;
        }
        select:focus, input:focus { outline: none; border-color: #4a90d9; background: white; }
        .btn { padding: 11px 24px; background: #1a1a2e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
        .btn:hover { background: #2d2d5e; }
        .btn-save { background: #28a745; }
        .btn-save:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.92rem; }
        th { background: #f4f6fb; color: #444; font-weight: 700; }
        tr:hover td { background: #f9fafb; }
        td select { margin-bottom: 0; }
        .form-row { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
        .form-row > div { flex: 1; min-width: 180px; }
        .no-students { color: #dc3545; background: #fff3cd; padding: 14px; border-radius: 6px; border: 1px solid #ffc107; }
        .tab-links { display: flex; gap: 12px; margin-bottom: 24px; }
        .tab-link { padding: 10px 22px; border-radius: 6px; text-decoration: none; font-weight: 600; background: #e9ecef; color: #333; }
        .tab-link.active { background: #1a1a2e; color: white; }
    </style>
</head>
<body>
<div class="navbar">
    <strong>Admin Panel — Record Attendance</strong>
    <div>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h1>Record Attendance</h1>
    <p style="color:#666;margin-bottom:20px;">Mark student attendance by subject and date.</p>

    <div class="tab-links">
        <a href="attendance_admin.php" class="tab-link active">Student Attendance</a>
        <a href="teacher_attendance_admin.php" class="tab-link">Teacher Attendance</a>
    </div>

    <?php if ($message): ?>
        <div class="msg"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Subject + date selector -->
    <div class="card">
        <form method="get">
            <div class="form-row">
                <div>
                    <label>Select Subject</label>
                    <select name="subject_id" required>
                        <option value="">-- choose subject --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "") . " — " . $s['teacher_username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($attendance_date); ?>" required>
                </div>
                <div style="padding-bottom:18px;">
                    <button type="submit" class="btn">Load Students</button>
                </div>
            </div>
        </form>
    </div>

    <?php if ($subject_id > 0): ?>
        <?php if (count($students) === 0): ?>
            <div class="no-students">No students are enrolled in this subject yet. Please enroll students via <a href="enroll_students.php">Enroll Students</a>.</div>
        <?php else: ?>
        <div class="card">
            <h2 style="margin-bottom:16px;font-size:1.2rem;">Students — <?php echo htmlspecialchars($attendance_date); ?></h2>
            <form method="post">
                <input type="hidden" name="subject_id" value="<?php echo (int)$subject_id; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>">
                <table>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($students as $st):
                        $sid     = (int)$st["id"];
                        $full    = trim(($st['name'] ?? '') . ' ' . ($st['surname'] ?? ''));
                        $label   = $st["username"] . ($full ? " ($full)" : "");
                        $current = $existing[$sid] ?? "present";
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td>
                            <select name="attendance[<?php echo $sid; ?>]">
                                <option value="present" <?php echo sel($current, "present"); ?>>Present</option>
                                <option value="absent"  <?php echo sel($current, "absent");  ?>>Absent</option>
                                <option value="late"    <?php echo sel($current, "late");    ?>>Late</option>
                                <option value="excused" <?php echo sel($current, "excused"); ?>>Excused</option>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <br>
                <button type="submit" class="btn btn-save">Save Attendance</button>
            </form>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>