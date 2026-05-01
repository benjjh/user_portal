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
$message = "";

// Teacher's subjects
$stmt = $conn->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$subjects_res = $stmt->get_result();
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) $subjects[] = $row;
$stmt->close();

$subject_id = (int)($_GET["subject_id"] ?? 0);
$attendance_date = $_GET["date"] ?? date("Y-m-d");

// Save attendance
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $attendance_date = $_POST["attendance_date"] ?? date("Y-m-d");

    // verify subject belongs to teacher
    $stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $subject_id, $teacher_id);
    $stmt->execute();
    $ok = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ok) {
        $message = "Invalid subject selection.";
    } else {
        // attendance_data[student_id] = status
        $attendance_data = $_POST["attendance"] ?? [];

        foreach ($attendance_data as $student_id_str => $status) {
            $student_id = (int)$student_id_str;

            // Upsert (insert or update) based on unique constraint
            $stmt = $conn->prepare(
                "INSERT INTO attendance (student_id, subject_id, attendance_date, status, marked_by)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)"
            );
            $stmt->bind_param("iissi", $student_id, $subject_id, $attendance_date, $status, $teacher_id);
            $stmt->execute();
            $stmt->close();
        }

        $message = "Attendance saved for " . $attendance_date;
    }
}

// Load enrolled students for selected subject
$students = [];
if ($subject_id > 0) {
    // verify subject belongs to teacher
    $stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $subject_id, $teacher_id);
    $stmt->execute();
    $ok = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($ok) {
        $stmt = $conn->prepare(
            "SELECT u.id, u.username, u.name, u.surname
             FROM enrollments e
             JOIN users u ON u.id = e.student_id
             WHERE e.subject_id = ?
             ORDER BY u.id DESC"
        );
        $stmt->bind_param("i", $subject_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $students[] = $row;
        $stmt->close();
    }
}

// Load existing attendance for that date + subject to prefill
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

function selected($a, $b) { return $a === $b ? "selected" : ""; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance</title>
    <style>
        body { font-family: Arial; padding: 30px; }
        .box { max-width: 950px; margin: 0 auto; }
        select, input { padding: 10px; margin: 8px 0 16px; width: 100%; }
        button { padding: 10px 18px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f4f4f4; }
        .msg { color: green; margin: 10px 0; }
        .nav a { margin-right: 12px; }
        .rowflex { display: flex; gap: 12px; }
        .rowflex > div { flex: 1; }
    </style>
</head>
<body>
<div class="box">
    <div class="nav">
        <a href="teacher_dashboard.php">Teacher Dashboard</a>
        <a href="index.php">Home</a>
    </div>

    <h1>Mark Attendance</h1>
    <p class="msg"><?php echo htmlspecialchars($message); ?></p>

    <form method="get" class="rowflex">
        <div>
            <label>Select Subject</label>
            <select name="subject_id" required>
                <option value="">-- choose subject --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? "selected" : ""; ?>>
                        <?php
                            $label = $s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "");
                            echo htmlspecialchars($label);
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($attendance_date); ?>" required>
        </div>
        <div style="align-self: end;">
            <button type="submit">Load Students</button>
        </div>
    </form>

    <?php if ($subject_id > 0): ?>
        <form method="post">
            <input type="hidden" name="subject_id" value="<?php echo (int)$subject_id; ?>">
            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>">

            <h2>Enrolled Students</h2>
            <?php if (count($students) === 0): ?>
                <p>No students enrolled in this subject yet. Enroll students first.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Student</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($students as $st): ?>
                        <?php
                            $sid = (int)$st["id"];
                            $full = trim(($st['name'] ?? '') . ' ' . ($st['surname'] ?? ''));
                            $label = $st["username"] . ($full ? " ($full)" : "");
                            $current = $existing[$sid] ?? "present";
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($label); ?></td>
                            <td>
                                <select name="attendance[<?php echo $sid; ?>]">
                                    <option value="present" <?php echo selected($current, "present"); ?>>Present</option>
                                    <option value="absent" <?php echo selected($current, "absent"); ?>>Absent</option>
                                    <option value="late" <?php echo selected($current, "late"); ?>>Late</option>
                                    <option value="excused" <?php echo selected($current, "excused"); ?>>Excused</option>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <button type="submit">Save Attendance</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
</body>
</html>