<?php
$servername = "localhost";
$dbusername = "root"; 
$dbpassword = "";
$dbname = "portal_db"; 
// http://localhost/user_portal/admin_dashboard.php use this to access the local host server for user and admin login portal 
// i might forget that is why i wrote the link here
// Create connection
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
session_start();
?>