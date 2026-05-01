<?php
require_once 'config.php';
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("location: admin_login.php");
exit;
?>