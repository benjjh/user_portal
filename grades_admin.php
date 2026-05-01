<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message = "";
$subject_id = (int)($_GET["subject_id"] ?? 0);

// Load all subjects
$subjects = [];
$sql = "SELECT s.id, s.subject_name, s.subject_code, u.username AS teacher_username
        FROM subjects s
        JOIN users u ON u.id = s.teacher_id
        ORDER BY s.subject_name ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $subjects[] = $row;
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
    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

// Save grade — graded_by is NULL for admin (FK is now nullable)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject_id  = (int)($_POST["subject_id"] ?? 0);
    $student_id  = (int)($_POST["student_id"] ?? 0);
    $assessment  = trim($_POST["assessment"] ?? "");
    $score       = (float)($_POST["score"] ?? 0);
    $max_score   = (float)($_POST["max_score"] ?? 100);

    if ($subject_id <= 0 || $student_id <= 0 || $assessment === "") {
        $message = "Please complete all fields.";
    } else {
        // graded_by = NULL because admin is not in the users table
        $stmt = $conn->prepare(
            "INSERT INTO grades (student_id, subject_id, assessment, score, max_score, graded_by)
             VALUES (?, ?, ?, ?, ?, NULL)"
        );
        $stmt->bind_param("iisdd", $student_id, $subject_id, $assessment, $score, $max_score);

        if ($stmt->execute()) {
            $message = "Grade uploaded successfully.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    header("location: grades_admin.php?subject_id=" . $subject_id . "&msg=" . urlencode($message));
    exit;
}

if (isset($_GET["msg"])) {
    $message = $_GET["msg"];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Grades | Admin</title>
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
        select, input[type="text"], input[type="number"] {
            width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px;
            font-size: 0.95rem; margin-bottom: 18px; background: #fafafa;
        }
        select:focus, input:focus { outline: none; border-color: #4a90d9; background: white; }
        .btn { padding: 11px 24px; background: #1a1a2e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
        .btn:hover { background: #2d2d5e; }
        .btn-outline { background: transparent; color: #1a1a2e; border: 2px solid #1a1a2e; margin-left: 10px; }
        .btn-outline:hover { background: #1a1a2e; color: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.92rem; }
        th { background: #f4f6fb; color: #444; font-weight: 700; }
        tr:hover td { background: #f9fafb; }
        .no-students { color: #dc3545; background: #fff3cd; padding: 14px; border-radius: 6px; border: 1px solid #ffc107; }
        .form-row { display: flex; gap: 16px; flex-wrap: wrap; }
        .form-row > div { flex: 1; min-width: 160px; }
    </style>
</head>
<body>
<div class="navbar">
    <strong>Admin Panel — Upload Grades</strong>
    <div>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h1>Upload Grades</h1>
    <p style="color:#666;margin-bottom:20px;">Select a subject to load its enrolled students, then enter grades.</p>

    <?php if ($message): ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Subject selector -->
    <div class="card">
        <form method="get">
            <label>Select Subject</label>
            <select name="subject_id" required>
                <option value="">-- choose subject --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? "selected" : ""; ?>>
                        <?php
                        echo htmlspecialchars(
                            $s['subject_name']
                            . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "")
                            . " — " . $s['teacher_username']
                        );
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Load Students</button>
        </form>
    </div>

    <?php if ($subject_id > 0): ?>
        <?php if (count($students) === 0): ?>
            <div class="no-students">No students are enrolled in this subject yet. Please enroll students first via <a href="enroll_students.php">Enroll Students</a>.</div>
        <?php else: ?>
        <div class="card">
            <h2 style="margin-bottom:20px;font-size:1.2rem;">Enter Grade</h2>
            <form method="post">
                <input type="hidden" name="subject_id" value="<?php echo (int)$subject_id; ?>">
                <div class="form-row">
                    <div>
                        <label>Choose Student</label>
                        <select name="student_id" required>
                            <option value="">-- choose student --</option>
                            <?php foreach ($students as $st): ?>
                                <option value="<?php echo (int)$st['id']; ?>">
                                    <?php
                                    $full = trim(($st['name'] ?? '') . ' ' . ($st['surname'] ?? ''));
                                    echo htmlspecialchars($st['username'] . ($full ? " ($full)" : ""));
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Assessment (e.g. Quiz 1, Exam)</label>
                        <input type="text" name="assessment" value="Test" required>
                    </div>
                    <div>
                        <label>Score</label>
                        <input type="number" step="0.01" name="score" required>
                    </div>
                    <div>
                        <label>Max Score</label>
                        <input type="number" step="0.01" name="max_score" value="10" required>
                    </div>
                </div>
                <button type="submit" class="btn">Save Grade</button>
            </form>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Recent grades table -->
    <div class="card">
        <h2 style="margin-bottom:16px;font-size:1.2rem;">All Grade Records</h2>
        <table>
            <tr>
                <th>Student</th>
                <th>Subject</th>
                <th>Assessment</th>
                <th>Score</th>
                <th>Date</th>
                <th>Recorded By</th>
            </tr>
            <?php
            $sql_grades = "SELECT g.assessment, g.score, g.max_score, g.graded_at,
                                  st.username AS student_username,
                                  s.subject_name, s.subject_code,
                                  u.username AS graded_by_username
                           FROM grades g
                           JOIN users st ON st.id = g.student_id
                           JOIN subjects s ON s.id = g.subject_id
                           LEFT JOIN users u ON u.id = g.graded_by
                           ORDER BY g.graded_at DESC";
            $recent = $conn->query($sql_grades);
            while ($g = $recent->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo htmlspecialchars($g['student_username']); ?></td>
                <td><?php echo htmlspecialchars($g['subject_name'] . (!empty($g['subject_code']) ? " ({$g['subject_code']})" : "")); ?></td>
                <td><?php echo htmlspecialchars($g['assessment']); ?></td>
                <td><?php echo htmlspecialchars($g['score'] . " / " . $g['max_score']); ?></td>
                <td><?php echo htmlspecialchars($g['graded_at']); ?></td>
                <td><?php echo htmlspecialchars($g['graded_by_username'] ?? 'Admin'); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>