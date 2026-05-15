<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message    = "";
$msg_type   = "success";
$subject_id = (int)($_GET["subject_id"] ?? 0);
$edit_grade = null;

// Fixed assessment types
$assessment_types = ['Exam', 'Mid-term', 'Exercise'];

// Load all subjects
$subjects = [];
$sql = "SELECT s.id, s.subject_name, s.subject_code, u.username AS teacher_username
        FROM subjects s
        JOIN users u ON u.id = s.teacher_id
        ORDER BY s.subject_name ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) $subjects[] = $row;

// ── DELETE GRADE ──
if (isset($_GET["delete_id"])) {
    $delete_id = (int)$_GET["delete_id"];
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
    header("location: grades_admin.php?subject_id=" . $subject_id . "&msg=" . urlencode($message) . "&mtype=" . $msg_type);
    exit;
}

// ── LOAD GRADE FOR EDITING ──
if (isset($_GET["edit_id"])) {
    $edit_id = (int)$_GET["edit_id"];
    $stmt = $conn->prepare("SELECT * FROM grades WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_grade = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($edit_grade) {
        $subject_id = (int)$edit_grade['subject_id'];
    }
}

// ── UPDATE GRADE ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update_grade") {
    $grade_id   = (int)$_POST["grade_id"];
    $student_id = (int)$_POST["student_id"];
    $subject_id = (int)$_POST["subject_id"];
    $assessment = trim($_POST["assessment"] ?? "");
    $score      = (float)$_POST["score"];
    $max_score  = 10;

    if ($score < 0 || $score > 10) {
        $message  = "Score must be between 0 and 10.";
        $msg_type = "error";
    } else {
        $stmt = $conn->prepare(
            "UPDATE grades SET student_id=?, subject_id=?, assessment=?, score=?, max_score=?
             WHERE id=?"
        );
        $stmt->bind_param("iisddi", $student_id, $subject_id, $assessment, $score, $max_score, $grade_id);
        if ($stmt->execute()) {
            $message  = "Grade updated successfully.";
            $msg_type = "success";
            $edit_grade = null;
        } else {
            $message  = "Error: " . $stmt->error;
            $msg_type = "error";
        }
        $stmt->close();
    }
    header("location: grades_admin.php?subject_id=" . $subject_id . "&msg=" . urlencode($message) . "&mtype=" . $msg_type);
    exit;
}

// ── SAVE NEW GRADE ──
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "save_grade") {
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $student_id = (int)($_POST["student_id"] ?? 0);
    $assessment = trim($_POST["assessment"] ?? "");
    $score      = (float)($_POST["score"] ?? 0);
    $max_score  = 10;

    if ($subject_id <= 0 || $student_id <= 0 || $assessment === "") {
        $message  = "Please complete all fields.";
        $msg_type = "error";
    } elseif ($score < 0 || $score > 10) {
        $message  = "Score must be between 0 and 10.";
        $msg_type = "error";
    } else {
        // Check for duplicate
        $stmt = $conn->prepare("SELECT id FROM grades WHERE student_id=? AND subject_id=? AND assessment=?");
        $stmt->bind_param("iis", $student_id, $subject_id, $assessment);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($dup) {
            $message  = "A '$assessment' grade already exists for this student. Use the Edit button to update it.";
            $msg_type = "error";
        } else {
            // graded_by = NULL for admin
            $stmt = $conn->prepare(
                "INSERT INTO grades (student_id, subject_id, assessment, score, max_score, graded_by)
                 VALUES (?, ?, ?, ?, ?, NULL)"
            );
            $stmt->bind_param("iisdd", $student_id, $subject_id, $assessment, $score, $max_score);
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
    header("location: grades_admin.php?subject_id=" . $subject_id . "&msg=" . urlencode($message) . "&mtype=" . $msg_type);
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

// Load most recent attendance per student for this subject
$attendance_map = [];
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT a.student_id, a.status, a.attendance_date
         FROM attendance a
         INNER JOIN (
             SELECT student_id, MAX(attendance_date) AS max_date
             FROM attendance WHERE subject_id = ?
             GROUP BY student_id
         ) latest ON a.student_id = latest.student_id AND a.attendance_date = latest.max_date
         WHERE a.subject_id = ?"
    );
    $stmt->bind_param("ii", $subject_id, $subject_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $attendance_map[(int)$row['student_id']] = $row;
    $stmt->close();
}

// Load grades for this subject grouped by student
$grades_map = [];
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT g.id, g.student_id, g.assessment, g.score, g.max_score, g.graded_at
         FROM grades g
         WHERE g.subject_id = ?
         ORDER BY g.graded_at DESC"
    );
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $grades_map[(int)$row['student_id']][$row['assessment']] = $row;
    }
    $stmt->close();
}

// Performance label
function perfLabel($score) {
    $s = (float)$score;
    if ($s == 10) return ['label'=>'Excellent', 'class'=>'perf-excellent'];
    if ($s >= 8)  return ['label'=>'Very Good', 'class'=>'perf-verygood'];
    if ($s >= 6)  return ['label'=>'Good',      'class'=>'perf-good'];
    if ($s >= 5)  return ['label'=>'Average',   'class'=>'perf-average'];
    return             ['label'=>'Failed',    'class'=>'perf-failed'];
}

// Get selected subject name
$selected_subject_name = "";
foreach ($subjects as $s) {
    if ((int)$s['id'] === $subject_id) {
        $selected_subject_name = $s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "") . " — " . $s['teacher_username'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Grades | Admin</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',Arial,sans-serif; background:#f0f2f5; min-height:100vh; display:flex; flex-direction:column; }

        .navbar { background:#1a1a2e; padding:15px 40px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.15); }
        .navbar .logo { color:white; font-size:1.1rem; font-weight:700; }
        .navbar .nav-links a { color:#a0c4ff; text-decoration:none; margin-left:20px; font-weight:600; font-size:0.92rem; }
        .navbar .nav-links a:hover { color:white; }

        .container { max-width:1150px; margin:36px auto; padding:0 20px; flex:1; width:100%; }

        .page-title { margin-bottom:26px; }
        .page-title h1 { font-size:1.8rem; color:#1a1a2e; font-weight:800; }
        .page-title p  { color:#666; margin-top:5px; font-size:0.95rem; }

        .msg { padding:12px 18px; border-radius:8px; margin-bottom:22px; font-size:0.93rem; font-weight:600; display:flex; align-items:center; gap:10px; }
        .msg-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .msg-error   { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

        .card { background:white; border-radius:12px; padding:26px; box-shadow:0 2px 14px rgba(0,0,0,0.07); margin-bottom:26px; }
        .card h2 { font-size:1.05rem; color:#1a1a2e; font-weight:700; margin-bottom:18px; padding-bottom:10px; border-bottom:2px solid #f0f2f5; }
        .card.editing { border:2px solid #fd7e14; }
        .card.editing h2 { color:#fd7e14; }

        label { display:block; font-weight:600; color:#444; font-size:0.87rem; margin-bottom:6px; }
        select, input[type="number"] {
            width:100%; padding:10px 13px; border:1.5px solid #ddd; border-radius:8px;
            font-size:0.93rem; background:#fafafa; font-family:inherit;
            transition:border-color 0.15s, background 0.15s;
        }
        select:focus, input:focus { outline:none; border-color:#1a1a2e; background:white; }

        .form-grid { display:grid; grid-template-columns:2fr 1.5fr 1fr 1fr auto; gap:14px; align-items:end; }
        .form-grid label { margin-top:10px; }

        .btn { padding:10px 22px; border:none; border-radius:8px; cursor:pointer; font-size:0.88rem; font-weight:700; transition:opacity 0.15s; text-decoration:none; display:inline-block; text-align:center; }
        .btn:hover { opacity:0.85; }
        .btn-primary   { background:#1a1a2e; color:white; }
        .btn-warning   { background:#fd7e14; color:white; }
        .btn-danger    { background:#dc3545; color:white; }
        .btn-secondary { background:#6c757d; color:white; }
        .btn-sm { padding:5px 12px; font-size:0.8rem; }
        .btn-load { width:100%; }

        .max-note { font-size:0.75rem; color:#aaa; margin-top:3px; }

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

        .student-name { font-weight:700; color:#1a1a2e; }
        .student-user { font-size:0.78rem; color:#aaa; }

        /* Attendance badges */
        .att { display:inline-block; padding:3px 11px; border-radius:20px; font-size:0.78rem; font-weight:700; }
        .att-present { background:#d4edda; color:#155724; }
        .att-absent  { background:#f8d7da; color:#721c24; }
        .att-late    { background:#fff3cd; color:#856404; }
        .att-excused { background:#e2e3e5; color:#383d41; }
        .att-none    { background:#f0f0f0; color:#aaa; }

        /* Score cells */
        .score-cell { text-align:center; }
        .score-val { display:inline-block; background:#e8eef8; color:#1a1a2e; padding:3px 11px; border-radius:20px; font-size:0.82rem; font-weight:700; }
        .score-fail { background:#f8d7da; color:#721c24; }
        .score-none { color:#ccc; font-size:0.85rem; }
        .act-cell { display:flex; gap:5px; flex-wrap:wrap; justify-content:center; margin-top:5px; }

        /* Performance */
        .perf { display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.76rem; font-weight:800; text-transform:uppercase; letter-spacing:0.3px; margin-top:3px; }
        .perf-excellent { background:#d4edda; color:#155724; }
        .perf-verygood  { background:#d1f2eb; color:#0e6251; }
        .perf-good      { background:#cce5ff; color:#004085; }
        .perf-average   { background:#fff3cd; color:#856404; }
        .perf-failed    { background:#f8d7da; color:#721c24; }

        .complete-badge { font-size:0.78rem; color:#28a745; font-weight:700; }
        .missing-note   { font-size:0.78rem; color:#aaa; }

        .empty { text-align:center; padding:40px; color:#bbb; }
        .empty .icon { font-size:2.8rem; margin-bottom:12px; }

        .no-subject { text-align:center; padding:48px 20px; color:#aaa; }
        .no-subject .icon { font-size:3rem; margin-bottom:12px; }

        .footer { background:#1a1a2e; color:#a0c4ff; padding:22px 40px; font-size:0.88rem; display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; }
        .footer p { margin-top:3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="logo">Admin Panel</div>
    <div class="nav-links">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="view_grades_admin.php">View Grades</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <div class="page-title">
        <h1>Upload Grades</h1>
        <p>Select a subject to load enrolled students, then enter or edit grades. Maximum score is <strong>10</strong>.</p>
    </div>

    <?php if ($message): ?>
        <div class="msg msg-<?php echo $msg_type; ?>">
            <?php echo $msg_type === 'success' ? '' : ''; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Subject selector -->
    <div class="card">
        <h2>📚 Select Subject</h2>
        <form method="get">
            <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
                <div style="flex:1;min-width:220px;">
                    <label>Subject</label>
                    <select name="subject_id" required onchange="this.form.submit()">
                        <option value="">-- choose subject --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id']===$subject_id)?'selected':''; ?>>
                                <?php echo htmlspecialchars($s['subject_name'].(!empty($s['subject_code'])?" ({$s['subject_code']})":'')." — ".$s['teacher_username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if ($subject_id <= 0): ?>
        <div class="card">
            <div class="no-subject">
                <div class="icon">📝</div>
                <p>Choose a subject above to load students and begin entering grades.</p>
            </div>
        </div>

    <?php elseif (count($students) === 0): ?>
        <div class="card">
            <div class="empty">
                <div class="icon">👥</div>
                <p>No students enrolled in this subject yet.</p>
                <p style="margin-top:8px;font-size:0.88rem;"><a href="enroll_students.php" style="color:#1a1a2e;font-weight:700;">Go to Enroll Students →</a></p>
            </div>
        </div>

    <?php else: ?>

        <!-- Edit grade form -->
        <?php if ($edit_grade): ?>
        <div class="card editing">
            <h2>✏️ Editing Grade Record #<?php echo (int)$edit_grade['id']; ?></h2>
            <form method="post">
                <input type="hidden" name="action"     value="update_grade">
                <input type="hidden" name="grade_id"   value="<?php echo (int)$edit_grade['id']; ?>">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <div class="form-grid">
                    <div>
                        <label>Student</label>
                        <select name="student_id" required>
                            <?php foreach ($students as $st):
                                $full  = trim(($st['name']??'').' '.($st['surname']??''));
                                $label = $st['username'].($full?" ($full)":'');
                            ?>
                                <option value="<?php echo (int)$st['id']; ?>" <?php echo ($st['id']==$edit_grade['student_id'])?'selected':''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Assessment Type</label>
                        <select name="assessment" required>
                            <?php foreach ($assessment_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo ($edit_grade['assessment']===$type)?'selected':''; ?>>
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
                        <button type="submit" class="btn btn-warning btn-load">Save Changes</button>
                    </div>
                    <div style="align-self:end;">
                        <a href="grades_admin.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-secondary btn-load">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Add new grade form -->
        <div class="card">
            <h2>➕ Add New Grade</h2>
            <form method="post">
                <input type="hidden" name="action"     value="save_grade">
                <input type="hidden" name="subject_id" value="<?php echo $subject_id; ?>">
                <div class="form-grid">
                    <div>
                        <label>Student</label>
                        <select name="student_id" required>
                            <option value="">-- choose student --</option>
                            <?php foreach ($students as $st):
                                $full  = trim(($st['name']??'').' '.($st['surname']??''));
                                $att   = $attendance_map[(int)$st['id']] ?? null;
                                $att_txt = $att ? " [{$att['status']}]" : "";
                                $label = $st['username'].($full?" ($full)":'').$att_txt;
                            ?>
                                <option value="<?php echo (int)$st['id']; ?>">
                                    <?php echo htmlspecialchars($label); ?>
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
                        <button type="submit" class="btn btn-primary btn-load">Save Grade</button>
                    </div>
                    <div></div>
                </div>
            </form>
        </div>

        <!-- Grade overview table -->
        <div class="card">
            <h2>📊 Grade Overview — <?php echo htmlspecialchars($selected_subject_name); ?></h2>
            <p style="font-size:0.84rem;color:#888;margin-bottom:16px;">
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
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $st):
                        $sid   = (int)$st['id'];
                        $full  = trim(($st['name']??'').' '.($st['surname']??''));
                        $label = $st['username'].($full?" ($full)":'');
                        $att   = $attendance_map[$sid] ?? null;

                        $g_exam     = $grades_map[$sid]['Exam']     ?? null;
                        $g_midterm  = $grades_map[$sid]['Mid-term']  ?? null;
                        $g_exercise = $grades_map[$sid]['Exercise']  ?? null;

                        // Build score cell helper
                        function scoreCell($g, $subject_id) {
                            if (!$g) return '<span class="score-none">—</span>';
                            $score = (float)$g['score'];
                            $perf  = perfLabel($score);
                            $fail_cls = $score < 5 ? ' score-fail' : '';
                            $out  = '<span class="score-val'.$fail_cls.'">'.$score.'</span>';
                            $out .= '<br><span class="perf '.$perf['class'].'">'.$perf['label'].'</span>';
                            $out .= '<div class="act-cell">';
                            $out .= '<a href="grades_admin.php?subject_id='.$subject_id.'&edit_id='.(int)$g['id'].'" class="btn btn-warning btn-sm">✏️</a>';
                            $out .= '<a href="grades_admin.php?subject_id='.$subject_id.'&delete_id='.(int)$g['id'].'" class="btn btn-danger btn-sm" onclick="return confirm(\'Delete this grade record?\')">🗑️</a>';
                            $out .= '</div>';
                            return $out;
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="student-name"><?php echo htmlspecialchars($full ?: $st['username']); ?></div>
                            <div class="student-user">@<?php echo htmlspecialchars($st['username']); ?></div>
                        </td>
                        <td>
                            <?php if ($att): ?>
                                <span class="att att-<?php echo $att['status']; ?>"><?php echo ucfirst($att['status']); ?></span>
                                <br><span style="font-size:0.75rem;color:#aaa;"><?php echo htmlspecialchars($att['attendance_date']); ?></span>
                            <?php else: ?>
                                <span class="att att-none">No record</span>
                            <?php endif; ?>
                        </td>
                        <td class="score-cell"><?php echo scoreCell($g_exam, $subject_id); ?></td>
                        <td class="score-cell"><?php echo scoreCell($g_midterm, $subject_id); ?></td>
                        <td class="score-cell"><?php echo scoreCell($g_exercise, $subject_id); ?></td>
                        <td>
                            <?php
                            $missing = [];
                            if (!$g_exam)     $missing[] = 'Exam';
                            if (!$g_midterm)  $missing[] = 'Mid-term';
                            if (!$g_exercise) $missing[] = 'Exercise';
                            if (count($missing) === 0):
                            ?>
                                <span class="complete-badge"> Complete</span>
                            <?php else: ?>
                                <span class="missing-note">Missing:<br><?php echo implode(', ', $missing); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

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