<?php
require_once 'config.php';

// If admin is already logged in, send them to the dashboard
if (isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true) {
    header("location: admin_dashboard.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, username, password FROM admins WHERE username = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    // Check if a user with that username exists
    if ($stmt->num_rows == 1) {
        $stmt->bind_result($id, $username, $hashed_password);
        if ($stmt->fetch()) {
            // Verify the password (checks the secret code against the stored one)
            if (password_verify($password, $hashed_password)) {
                // Password is correct, so start the session!
                $_SESSION["admin_loggedin"] = true;
                $_SESSION["admin_id"] = $id;
                $_SESSION["admin_username"] = $username;
                
                // Send the admin to their dashboard
                header("location: admin_dashboard.php");
                exit;
            } else {
                $message = "The password you entered was not valid.";
            }
        }
    } else {
        $message = "No account found with that username.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Login</title>
</head>
<body>
    <h2>Admin Login</h2>
    <p><?php echo $message; ?></p>
    
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        
        <input type="submit" value="Login">
    </form>
    <p>Don't have an account? <a href="admin_register.php">Register here</a>.</p>
</body>
</html>
<?php $conn->close(); ?>