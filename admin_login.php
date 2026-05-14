<?php
require_once 'config.php';

if (isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true) {
    header("location: admin_dashboard.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $sql  = "SELECT id, username, password FROM admins WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $username, $hashed_password);
        if ($stmt->fetch()) {
            if (password_verify($password, $hashed_password)) {
                $_SESSION["admin_loggedin"]  = true;
                $_SESSION["admin_id"]        = $id;
                $_SESSION["admin_username"]  = $username;
                header("location: admin_dashboard.php");
                exit;
            } else {
                $message = "Incorrect password. Please try again.";
            }
        }
    } else {
        $message = "No admin account found with that username.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Legit Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f5f0f0;
        }

        /* Navbar — maroon theme */
        .navbar {
            background: #4a0f0f;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar .brand { color: white; font-size: 1.1rem; font-weight: 800; letter-spacing: 1px; }
        .navbar .brand span { color: #ffb3b3; }
        .navbar a { color: #ffb3b3; text-decoration: none; font-weight: 600; font-size: 0.92rem; }
        .navbar a:hover { color: white; }

        /* Page */
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
            box-shadow: 0 8px 32px rgba(74,15,15,0.13);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        /* Top banner */
        .login-banner {
            background: linear-gradient(135deg, #4a0f0f 0%, #7b1f1f 100%);
            padding: 32px 36px 28px;
            text-align: center;
            color: white;
        }
        .banner-icon {
            width: 70px;
            height: 70px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 14px;
            border: 2px solid rgba(255,255,255,0.3);
        }
        .banner-title { font-size: 1.4rem; font-weight: 800; letter-spacing: 0.5px; }
        .banner-sub { font-size: 0.88rem; color: #ffb3b3; margin-top: 5px; }

        /* Form area */
        .form-area { padding: 32px 36px 36px; }

        /* Role strip */
        .role-strip {
            height: 4px;
            background: linear-gradient(90deg, #4a0f0f, #c0392b);
            border-radius: 2px;
            margin-bottom: 24px;
        }

        /* Error */
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

        /* Warning badge */
        .admin-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
            border-radius: 7px;
            padding: 9px 14px;
            font-size: 0.83rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        label { display: block; font-weight: 700; font-size: 0.85rem; color: #444; margin-bottom: 6px; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            margin-bottom: 18px;
            background: #fafafa;
            font-family: inherit;
            transition: border-color 0.15s, background 0.15s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #7b1f1f;
            background: white;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #4a0f0f, #7b1f1f);
            color: white;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: opacity 0.15s;
            margin-top: 4px;
        }
        .btn-login:hover { opacity: 0.88; }

        .bottom-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.88rem;
            color: #888;
        }
        .bottom-link a { color: #7b1f1f; font-weight: 700; text-decoration: none; }
        .bottom-link a:hover { text-decoration: underline; }

        /* Footer */
        .footer {
            background: #4a0f0f;
            color: #ffb3b3;
            padding: 22px 40px;
            font-size: 0.88rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .footer p { margin-top: 3px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="brand">LEGIT <span>PORTAL</span> — Admin</div>
    <a href="index.php">← Back to Home</a>
</div>

<div class="page">
    <div class="login-box">

        <!-- Banner -->
        <div class="login-banner">
            <div class="banner-icon">🛡️</div>
            <div class="banner-title">Administrator Login</div>
            <div class="banner-sub">Restricted access — authorised personnel only</div>
        </div>

        <!-- Form -->
        <div class="form-area">
            <div class="role-strip"></div>

            <div class="admin-warning">
                ⚠️ This area is for system administrators only. Unauthorised access is prohibited.
            </div>

            <?php if ($message): ?>
                <div class="msg-error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username" placeholder="Enter admin username" required>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <button type="submit" class="btn-login">Login to Admin Panel</button>
            </form>

            <div class="bottom-link">
                Not an admin? <a href="user_login.php">Go to User Login</a>
                &nbsp;|&nbsp;
                <a href="admin_register.php">Register Admin</a>
            </div>
        </div>

    </div>
</div>

<div class="footer">
    <div>
        <p>• Phone: +37000000002</p>
        <p>• Email: admin@portal.com</p>
    </div>
    <div style="align-self:flex-end;color:#8a4a4a;font-size:0.82rem;">&copy; 2026 Legit Portal</div>
</div>

</body>
</html>