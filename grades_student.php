<?php
require_once 'config.php';

if (!isset($_SESSION["user_loggedin"]) || $_SESSION["user_loggedin"] !== true) {
    header("location: user_login.php");
    exit;
}
if (($_SESSION["role"] ?? '') !== 'student') {
    header("location: teacher_dashboard.php");
    exit;
}

$student_id = (int)$_SESSION["user_id"];
$subject_id = (int)($_GET["subject_id"] ?? 0);

// Subjects student is enrolled in (for filter dropdown)
$stmt = $conn->prepare(
    "SELECT s.id, s.subject_name, s.subject_code
     FROM enrollments e
     JOIN subjects s ON s.id = e.subject_id
     WHERE e.student_id = ?
     ORDER BY s.subject_name ASC"
);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$subjects_res = $stmt->get_result();
$subjects = [];
while ($row = $subjects_res->fetch_assoc()) $subjects[] = $row;
$stmt->close();

// Load grades
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT g.assessment, g.score, g.max_score, g.graded_at, s.subject_name, s.subject_code
         FROM grades g
         JOIN subjects s ON s.id = g.subject_id
         WHERE g.student_id = ? AND g.subject_id = ?
         ORDER BY g.graded_at DESC"
    );
    $stmt->bind_param("ii", $student_id, $subject_id);
} else {
    $stmt = $conn->prepare(
        "SELECT g.assessment, g.score, g.max_score, g.graded_at, s.subject_name, s.subject_code
         FROM grades g
         JOIN subjects s ON s.id = g.subject_id
         WHERE g.student_id = ?
         ORDER BY g.graded_at DESC"
    );
    $stmt->bind_param("i", $student_id);
}
$stmt->execute();
$grades_res = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Grades</title>
  <style>
    body { font-family: Arial; padding: 30px; }
    .box { max-width: 950px; margin: 0 auto; }
    table { width: 100%; border-collapse: collapse; margin-top: 18px; }
    th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
    th { background: #f4f4f4; }
    select { padding: 10px; width: 100%; max-width: 450px; }
    .nav a { margin-right: 12px; }
  </style>
</head>
<body>
<div class="box">
  <div class="nav">
    <a href="student_dashboard.php">Student Dashboard</a>
    <a href="index.php">Home</a>
  </div>

  <h1>My Grades</h1>

  <form method="get">
    <label>Filter by Subject (optional)</label><br>
    <select name="subject_id" onchange="this.form.submit()">
      <option value="0">All Subjects</option>
      <?php foreach ($subjects as $s): ?>
        <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? "selected" : ""; ?>>
          <?php
            $label = $s['subject_name'] . (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "");
            echo htmlspecialchars($label);
          ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <table>
    <tr>
      <th>Subject</th>
      <th>Assessment</th>
      <th>Score</th>
      <th>Date</th>
    </tr>
    <?php while ($row = $grades_res->fetch_assoc()): ?>
      <tr>
        <td>
          <?php
            $sub = $row['subject_name'] . (!empty($row['subject_code']) ? " ({$row['subject_code']})" : "");
            echo htmlspecialchars($sub);
          ?>
        </td>
        <td><?php echo htmlspecialchars($row['assessment']); ?></td>
        <td><?php echo htmlspecialchars($row['score'] . " / " . $row['max_score']); ?></td>
        <td><?php echo htmlspecialchars($row['graded_at']); ?></td>
      </tr>
    <?php endwhile; ?>
  </table>

  <p style="color:gray;margin-top:12px;">
    If you don’t see grades here, confirm you are enrolled in the subject and the teacher saved grades for you.
  </p>
</div>
</body>
</html>
<?php $stmt->close(); ?>