<?php
require_once 'config.php';

if (isset($_SESSION["user_loggedin"]) && $_SESSION["user_loggedin"] === true) {
    if (($_SESSION["role"] ?? '') === 'teacher') {
        header("location: teacher_dashboard.php");
    } else {
        header("location: student_dashboard.php");
    }
    exit;
}

$message = "";
// Remember which tab was active on error
$active_tab = $_POST['login_type'] ?? $_GET['type'] ?? 'student';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username   = trim($_POST['username']);
    $password   = $_POST['password'];
    $login_type = $_POST['login_type'] ?? 'student'; // 'student' or 'teacher'
    $active_tab = $login_type;

    $sql  = "SELECT id, username, password, role FROM users WHERE username = ? AND role = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $login_type);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $db_username, $hashed_password, $role);
        if ($stmt->fetch()) {
            if (password_verify($password, $hashed_password)) {
                $_SESSION["user_loggedin"]   = true;
                $_SESSION["user_id"]         = $id;
                $_SESSION["user_username"]   = $db_username;
                $_SESSION["role"]            = $role;

                if ($role === 'teacher') {
                    header("location: teacher_dashboard.php");
                } else {
                    header("location: student_dashboard.php");
                }
                exit;
            } else {
                $message = "Incorrect password. Please try again.";
            }
        }
    } else {
        $message = "No " . ucfirst($login_type) . " account found with that username.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login | Legit Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f0f2f5;
        }

        /* Navbar */
        .navbar {
            background: #1a1a2e;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar .brand { color: white; font-size: 1.1rem; font-weight: 800; letter-spacing: 1px; }
        .navbar .brand span { color: #a0c4ff; }
        .navbar a { color: #a0c4ff; text-decoration: none; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }

        /* Page wrapper */
        .page {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        /* Login box */
        .login-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.11);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        /* Tab header */
        .tabs {
            display: flex;
        }
        .tab {
            flex: 1;
            padding: 18px 10px;
            text-align: center;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            background: #f0f2f5;
            color: #888;
            transition: all 0.2s;
            letter-spacing: 0.3px;
        }
        .tab .tab-icon { font-size: 1.3rem; display: block; margin-bottom: 4px; }

        /* Student tab active — blue */
        .tab.student-tab.active {
            background: #1a1a2e;
            color: white;
        }
        /* Teacher tab active — green */
        .tab.teacher-tab.active {
            background: #1a3a2a;
            color: white;
        }
        .tab:not(.active):hover { background: #e8eef8; color: #333; }

        /* Form area */
        .form-area { padding: 32px 36px 36px; }

        .form-title {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .form-title.student { color: #1a1a2e; }
        .form-title.teacher { color: #1a3a2a; }

        .form-subtitle { font-size: 0.88rem; color: #888; margin-bottom: 24px; }

        /* Error message */
        .msg-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px 14px;
            border-radius: 7px;
            font-size: 0.88rem;
            margin-bottom: 18px;
            font-weight: 600;
        }

        label {
            display: block;
            font-weight: 700;
            font-size: 0.85rem;
            color: #444;
            margin-bottom: 6px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            margin-bottom: 18px;
            background: #fafafa;
            transition: border-color 0.15s, background 0.15s;
            font-family: inherit;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            background: white;
        }

        /* Focus colour per role */
        .student-form input:focus { border-color: #1a1a2e; }
        .teacher-form input:focus { border-color: #1a3a2a; }

        /* Login button */
        .btn-login {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: opacity 0.15s;
            margin-top: 4px;
        }
        .btn-login:hover { opacity: 0.88; }
        .student-form .btn-login { background: #1a1a2e; }
        .teacher-form .btn-login { background: #1a3a2a; }

        /* Role indicator strip */
        .role-strip {
            height: 4px;
            width: 100%;
            margin-bottom: 28px;
            border-radius: 2px;
        }
        .student .role-strip { background: linear-gradient(90deg, #1a1a2e, #4a6fa5); }
        .teacher .role-strip { background: linear-gradient(90deg, #1a3a2a, #2d8a4e); }

        /* Bottom link */
        .bottom-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.88rem;
            color: #888;
        }
        .bottom-link a { font-weight: 700; text-decoration: none; }
        .student .bottom-link a { color: #1a1a2e; }
        .teacher .bottom-link a { color: #1a3a2a; }

        /* Hidden form panels */
        .form-panel { display: none; }
        .form-panel.active { display: block; }

        /* Footer */
        .footer { background: #1a1a2e; color: #a0c4ff; padding: 22px 40px; font-size: 0.88rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .footer p { margin-top: 3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">LEGIT <span>PORTAL</span></div>
    <a href="index.php">← Back to Home</a>
</div>

<div class="page">
    <div class="login-box">

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab student-tab <?php echo ($active_tab === 'student') ? 'active' : ''; ?>"
                    onclick="switchTab('student')">
                <span class="tab-icon">🎓</span>
                Student
            </button>
            <button class="tab teacher-tab <?php echo ($active_tab === 'teacher') ? 'active' : ''; ?>"
                    onclick="switchTab('teacher')">
                <span class="tab-icon">🧑‍🏫</span>
                Teacher
            </button>
        </div>

        <!-- Student login form -->
        <div class="form-panel student <?php echo ($active_tab === 'student') ? 'active' : ''; ?>" id="panel-student">
            <div class="form-area student-form">
                <div class="role-strip"></div>
                <div class="form-title student">Student Login</div>
                <div class="form-subtitle">Access your grades, attendance, and assignments</div>

                <?php if ($message && $active_tab === 'student'): ?>
                    <div class="msg-error"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="login_type" value="student">
                    <label for="s_username">Username</label>
                    <input type="text" id="s_username" name="username" placeholder="Enter your username" required>
                    <label for="s_password">Password</label>
                    <input type="password" id="s_password" name="password" placeholder="Enter your password" required>
                    <button type="submit" class="btn-login">Login as Student</button>
                </form>

                <div class="bottom-link student">
                    <a href="index.php">← Back to Home</a>
                    &nbsp;|&nbsp;
                    <a href="admin_login.php">Admin Login</a>
                </div>
            </div>
        </div>

        <!-- Teacher login form -->
        <div class="form-panel teacher <?php echo ($active_tab === 'teacher') ? 'active' : ''; ?>" id="panel-teacher">
            <div class="form-area teacher-form">
                <div class="role-strip"></div>
                <div class="form-title teacher">Teacher Login</div>
                <div class="form-subtitle">Access your subjects, students, and attendance tools</div>

                <?php if ($message && $active_tab === 'teacher'): ?>
                    <div class="msg-error"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="login_type" value="teacher">
                    <label for="t_username">Username</label>
                    <input type="text" id="t_username" name="username" placeholder="Enter your username" required>
                    <label for="t_password">Password</label>
                    <input type="password" id="t_password" name="password" placeholder="Enter your password" required>
                    <button type="submit" class="btn-login">Login as Teacher</button>
                </form>

                <div class="bottom-link teacher">
                    <a href="index.php">← Back to Home</a>
                    &nbsp;|&nbsp;
                    <a href="admin_login.php">Admin Login</a>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="footer">
    <div>
        <p>• Phone: +37000000001</p>
        <p>• Email: info@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#555;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

<script>
function switchTab(role) {
    // Update tab buttons
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelector('.' + role + '-tab').classList.add('active');

    // Update form panels
    document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + role).classList.add('active');
}
</script>

</body>
</html>