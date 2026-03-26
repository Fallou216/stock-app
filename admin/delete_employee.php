<?php
session_start();
require_once('../config/db.php');
requireLogin();
requireAdmin();

$id = intval($_GET['id']);
$conn->query("DELETE FROM users WHERE id=$id AND role='employee'");
header("Location: create_employee.php?msg=deleted");
exit();