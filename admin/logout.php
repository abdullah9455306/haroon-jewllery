<?php
session_start();

// Destroy all admin session variables
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_role']);
unset($_SESSION['is_super_admin']);

// Redirect to admin login page
header('Location: index.php');
exit;
?>