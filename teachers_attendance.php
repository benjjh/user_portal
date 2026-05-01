<?php
require_once 'config.php';

if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: admin_login.php");
    exit;
}

$message = "";
$admin_id = (int)$_SESSION["admin_id"];

// Load all teachers
$teachers = [];
$res = $conn->query("SELECT id, username, name, surname FROM users WHERE role = 'teacher' ORDER BY username ASC");
while ($row = $res->fetch_assoc()) $teachers[] = $row;

$attendance_date = date("Y-m-d");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $attendance_date  = $_POST["attendance_date"] ?? date("Y-m-d");
    $attendance_data  = $_POST["attendance"] ?? [];

    foreach ($attendance_data as $tid => $status) {
        $tid = (int)$tid;
        $stmt = $conn->prepare(
            "INSERT INTO teacher_attendance (teacher_id, attendance_date, status, marked_by_admin)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by_admin = VALUES(marked_by_admin)"
        );
        $stmt->bind_param("issi", $tid, $attendance_date, $status, $admin_id);
        $stmt->execute();
        $stmt->close();
    }
    $message = "Teacher attendance saved for " . htmlspecialchars($attendance_date) . ".";
} else {
    $attendance_date = $_GET["date"] ?? date("Y-m-d");
}

// Prefill existing records
$existing = [];
$stmt = $conn->prepare("SELECT teacher_id, status FROM teacher_attendance WHERE attendance_date = ?");
$stmt->bind_param("s", $attendance_date);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $existing[(int)$row["teacher_id"]] = $row["status"];
}
$stmt->close();

function sel2($a, $b) { return $a === $b ? "selected" : ""; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Attendance | Admin</title>
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
        input[type="date"], select { width: 100%; padding: 10px 14px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem; margin-bottom: 18px; background: #fafafa; }
        select:focus, input:focus { outline: none; border-color: #4a90d9; background: white; }
        .btn { padding: 11px 24px; background: #1a1a2e; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 0.95rem; font-weight: 600; }
        .btn:hover { background: #2d2d5e; }
        .btn-save { background: #28a745; }
        .btn-save:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.92rem; }
        th { background: #f4f6fb; color: #444; font-weight: 700; }
        tr:hover td { background: #f9fafb; }
        td select { margin-bottom: 0; }
        .form-row { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
        .form-row > div { flex: 1; min-width: 180px; }
        .tab-links { display: flex; gap: 12px; margin-bottom: 24px; }
        .tab-link { padding: 10px 22px; border-radius: 6px; text-decoration: none; font-weight: 600; background: #e9ecef; color: #333; }
        .tab-link.active { background: #1a1a2e; color: white; }
    </style>
</head>
<body>
<div class="navbar">
    <strong>Admin Panel — Teacher Attendance</strong>
    <div>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h1>Teacher Attendance</h1>
    <p style="color:#666;margin-bottom:20px;">Mark attendance for all teachers on a given date.</p>

    <div class="tab-links">
        <a href="attendance_admin.php" class="tab-link">Student Attendance</a>
        <a href="teacher_attendance_admin.php" class="tab-link active">Teacher Attendance</a>
    </div>

    <?php if ($message): ?>
        <div class="msg"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="get">
            <div class="form-row">
                <div>
                    <label>Select Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($attendance_date); ?>" required>
                </div>
                <div style="padding-bottom:18px;">
                    <button type="submit" class="btn">Load Teachers</button>
                </div>
            </div>
        </form>
    </div>

    <?php if (count($teachers) === 0): ?>
        <p style="color:#666;">No teachers found in the system.</p>
    <?php else: ?>
    <div class="card">
        <h2 style="margin-bottom:16px;font-size:1.2rem;">Teachers — <?php echo htmlspecialchars($attendance_date); ?></h2>
        <form method="post">
            <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($attendance_date); ?>">
            <table>
                <tr>
                    <th>Teacher</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($teachers as $t):
                    $tid     = (int)$t["id"];
                    $full    = trim(($t['name'] ?? '') . ' ' . ($t['surname'] ?? ''));
                    $label   = $t["username"] . ($full ? " ($full)" : "");
                    $current = $existing[$tid] ?? "present";
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($label); ?></td>
                    <td>
                        <select name="attendance[<?php echo $tid; ?>]">
                            <option value="present" <?php echo sel2($current, "present"); ?>>Present</option>
                            <option value="absent"  <?php echo sel2($current, "absent");  ?>>Absent</option>
                            <option value="late"    <?php echo sel2($current, "late");    ?>>Late</option>
                            <option value="excused" <?php echo sel2($current, "excused"); ?>>Excused</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <br>
            <button type="submit" class="btn btn-save">Save Teacher Attendance</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- View recent teacher attendance -->
    <div class="card">
        <h2 style="margin-bottom:16px;font-size:1.2rem;">Recent Teacher Attendance Records</h2>
        <table>
            <tr>
                <th>Date</th>
                <th>Teacher</th>
                <th>Status</th>
            </tr>
            <?php
            $sql_ta = "SELECT ta.attendance_date, ta.status, u.username, u.name, u.surname
                       FROM teacher_attendance ta
                       JOIN users u ON u.id = ta.teacher_id
                       ORDER BY ta.attendance_date DESC, u.username ASC
                       LIMIT 50";
            $res_ta = $conn->query($sql_ta);
            while ($row = $res_ta->fetch_assoc()):
                $full = trim(($row['name'] ?? '') . ' ' . ($row['surname'] ?? ''));
                $label = $row['username'] . ($full ? " ($full)" : "");
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['attendance_date']); ?></td>
                <td><?php echo htmlspecialchars($label); ?></td>
                <td><?php echo ucfirst(htmlspecialchars($row['status'])); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>