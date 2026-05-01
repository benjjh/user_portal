<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$subject_id = (int)($_GET["subject_id"] ?? 0);

// Load subjects
$subjects = [];
$sql = "SELECT s.id, s.subject_name, s.subject_code, u.username AS teacher_username
        FROM subjects s
        JOIN users u ON u.id = s.teacher_id
        ORDER BY s.subject_name ASC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $subjects[] = $row;
}

// Grades query
if ($subject_id > 0) {
    $stmt = $conn->prepare(
        "SELECT g.id, g.assessment, g.score, g.max_score, g.graded_at,
                st.username AS student_username,
                s.subject_name, s.subject_code,
                u.username AS graded_by_username
         FROM grades g
         JOIN users st ON st.id = g.student_id
         JOIN subjects s ON s.id = g.subject_id
         LEFT JOIN users u ON u.id = g.graded_by
         WHERE g.subject_id = ?
         ORDER BY g.graded_at DESC, st.username ASC"
    );
    $stmt->bind_param("i", $subject_id);
} else {
    $stmt = $conn->prepare(
        "SELECT g.id, g.assessment, g.score, g.max_score, g.graded_at,
                st.username AS student_username,
                s.subject_name, s.subject_code,
                u.username AS graded_by_username
         FROM grades g
         JOIN users st ON st.id = g.student_id
         JOIN subjects s ON s.id = g.subject_id
         LEFT JOIN users u ON u.id = g.graded_by
         ORDER BY g.graded_at DESC, st.username ASC"
    );
}
$stmt->execute();
$grades = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Grades</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; }
        th { background: #f4f4f4; }
        select, button { padding: 8px; }
        .top a { margin-right: 12px; }
    </style>
</head>
<body>
    <div class="top">
        <a href="admin_dashboard.php">Back to Admin Dashboard</a>
        <a href="index.php">Home</a>
    </div>

    <h1>View Grade Records</h1>

    <form method="get">
        <label>Filter by Subject</label>
        <select name="subject_id">
            <option value="0">All Subjects</option>
            <?php foreach ($subjects as $s): ?>
                <option value="<?php echo (int)$s['id']; ?>" <?php echo ((int)$s['id'] === $subject_id) ? 'selected' : ''; ?>>
                    <?php
                    echo htmlspecialchars(
                        $s['subject_name'] .
                        (!empty($s['subject_code']) ? " ({$s['subject_code']})" : "") .
                        " - " . $s['teacher_username']
                    );
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>Student</th>
            <th>Subject</th>
            <th>Assessment</th>
            <th>Score</th>
            <th>Date</th>
            <th>Recorded By</th>
        </tr>
        <?php while ($row = $grades->fetch_assoc()): ?>
        <tr>
            <td><?php echo (int)$row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['student_username']); ?></td>
            <td>
                <?php
                echo htmlspecialchars(
                    $row['subject_name'] .
                    (!empty($row['subject_code']) ? " ({$row['subject_code']})" : "")
                );
                ?>
            </td>
            <td><?php echo htmlspecialchars($row['assessment']); ?></td>
            <td><?php echo htmlspecialchars($row['score'] . " / " . $row['max_score']); ?></td>
            <td><?php echo htmlspecialchars($row['graded_at']); ?></td>
            <td><?php echo htmlspecialchars($row['graded_by_username'] ?? 'Admin'); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
<?php $stmt->close(); ?>