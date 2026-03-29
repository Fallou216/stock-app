<?php
// ============================================
// CONNEXION BASE DE DONNÉES
// ============================================
$conn = new mysqli("localhost", "root", "", "stock_app");

if($conn->connect_error){
    die("Erreur de connexion : " . $conn->connect_error);
}

$conn->set_charset('utf8');

// ============================================
// FONCTIONS RÔLES & SESSIONS
// ============================================

if(!function_exists('isAdmin')){
    function isAdmin(){
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if(!function_exists('isEmployee')){
    function isEmployee(){
        return isset($_SESSION['role']) && $_SESSION['role'] === 'employee';
    }
}

if(!function_exists('requireLogin')){
    function requireLogin(){
        if(!isset($_SESSION['user'])){
            header("Location: /stock-app/auth/login.php");
            exit();
        }
    }
}

if(!function_exists('requireAdmin')){
    function requireAdmin(){
        if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'){
            header("Location: /stock-app/dashboard.php");
            exit();
        }
    }
}

if(!function_exists('getAvatar')){
    function getAvatar($photo, $username, $size = 40){
        if(!empty($photo)){
            return "<img src='/stock-app/uploads/" . htmlspecialchars($photo) . "'
                    style='width:{$size}px;height:{$size}px;border-radius:12px;
                           object-fit:cover;border:3px solid #ede9fe;'
                    alt='Photo'>";
        } else {
            $initiale = strtoupper(substr($username, 0, 1));
            $fontSize = round($size / 2.8);
            return "<div style='width:{$size}px;height:{$size}px;border-radius:12px;
                    background:linear-gradient(135deg,#6366f1,#818cf8);
                    display:flex;align-items:center;justify-content:center;
                    color:white;font-weight:700;font-size:{$fontSize}px;
                    font-family:Plus Jakarta Sans,sans-serif;'>
                    {$initiale}</div>";
        }
    }
}

// ============================================
// ❌ PAS D'APPEL À check.php ICI
// check.php est appelé manuellement dans chaque
// page via include_once 'notifications/check.php'
// ============================================
?>