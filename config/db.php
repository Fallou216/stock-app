<?php
$conn = new mysqli("localhost", "root", "", "stock_app");

if ($conn->connect_error) {
    die("Erreur : " . $conn->connect_error);
}
?>