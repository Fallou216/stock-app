<?php
$conn = new mysqli("localhost", "root", "", "stock_app");

if ($conn->connect_error) {
    die("Erreur : " . $conn->connect_error);
}

// ============================================
// FONCTIONS DE GESTION DES RÔLES ET SESSIONS
// ============================================

// Vérifie si l'utilisateur est admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Vérifie si l'utilisateur est employé
function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
}

// Bloque l'accès si pas connecté → redirige vers login
function requireLogin() {
    if (!isset($_SESSION['user'])) {
        header("Location: /stock-app/auth/login.php");
        exit();
    }
}

// Bloque l'accès si pas admin → redirige vers dashboard
function requireAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: /stock-app/dashboard.php");
        exit();
    }
}

// Retourne la photo de profil ou une initiale
function getAvatar($photo, $username, $size = 40) {
    if (!empty($photo)) {
        return "<img src='/stock-app/uploads/" . htmlspecialchars($photo) . "' 
                style='width:{$size}px;height:{$size}px;border-radius:12px;object-fit:cover;border:3px solid #ede9fe;'
                alt='Photo'>";
    } else {
        $initiale = strtoupper(substr($username, 0, 1));
        return "<div style='width:{$size}px;height:{$size}px;border-radius:12px;
                background:linear-gradient(135deg,#6366f1,#818cf8);
                display:flex;align-items:center;justify-content:center;
                color:white;font-weight:700;font-size:" . ($size/2.8) . "px;
                font-family:Plus Jakarta Sans,sans-serif;'>
                {$initiale}</div>";
    }
}
?>