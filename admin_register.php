<?php
require_once 'config.php';

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    if (!empty($username) && !empty($password)) {
       
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO admins (username, password) VALUES (?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $hashed_password);

        if ($stmt->execute()) {
            $message = "Administrator registered successfully! You can now log in.";
        } else {
            // Check if the username already exists
            if ($conn->errno == 1062) { // 1062 is the error code for duplicate entry
                 $message = "Error: Username already exists.";
            } else {
                 $message = "Error: Could not register admin. " . $stmt->error;
            }
        }
        $stmt->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Register</title>
</head>
<body>
    <h2>Admin Registration</h2>
    <p><?php echo $message; ?></p>
    
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        
        <input type="submit" value="Register Admin">
    </form>
    <p>Already have an account? <a href="admin_login.php">Log In here</a>.</p>
</body>
</html>
<?php $conn->close(); ?>