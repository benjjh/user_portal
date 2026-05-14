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

// Fixed assessment types
$assessment_types = ['Exam', 'Mid-term', 'Exercise'];

// Teacher's subjects
$stmt = $conn->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? ORDER BY subject_name ASC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
$subjects = [];
while ($row = $res->fetch_assoc()) $subjects[] = $row;
$stmt->close();

$subject_id = (int)($_GET["subject_id"] ?? 0);
$edit_grade = null;

// ── DELETE GRADE ──
if (isset($_GET["delete_id"])) {
    $delete_id = (int)$_GET["delete_id"];
    // verify grade belongs to this teacher's subject
    $stmt = $conn->prepare(
        "SELECT g.id FROM grades g
         JOIN subjects s ON s.id = g.subject_id
         WHERE g.id = ? AND s.teacher_id = ?"
    );
    $stmt->bind_param("ii", $delete_id, $teacher_id);
    $stmt->execute();
    $ok = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($ok) {
        $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $message  = "Grade record deleted successfully.";
            $msg_type = "success";
        } else {
            $message  = "Error deleting grade: " . $stmt->error;
            $msg_type = "error";
        }
        $stmt->close();
    }
    header("location: grades_teacher.php?subject_id=" . $subject_id . "&msg=" . urlencode($message) . "&mtype=" . $msg_type);
    exit;
}

// ── LOAD GRADE FOR EDITING ──
if (isset($_GET["edit_id"])) {
    $edit_id = (int)$_GET["edit_id"];
    $stmt = $conn->prepare(
        "SELECT g.* FROM grades g
         JOIN subjects s ON s.id = g.subject_id
         WHERE g.id = ? AND s.teacher_id = ?"
    );
    $stmt->bind_param("ii", $edit_id, $teacher_id);
    $stmt->execute();
    $edit_grade = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($edit_grade) {
        $subject_id = (int)$edit_grade['subject_id'];
    }
}

// ── SAVE / UPDATE GRADE ──
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject_id  = (int)($_POST["subject_id"] ?? 0);
    $student_id  = (int)($_POST["student_id"] ?? 0);
    $assessment  = trim($_POST["assessment"] ?? "");
    $score       = (float)($_POST["score"] ?? 0);
    $max_score   = 10; // fixed max score
    $is_edit     = isset($_POST["grade_id"]) && (int)$_POST["grade_id"] > 0;
    $grade_id    = (int)($_POST["grade_id"] ?? 0);

    if ($subject_id <= 0 || $student_id <= 0 || $assessment === "") {
        $message  = "Please fill all fields.";
        $msg_type = "error";
    } elseif ($score < 0 || $score > 10) {
        $message  = "Score must be between 0 and 10.";
        $msg_type = "error";
    } else {
        // verify subject belongs to teacher
        $stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $subject_id, $teacher_id);
        $stmt->execute();
        $ok = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ok) {
            $message  = "Invalid subject selection.";
            $msg_type = "error";
        } else {
            if ($is_edit) {
                // Update existing grade
                $stmt = $conn->prepare(
                    "UPDATE grades SET student_id=?, subject_id=?, assessment=?, score=?, max_score=?
                     WHERE id=?"
                );
                $stmt->bind_param("iisddi", $student_id, $subject_id, $assessment, $score, $max_score, $grade_id);
                if ($stmt->execute()) {
                    $message  = "Grade updated successfully.";
                    $msg_type = "success";
                } else {
                    $message  = "Error updating grade: " . $stmt->error;
                    $msg_type = "error";
                }
                $stmt->close();
            } else {
                // Check for duplicate assessment for this student+subject
                $stmt = $conn->prepare(
                    "SELECT id FROM grades WHERE student_id=? AND subject_id=? AND assessment=?"
                );
                $stmt->bind_param("iis", $student_id, $subject_id, $assessment);
                $stmt->execute();
                $dup = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($dup) {
                    $message  = "A '$assessment' grade already exists for this student in this subject. Use the Edit button to update it.";
                    $msg_type = "error";
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO grades (student_id, subject_id, assessment, score, max_score, graded_by)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("iisddi", $student_id, $subject_id, $assessment, $score, $max_score, $teacher_id);
                    if ($stmt->execute()) {
                        $message  = "Grade saved successfully.";
                        $msg_type = "success";
                    } else {
                        $message  = "Error: " . $stmt->error;
                        $msg_type = "error";
                    }
                    $stmt->close();
                }
            }
        }
    }

    header("location: grades_teacher.php?subject_id=" . $subject_id . "&msg=" . urlencode($message) . "&mtype=" . $msg_type);
    exit;
}

if (isset($_GET["msg"]))   $message  = $_GET["msg"];
if (isset($_GET["mtype"])) $msg_type = $_GET["mtype"];

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

// Load latest attendance status for each student in this subject (most recent date)
$attendance_map = [];
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT a.student_id, a.status, a.attendance_date
         FROM attendance a
         INNER JOIN (
             SELECT student_id, MAX(attendance_date) AS max_date
             FROM attendance
             WHERE subject_id = ?
             GROUP BY student_id
         ) latest ON a.student_id = latest.student_id AND a.attendance_date = latest.max_date
         WHERE a.subject_id = ?"
    );
    $stmt->bind_param("ii", $subject_id, $subject_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $attendance_map[(int)$row['student_id']] = $row;
    }
    $stmt->close();
}

// Load grades for this subject grouped by student and assessment type
$grades_map = []; // $grades_map[student_id][assessment] = grade row
$all_grades = [];
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT g.id, g.student_id, g.assessment, g.score, g.max_score, g.graded_at,
                u.username AS student_username, u.name AS student_name, u.surname AS student_surname
         FROM grades g
         JOIN users u ON u.id = g.student_id
         JOIN subjects s ON s.id = g.subject_id
         WHERE g.subject_id = ? AND s.teacher_id = ?
         ORDER BY u.username ASC, g.assessment ASC"
    );
    $stmt->bind_param("ii", $subject_id, $teacher_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $grades_map[(int)$row['student_id']][$row['assessment']] = $row;
        $all_grades[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Grades</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f4f0; min-height: 100vh; display: flex; flex-direction: column; }

        .navbar { background: linear-gradient(135deg,#1a3a2a,#2d5a3d); color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { font-size: 1.1rem; font-weight: 700; }
        .navbar a { color: #a8d5b5; text-decoration: none; margin-left: 18px; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }

        .container { max-width: 1100px; margin: 36px auto; padding: 0 20px; flex: 1; width: 100%; }
        .page-title { margin-bottom: 24px; }
        .page-title h1 { font-size: 1.8rem; color: #1a3a2a; }
        .page-title p  { color: #666; margin-top: 5px; font-size: 0.95rem; }

        .msg { padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 0.93rem; font-weight: 600; }
        .msg-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .card { background: white; border-radius: 12px; padding: 26px; box-shadow: 0 2px 14px rgba(0,0,0,0.07); margin-bottom: 26px; }
        .card h2 { font-size: 1.05rem; color: #1a3a2a; font-weight: 700; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #f0f4f0; }
        .card.editing { border: 2px solid #fd7e14; }
        .card.editing h2 { color: #fd7e14; }

        /* Subject selector */
        .selector-row { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
        .selector-row > div { flex: 1; min-width: 200px; }
        label { display: block; font-weight: 600; color: #444; font-size: 0.87rem; margin-bottom: 6px; }
        select, input[type="number"], input[type="text"] { width: 100%; padding: 10px 13px; border: 1px solid #ccc; border-radius: 7px; font-size: 0.93rem; margin-bottom: 0; background: #fafafa; font-family: inherit; transition: border-color 0.15s; }
        select:focus, input:focus { outline: none; border-color: #2d5a3d; background: white; }

        .btn { padding: 10px 22px; border: none; border-radius: 7px; cursor: pointer; font-size: 0.88rem; font-weight: 700; transition: opacity 0.15s; text-decoration: none; display: inline-block; }
        .btn:hover { opacity: 0.85; }
        .btn-primary   { background: #1a3a2a; color: white; }
        .btn-warning   { background: #fd7e14; color: white; }
        .btn-danger    { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 5px 12px; font-size: 0.8rem; }
        .btn-load { align-self: flex-end; padding: 10px 20px; }

        /* Grade entry form grid */
        .grade-form-grid { display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr auto; gap: 12px; align-items: end; }
        .grade-form-grid label { margin-top: 10px; }

        /* Grade overview table */
        table { width: 100%; border-collapse: collapse; }
        thead tr { background: #1a3a2a; }
        thead th { color: white; padding: 12px 13px; text-align: left; font-size: 0.85rem; font-weight: 700; }
        thead th:first-child { border-radius: 6px 0 0 0; }
        thead th:last-child  { border-radius: 0 6px 0 0; }
        tbody tr { border-bottom: 1px solid #eef2ee; transition: background 0.1s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f6fbf7; }
        tbody td { padding: 12px 13px; font-size: 0.88rem; color: #333; vertical-align: middle; }

        .student-name { font-weight: 700; color: #1a3a2a; }

        /* Attendance badges */
        .att { display: inline-block; padding: 3px 11px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; text-transform: capitalize; }
        .att-present { background: #d4edda; color: #155724; }
        .att-absent  { background: #f8d7da; color: #721c24; }
        .att-late    { background: #fff3cd; color: #856404; }
        .att-excused { background: #e2e3e5; color: #383d41; }
        .att-none    { background: #f0f0f0; color: #aaa; }

        /* Score cells */
        .score-cell { text-align: center; }
        .score-val { display: inline-block; background: #e8f5e9; color: #1a3a2a; padding: 3px 11px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; }
        .score-none { color: #ccc; font-size: 0.85rem; }

        /* Action cell */
        .act-cell { display: flex; gap: 5px; flex-wrap: wrap; }

        /* Max score note */
        .max-note { font-size: 0.78rem; color: #888; margin-top: 3px; }

        .empty { text-align: center; padding: 36px; color: #aaa; }
        .empty .icon { font-size: 2.5rem; margin-bottom: 10px; }

        .footer { background: #1a3a2a; color: #a8d5b5; padding: 22px 40px; font-size: 0.88rem; }
        .footer p { margin-top: 4px; }

        /* Assessment type colour dots */
        .type-exam     { color: #1a3a2a; font-weight: 700; }
        .type-midterm  { color: #0d6efd; font-weight: 700; }
        .type-exercise { color: #6f42c1; font-weight: 700; }
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

    <div class="page-title">
        <h1>Enter Grades</h1>
        <p>Select a subject to view students, their attendance, and enter or edit grades. Maximum score per assessment is <strong>10</strong>.</p>
    </div>

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Subject selector -->
    <div class="card">
        <h2>📚 Select Subject</h2>
        <form method="get">
            <div class="selector-row">
                <div>
                    <label>Subject</label>
                    <select name="subject_id" required onchange="this.form.submit()">
                        <option value="">-- choose subject --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "")); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if ($subject_id > 0): ?>

        <?php if (count($students) === 0): ?>
            <div class="card">
                <div class="empty">
                    <div class="icon">👥</div>
                    <p>No students enrolled in this subject yet. Ask the admin to enroll students.</p>
                </div>
            </div>
        <?php else: ?>

        <!-- Edit grade form (shown only when editing) -->
        <?php if ($edit_grade): ?>
        <div class="card editing">
            <h2>✏️ Editing Grade Record #<?php echo (int)$edit_grade['id']; ?></h2>
            <form method="post">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <input type="hidden" name="grade_id"   value="<?php echo (int)$edit_grade['id']; ?>">
                <div class="grade-form-grid">
                    <div>
                        <label>Student</label>
                        <select name="student_id" required>
                            <?php foreach ($students as $st):
                                $full  = trim(($st['name'] ?? '') . ' ' . ($st['surname'] ?? ''));
                                $label = $st['username'] . ($full ? " ($full)" : "");
                            ?>
                                <option value="<?php echo (int)$st['id']; ?>" <?php echo ($st['id'] == $edit_grade['student_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Assessment Type</label>
                        <select name="assessment" required>
                            <?php foreach ($assessment_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo ($edit_grade['assessment'] === $type) ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Score (0–10)</label>
                        <input type="number" name="score" step="0.01" min="0" max="10"
                               value="<?php echo htmlspecialchars($edit_grade['score']); ?>" required>
                        <p class="max-note">Max: 10</p>
                    </div>
                    <div style="align-self:end;">
                        <button type="submit" class="btn btn-warning" style="width:100%;margin-bottom:0;">Save Changes</button>
                    </div>
                    <div style="align-self:end;">
                        <a href="grades_teacher.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-secondary" style="width:100%;text-align:center;">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Add new grade form -->
        <div class="card">
            <h2>➕ Add New Grade</h2>
            <form method="post">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <div class="grade-form-grid">
                    <div>
                        <label>Student</label>
                        <select name="student_id" required>
                            <option value="">-- choose student --</option>
                            <?php foreach ($students as $st):
                                $full  = trim(($st['name'] ?? '') . ' ' . ($st['surname'] ?? ''));
                                $label = $st['username'] . ($full ? " ($full)" : "");
                                $att   = $attendance_map[(int)$st['id']] ?? null;
                                $att_txt = $att ? " [{$att['status']}]" : "";
                            ?>
                                <option value="<?php echo (int)$st['id']; ?>">
                                    <?php echo htmlspecialchars($label . $att_txt); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Assessment Type</label>
                        <select name="assessment" required>
                            <option value="">-- select type --</option>
                            <?php foreach ($assessment_types as $type): ?>
                                <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Score (0–10)</label>
                        <input type="number" name="score" step="0.01" min="0" max="10" placeholder="e.g. 7.5" required>
                        <p class="max-note">Max score: 10</p>
                    </div>
                    <div style="align-self:end;">
                        <button type="submit" class="btn btn-primary" style="width:100%;">Save Grade</button>
                    </div>
                    <div></div>
                </div>
            </form>
        </div>

        <!-- Grade overview table: one row per student, columns per assessment type -->
        <div class="card">
            <h2>📊 Grade Overview — <?php
                foreach ($subjects as $s) {
                    if ($s['id'] == $subject_id) {
                        echo htmlspecialchars($s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : ""));
                    }
                }
            ?></h2>
            <p style="font-size:0.85rem;color:#888;margin-bottom:16px;">
                Attendance shown is the most recent recorded date for each student in this subject.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Last Attendance</th>
                        <th style="text-align:center;">Exam <small style="opacity:.7;">/10</small></th>
                        <th style="text-align:center;">Mid-term <small style="opacity:.7;">/10</small></th>
                        <th style="text-align:center;">Exercise <small style="opacity:.7;">/10</small></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $st):
                        $sid   = (int)$st['id'];
                        $full  = trim(($st['name'] ?? '') . ' ' . ($st['surname'] ?? ''));
                        $label = $st['username'] . ($full ? " ($full)" : "");
                        $att   = $attendance_map[$sid] ?? null;
                        $att_status = $att ? $att['status'] : null;
                        $att_date   = $att ? $att['attendance_date'] : null;

                        $g_exam     = $grades_map[$sid]['Exam']     ?? null;
                        $g_midterm  = $grades_map[$sid]['Mid-term']  ?? null;
                        $g_exercise = $grades_map[$sid]['Exercise']  ?? null;
                    ?>
                    <tr>
                        <td><span class="student-name"><?php echo htmlspecialchars($label); ?></span></td>
                        <td>
                            <?php if ($att_status): ?>
                                <span class="att att-<?php echo $att_status; ?>"><?php echo ucfirst($att_status); ?></span>
                                <br><span style="font-size:0.78rem;color:#aaa;"><?php echo htmlspecialchars($att_date); ?></span>
                            <?php else: ?>
                                <span class="att att-none">No record</span>
                            <?php endif; ?>
                        </td>

                        <!-- Exam column -->
                        <td class="score-cell">
                            <?php if ($g_exam): ?>
                                <span class="score-val"><?php echo htmlspecialchars($g_exam['score']); ?></span>
                                <div class="act-cell" style="justify-content:center;margin-top:5px;">
                                    <a href="grades_teacher.php?subject_id=<?php echo $subject_id; ?>&edit_id=<?php echo (int)$g_exam['id']; ?>" class="btn btn-warning btn-sm">✏️</a>
                                    <a href="grades_teacher.php?subject_id=<?php echo $subject_id; ?>&delete_id=<?php echo (int)$g_exam['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this Exam grade?')">🗑️</a>
                                </div>
                            <?php else: ?>
                                <span class="score-none">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Mid-term column -->
                        <td class="score-cell">
                            <?php if ($g_midterm): ?>
                                <span class="score-val"><?php echo htmlspecialchars($g_midterm['score']); ?></span>
                                <div class="act-cell" style="justify-content:center;margin-top:5px;">
                                    <a href="grades_teacher.php?subject_id=<?php echo $subject_id; ?>&edit_id=<?php echo (int)$g_midterm['id']; ?>" class="btn btn-warning btn-sm">✏️</a>
                                    <a href="grades_teacher.php?subject_id=<?php echo $subject_id; ?>&delete_id=<?php echo (int)$g_midterm['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this Mid-term grade?')">🗑️</a>
                                </div>
                            <?php else: ?>
                                <span class="score-none">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Exercise column -->
                        <td class="score-cell">
                            <?php if ($g_exercise): ?>
                                <span class="score-val"><?php echo htmlspecialchars($g_exercise['score']); ?></span>
                                <div class="act-cell" style="justify-content:center;margin-top:5px;">
                                    <a href="grades_teacher.php?subject_id=<?php echo $subject_id; ?>&edit_id=<?php echo (int)$g_exercise['id']; ?>" class="btn btn-warning btn-sm">✏️</a>
                                    <a href="grades_teacher.php?subject_id=<?php echo $subject_id; ?>&delete_id=<?php echo (int)$g_exercise['id']; ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Delete this Exercise grade?')">🗑️</a>
                                </div>
                            <?php else: ?>
                                <span class="score-none">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <!-- Quick add missing grades -->
                            <?php
                            $missing = [];
                            if (!$g_exam)     $missing[] = 'Exam';
                            if (!$g_midterm)  $missing[] = 'Mid-term';
                            if (!$g_exercise) $missing[] = 'Exercise';
                            if (count($missing) > 0):
                            ?>
                            <span style="font-size:0.78rem;color:#aaa;">
                                Missing: <?php echo implode(', ', $missing); ?>
                            </span>
                            <?php else: ?>
                            <span style="font-size:0.78rem;color:#28a745;font-weight:700;">✅ Complete</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php endif; // end students check ?>

    <?php endif; // end subject_id check ?>

</div>

<div class="footer">
    <p>• Phone: +37000000001</p>
    <p>• Email: info@portal.com</p>
</div>

</body>
</html>