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

$sql = "SELECT s.subject_name, s.subject_code, u.username AS teacher_username
        FROM subjects s
        JOIN users u ON u.id = s.teacher_id
        ORDER BY s.id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Subjects</title>
</head>
<body>
    <h1>Subjects</h1>
    <p><a href="student_dashboard.php">Back to Dashboard</a></p>

    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Subject</th>
            <th>Code</th>
            <th>Teacher</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row["subject_name"]); ?></td>
            <td><?php echo htmlspecialchars($row["subject_code"] ?? ""); ?></td>
            <td><?php echo htmlspecialchars($row["teacher_username"]); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>