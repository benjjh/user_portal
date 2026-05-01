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

// Teacher subjects
$stmt = $conn->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
$subjects = [];
while ($row = $res->fetch_assoc()) $subjects[] = $row;
$stmt->close();

$subject_id = (int)($_GET["subject_id"] ?? 0);

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

// Save grade
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $subject_id = (int)($_POST["subject_id"] ?? 0);
    $student_id = (int)($_POST["student_id"] ?? 0);
    $assessment = trim($_POST["assessment"] ?? "Test");
    $score = (float)($_POST["score"] ?? 0);
    $max_score = (float)($_POST["max_score"] ?? 100);

    if ($subject_id <= 0 || $student_id <= 0 || $assessment === "") {
        $message = "Please fill all fields.";
    } else {
        // verify subject belongs to teacher
        $stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
        $stmt->bind_param("ii", $subject_id, $teacher_id);
        $stmt->execute();
        $ok = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ok) {
            $message = "Invalid subject selection.";
        } else {
            // verify student is enrolled
            $stmt = $conn->prepare("SELECT id FROM enrollments WHERE student_id = ? AND subject_id = ?");
            $stmt->bind_param("ii", $student_id, $subject_id);
            $stmt->execute();
            $en = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$en) {
                $message = "Student is not enrolled in this subject.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO grades (student_id, subject_id, assessment, score, max_score, graded_by)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("iisddi", $student_id, $subject_id, $assessment, $score, $max_score, $teacher_id);
                if ($stmt->execute()) {
                    $message = "Grade saved successfully.";
                } else {
                    $message = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // Keep subject selected after post
    header("location: grades_teacher.php?subject_id=" . $subject_id . "&msg=" . urlencode($message));
    exit;
}

if (isset($_GET["msg"])) $message = $_GET["msg"];

// Recent grades for teacher's subjects
$stmt = $conn->prepare(
    "SELECT g.id, g.assessment, g.score, g.max_score, g.graded_at,
            s.subject_name, s.subject_code,
            u.username AS student_username
     FROM grades g
     JOIN subjects s ON s.id = g.subject_id
     JOIN users u ON u.id = g.student_id
     WHERE s.teacher_id = ?
     ORDER BY g.id DESC
     LIMIT 20"
);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$recent = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Grades</title>
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

    <h1>Enter Grades</h1>
    <p class="msg"><?php echo htmlspecialchars($message); ?></p>

    <form method="get">
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
        <button type="submit">Load Students</button>
    </form>

    <?php if ($subject_id > 0): ?>
        <?php if (count($students) === 0): ?>
            <p>No students enrolled in this subject yet. Enroll students first.</p>
        <?php else: ?>
            <form method="post" class="rowflex">
                <input type="hidden" name="subject_id" value="<?php echo (int)$subject_id; ?>">

                <div>
                    <label>Student</label>
                    <select name="student_id" required>
                        <option value="">-- choose student --</option>
                        <?php foreach ($students as $st): ?>
                            <?php
                                $full = trim(($st['name'] ?? '') . ' ' . ($st['surname'] ?? ''));
                                $label = $st["username"] . ($full ? " ($full)" : "");
                            ?>
                            <option value="<?php echo (int)$st['id']; ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Assessment (e.g., Quiz 1, Midterm, Exam)</label>
                    <input type="text" name="assessment" value="Test" required>
                </div>

                <div>
                    <label>Score</label>
                    <input type="number" step="0.01" name="score" required>
                </div>

                <div>
                    <label>Max Score</label>
                    <input type="number" step="0.01" name="max_score" value="100" required>
                </div>

                <div style="align-self:end;">
                    <button type="submit">Save Grade</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Recent Grades (Your Subjects)</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Student</th>
            <th>Subject</th>
            <th>Assessment</th>
            <th>Score</th>
            <th>Date</th>
        </tr>
        <?php while($g = $recent->fetch_assoc()): ?>
            <tr>
                <td><?php echo (int)$g['id']; ?></td>
                <td><?php echo htmlspecialchars($g['student_username']); ?></td>
                <td>
                    <?php
                        $sub = $g['subject_name'] . (!empty($g['subject_code']) ? " ({$g['subject_code']})" : "");
                        echo htmlspecialchars($sub);
                    ?>
                </td>
                <td><?php echo htmlspecialchars($g['assessment']); ?></td>
                <td><?php echo htmlspecialchars($g['score'] . " / " . $g['max_score']); ?></td>
                <td><?php echo htmlspecialchars($g['graded_at']); ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
<?php $stmt->close(); ?>