<?php
require_once 'config.php';

// Only admin can access this page
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message = "";

// Load all subjects with teacher info
$subjects = [];
$sql = "SELECT s.id, s.subject_name, s.subject_code, u.username AS teacher_username
        FROM subjects s
        JOIN users u ON u.id = s.teacher_id
        ORDER BY s.subject_name ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) $subjects[] = $row;

// Load all students
$students = [];
$res = $conn->query("SELECT id, username, name, surname FROM users WHERE role = 'student' ORDER BY username ASC");
while ($row = $res->fetch_assoc()) $students[] = $row;

// Save enrollment
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id  = (int)($_POST["student_id"] ?? 0);
    $subject_ids = $_POST["subject_ids"] ?? [];

    if ($student_id <= 0 || empty($subject_ids)) {
        $message = "Please select a student and at least one subject.";
    } else {
        $added   = 0;
        $skipped = 0;
        foreach ($subject_ids as $sid_raw) {
            $sid  = (int)$sid_raw;
            $stmt = $conn->prepare("INSERT IGNORE INTO enrollments (student_id, subject_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $student_id, $sid);
            if ($stmt->execute() && $conn->affected_rows > 0) {
                $added++;
            } else {
                $skipped++;
            }
            $stmt->close();
        }
        $message = "Enrollment completed. Added: $added" . ($skipped > 0 ? ", Already enrolled: $skipped" : "") . ".";
    }
}

// Recent enrollments
$sql_enr = "SELECT e.id, st.username AS student_username, s.subject_name, s.subject_code, t.username AS teacher_username
            FROM enrollments e
            JOIN users st ON st.id = e.student_id
            JOIN subjects s ON s.id = e.subject_id
            JOIN users t ON t.id = s.teacher_id
            ORDER BY e.id DESC
            LIMIT 50";
$enrollments = $conn->query($sql_enr);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll Students | Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; min-height: 100vh; }
        .navbar { background: #1a1a2e; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #a0c4ff; text-decoration: none; margin-left: 16px; font-weight: 600; }
        .navbar a:hover { color: white; }
        .container { max-width: 950px; margin: 36px auto; padding: 0 20px; }
        h1 { font-size: 1.8rem; color: #1a1a2e; margin-bottom: 6px; }
        h2 { font-size: 1.2rem; color: #1a1a2e; margin-bottom: 16px; }
        .msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; }
        .card { background: white; border-radius: 10px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); margin-bottom: 28px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; color: #333; font-size: 0.9rem; }
        select { width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; margin-bottom: 18px; background: #fafafa; }
        select:focus { outline: none; border-color: #4a90d9; }
        small { display: block; color: #888; font-size: 0.82rem; margin-top: -14px; margin-bottom: 18px; }
        .btn { padding: 11px 24px; background: #1a1a2e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
        .btn:hover { background: #2d2d5e; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 14px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.92rem; }
        th { background: #f4f6fb; color: #444; font-weight: 700; }
        tr:hover td { background: #f9fafb; }
    </style>
</head>
<body>
<div class="navbar">
    <strong>Admin Panel — Enroll Students</strong>
    <div>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h1>Enroll Students</h1>
    <p style="color:#666;margin-bottom:24px;">Assign students to one or more subjects.</p>

    <?php if ($message): ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <label>Select Student</label>
            <select name="student_id" required>
                <option value="">-- choose student --</option>
                <?php foreach ($students as $student): ?>
                    <option value="<?php echo (int)$student['id']; ?>">
                        <?php
                        $full = trim(($student['name'] ?? '') . ' ' . ($student['surname'] ?? ''));
                        echo htmlspecialchars($student['username'] . ($full ? " ($full)" : ""));
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Select Subject(s)</label>
            <select name="subject_ids[]" multiple size="8" required>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?php echo (int)$subject['id']; ?>">
                        <?php
                        $label = $subject['subject_name'];
                        if (!empty($subject['subject_code'])) $label .= " ({$subject['subject_code']})";
                        $label .= " — " . $subject['teacher_username'];
                        echo htmlspecialchars($label);
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Hold Ctrl (Windows) or Command (Mac) to select multiple subjects.</small>

            <button type="submit" class="btn">Enroll Student</button>
        </form>
    </div>

    <div class="card">
        <h2>Recent Enrollments</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Subject</th>
                <th>Teacher</th>
            </tr>
            <?php while ($row = $enrollments->fetch_assoc()): ?>
            <tr>
                <td><?php echo (int)$row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['student_username']); ?></td>
                <td><?php echo htmlspecialchars($row['subject_name'] . (!empty($row['subject_code']) ? " ({$row['subject_code']})" : "")); ?></td>
                <td><?php echo htmlspecialchars($row['teacher_username']); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>