<?php
require_once 'config.php';

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to user login page
header("location: user_login.php");
exit;
?>