<?php
session_start();
include('../config/db.php');

// Vérification si connecté
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}

// Vérifier si ID existe
if(isset($_GET['id']) && is_numeric($_GET['id'])){

    $id = $_GET['id'];

    // Requête sécurisée (préparée)
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);

    if($stmt->execute()){
        header("Location: list.php?msg=deleted");
        exit();
    } else {
        echo "Erreur lors de la suppression ❌";
    }

} else {
    echo "ID invalide ⚠️";
}
?>