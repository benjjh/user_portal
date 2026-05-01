<?php
require_once 'config.php';

// If user is already logged in, redirect by role
if (isset($_SESSION["user_loggedin"]) && $_SESSION["user_loggedin"] === true) {
    if (($_SESSION["role"] ?? '') === 'teacher') {
        header("location: teacher_dashboard.php");
    } else {
        header("location: student_dashboard.php");
    }
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $db_username, $hashed_password, $role);
        if ($stmt->fetch()) {
            if (password_verify($password, $hashed_password)) {
                $_SESSION["user_loggedin"] = true;
                $_SESSION["user_id"] = $id;
                $_SESSION["user_username"] = $db_username;
                $_SESSION["role"] = $role;

                if ($role === 'teacher') {
                    header("location: teacher_dashboard.php");
                } else {
                    header("location: student_dashboard.php");
                }
                exit;
            } else {
                $message = "Invalid username or password.";
            }
        }
    } else {
        $message = "Invalid username or password.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    <p><?php echo htmlspecialchars($message); ?></p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>

        <input type="submit" value="Login">
    </form>

    <p>Admin Login: <a href="admin_login.php">Click here</a></p>
</body>
</html>
<?php $conn->close(); ?>