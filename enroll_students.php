<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message  = "";
$msg_type = "success";

// Load all subjects
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

// ── REMOVE ENROLLMENT ──
if (isset($_GET["remove_id"])) {
    $remove_id = (int)$_GET["remove_id"];
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE id = ?");
    $stmt->bind_param("i", $remove_id);
    if ($stmt->execute()) {
        $message  = "Enrollment removed successfully.";
        $msg_type = "success";
    } else {
        $message  = "Error removing enrollment: " . $stmt->error;
        $msg_type = "error";
    }
    $stmt->close();
}

// ── CHANGE ENROLLMENT (remove from old subject, add to new) ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "change_enrollment") {
    $enrollment_id  = (int)$_POST["enrollment_id"];
    $new_subject_id = (int)$_POST["new_subject_id"];
    $student_id     = (int)$_POST["student_id"];

    if ($new_subject_id <= 0) {
        $message  = "Please select a new subject.";
        $msg_type = "error";
    } else {
        // Check if student is already enrolled in the new subject
        $stmt = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND subject_id = ?");
        $stmt->bind_param("ii", $student_id, $new_subject_id);
        $stmt->execute();
        $already = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($already) {
            $message  = "This student is already enrolled in the selected subject.";
            $msg_type = "error";
        } else {
            // Update the enrollment to the new subject
            $stmt = $conn->prepare("UPDATE enrollments SET subject_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_subject_id, $enrollment_id);
            if ($stmt->execute()) {
                $message  = "Enrollment changed successfully.";
                $msg_type = "success";
            } else {
                $message  = "Error changing enrollment: " . $stmt->error;
                $msg_type = "error";
            }
            $stmt->close();
        }
    }
}

// ── ADD ENROLLMENT ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "enroll") {
    $student_id  = (int)($_POST["student_id"] ?? 0);
    $subject_ids = $_POST["subject_ids"] ?? [];

    if ($student_id <= 0 || empty($subject_ids)) {
        $message  = "Please select a student and at least one subject.";
        $msg_type = "error";
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
        $message  = "Enrollment completed. Added: $added" . ($skipped > 0 ? ", Already enrolled (skipped): $skipped" : "") . ".";
        $msg_type = "success";
    }
}

// Load all enrollments with full details
$sql_enr = "SELECT e.id, e.enrolled_at,
                   st.id AS student_id, st.username AS student_username, st.name AS student_name, st.surname AS student_surname,
                   s.id AS subject_id, s.subject_name, s.subject_code,
                   t.username AS teacher_username
            FROM enrollments e
            JOIN users st ON st.id = e.student_id
            JOIN subjects s ON s.id = e.subject_id
            JOIN users t ON t.id = s.teacher_id
            ORDER BY st.username ASC, s.subject_name ASC";
$enrollments_res = $conn->query($sql_enr);
$enrollments = [];
while ($row = $enrollments_res->fetch_assoc()) $enrollments[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enroll Students | Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; flex-direction: column; }
        .navbar { background: #1a1a2e; color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a0c4ff; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }
        .container { max-width: 1050px; margin: 40px auto; padding: 0 20px; flex: 1; width: 100%; }
        .page-title { margin-bottom: 28px; }
        .page-title h1 { font-size: 1.8rem; color: #1a1a2e; }
        .page-title p  { color: #666; margin-top: 6px; font-size: 0.95rem; }
        .msg { padding: 12px 18px; border-radius: 8px; margin-bottom: 22px; font-size: 0.93rem; font-weight: 600; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .card { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 14px rgba(0,0,0,0.07); margin-bottom: 28px; }
        .card h2 { font-size: 1.1rem; color: #1a1a2e; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #f0f2f5; }
        label { display: block; font-weight: 600; color: #444; font-size: 0.88rem; margin-bottom: 6px; }
        select, input[type="text"] { width: 100%; padding: 11px 14px; border: 1px solid #ccc; border-radius: 7px; font-size: 0.95rem; margin-bottom: 18px; background: #fafafa; font-family: inherit; }
        select:focus, input:focus { outline: none; border-color: #1a1a2e; background: white; }
        small { display: block; color: #888; font-size: 0.82rem; margin-top: -14px; margin-bottom: 18px; }
        .btn { padding: 10px 22px; border: none; border-radius: 7px; cursor: pointer; font-size: 0.88rem; font-weight: 700; transition: opacity 0.15s; text-decoration: none; display: inline-block; }
        .btn:hover { opacity: 0.85; }
        .btn-primary   { background: #1a1a2e; color: white; }
        .btn-danger    { background: #dc3545; color: white; }
        .btn-warning   { background: #fd7e14; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 5px 12px; font-size: 0.8rem; }
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #1a1a2e; }
        thead th { color: white; padding: 12px 14px; text-align: left; font-size: 0.87rem; font-weight: 700; }
        thead th:first-child { border-radius: 6px 0 0 0; }
        thead th:last-child  { border-radius: 0 6px 0 0; }
        tbody tr { border-bottom: 1px solid #eee; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f7f9ff; }
        tbody td { padding: 12px 14px; font-size: 0.9rem; color: #333; vertical-align: middle; }
        .student-name { font-weight: 700; color: #1a1a2e; }
        .subject-badge { display: inline-block; background: #e8eef8; color: #1a1a2e; padding: 3px 10px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }
        .teacher-badge { display: inline-block; background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }
        .actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
        .empty { text-align: center; padding: 40px; color: #bbb; }

        /* Change enrollment modal-style inline form */
        .change-form { display: none; background: #f7f9ff; border: 1px solid #dde3f0; border-radius: 8px; padding: 14px; margin-top: 8px; }
        .change-form.open { display: block; }
        .change-form select { margin-bottom: 10px; }
        .change-form .btn-row { display: flex; gap: 8px; }

        .footer { background: #1a1a2e; color: #a0c4ff; padding: 22px 40px; font-size: 0.88rem; }
        .footer p { margin-top: 4px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Admin Panel</div>
    <div>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>Enroll Students</h1>
        <p>Assign students to subjects, change enrollments, or remove them if there was an error.</p>
    </div>

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Enroll form -->
    <div class="card">
        <h2>➕ Enroll a Student</h2>
        <form method="post">
            <input type="hidden" name="action" value="enroll">
            <label>Select Student</label>
            <select name="student_id" required>
                <option value="">-- choose student --</option>
                <?php foreach ($students as $student):
                    $full = trim(($student['name'] ?? '') . ' ' . ($student['surname'] ?? ''));
                ?>
                    <option value="<?php echo (int)$student['id']; ?>">
                        <?php echo htmlspecialchars($student['username'] . ($full ? " ($full)" : "")); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Select Subject(s)</label>
            <select name="subject_ids[]" multiple size="6" required>
                <?php foreach ($subjects as $subject):
                    $label = $subject['subject_name'];
                    if (!empty($subject['subject_code'])) $label .= " ({$subject['subject_code']})";
                    $label .= " — " . $subject['teacher_username'];
                ?>
                    <option value="<?php echo (int)$subject['id']; ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Hold Ctrl (Windows) or Command (Mac) to select multiple subjects.</small>

            <button type="submit" class="btn btn-primary">Enroll Student</button>
        </form>
    </div>

    <!-- Current enrollments table -->
    <div class="card">
        <h2>📋 Current Enrollments</h2>
        <p style="color:#666;font-size:0.88rem;margin-bottom:16px;">
            To correct an enrollment error — click <strong>Change Subject</strong> to move a student to a different subject, or click <strong>Remove</strong> to unenroll them completely.
        </p>

        <?php if (count($enrollments) === 0): ?>
            <div class="empty">No enrollments found yet.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Current Subject</th>
                    <th>Teacher</th>
                    <th>Enrolled On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrollments as $enr):
                    $full = trim(($enr['student_name'] ?? '') . ' ' . ($enr['student_surname'] ?? ''));
                    $student_label = $enr['student_username'] . ($full ? " ($full)" : "");
                    $subject_label = $enr['subject_name'] . (!empty($enr['subject_code']) ? " ({$enr['subject_code']})" : "");
                ?>
                <tr>
                    <td style="color:#bbb;font-size:0.83rem;"><?php echo (int)$enr['id']; ?></td>
                    <td><span class="student-name"><?php echo htmlspecialchars($student_label); ?></span></td>
                    <td><span class="subject-badge"><?php echo htmlspecialchars($subject_label); ?></span></td>
                    <td><span class="teacher-badge">🧑‍🏫 <?php echo htmlspecialchars($enr['teacher_username']); ?></span></td>
                    <td style="color:#999;font-size:0.85rem;"><?php echo htmlspecialchars(substr($enr['enrolled_at'], 0, 10)); ?></td>
                    <td>
                        <div class="actions">
                            <!-- Change subject button -->
                            <button type="button" class="btn btn-warning btn-sm"
                                    onclick="toggleChange(<?php echo (int)$enr['id']; ?>)">
                                🔄 Change Subject
                            </button>
                            <!-- Remove enrollment -->
                            <a href="enroll_students.php?remove_id=<?php echo (int)$enr['id']; ?>"
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Remove <?php echo htmlspecialchars(addslashes($student_label)); ?> from <?php echo htmlspecialchars(addslashes($subject_label)); ?>?\n\nThis will unenroll the student from this subject.')">
                               🗑️ Remove
                            </a>
                        </div>

                        <!-- Inline change form -->
                        <div class="change-form" id="change-<?php echo (int)$enr['id']; ?>">
                            <form method="post">
                                <input type="hidden" name="action" value="change_enrollment">
                                <input type="hidden" name="enrollment_id" value="<?php echo (int)$enr['id']; ?>">
                                <input type="hidden" name="student_id" value="<?php echo (int)$enr['student_id']; ?>">
                                <label>Move to a different subject:</label>
                                <select name="new_subject_id" required>
                                    <option value="">-- choose new subject --</option>
                                    <?php foreach ($subjects as $s):
                                        if ($s['id'] == $enr['subject_id']) continue; // skip current
                                        $slabel = $s['subject_name'];
                                        if (!empty($s['subject_code'])) $slabel .= " ({$s['subject_code']})";
                                        $slabel .= " — " . $s['teacher_username'];
                                    ?>
                                        <option value="<?php echo (int)$s['id']; ?>">
                                            <?php echo htmlspecialchars($slabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="btn-row">
                                    <button type="submit" class="btn btn-primary btn-sm">Confirm Change</button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleChange(<?php echo (int)$enr['id']; ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<div class="footer">
    <p>• Phone: +37000000002</p>
    <p>• Email: admin@portal.com</p>
</div>

<script>
function toggleChange(id) {
    var form = document.getElementById('change-' + id);
    form.classList.toggle('open');
}
</script>

</body>
</html>