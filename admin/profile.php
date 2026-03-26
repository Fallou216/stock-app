<?php
session_start();
require_once('../config/db.php');
requireLogin();
requireAdmin();

$message = '';
$msgType = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $id = $_SESSION['user_id'];

    // ── Mise à jour du nom et photo ──────────────────
    if(isset($_POST['update_profile'])){
        $username = trim($conn->real_escape_string($_POST['username']));
        $photo    = $_SESSION['photo'];

        if(!empty($_FILES['photo']['name'])){
            $ext      = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','webp'];
            if(in_array($ext, $allowed)){
                $filename = 'user_' . $id . '_' . time() . '.' . $ext;
                if(!is_dir('../uploads')) mkdir('../uploads', 0755, true);
                move_uploaded_file($_FILES['photo']['tmp_name'], '../uploads/' . $filename);
                $photo = $filename;
            }
        }

        $conn->query("UPDATE users SET username='$username', photo='$photo' WHERE id=$id");
        $_SESSION['user']  = $username;
        $_SESSION['photo'] = $photo;
        $message = "Profil mis à jour avec succès !";
        $msgType = "success";
    }

    // ── Mise à jour de l'email ───────────────────────
    if(isset($_POST['update_email'])){
        $newEmail = trim($conn->real_escape_string($_POST['email']));

        // Vérifier si l'email est déjà utilisé par un autre utilisateur
        $check = $conn->query("SELECT id FROM users WHERE email='$newEmail' AND id != $id");
        if($check->num_rows > 0){
            $message = "Cet email est déjà utilisé par un autre compte.";
            $msgType = "error";
        } else {
            $conn->query("UPDATE users SET email='$newEmail' WHERE id=$id");
            $message = "Email mis à jour avec succès !";
            $msgType = "success";
        }
    }

    // ── Mise à jour du mot de passe ──────────────────
    if(isset($_POST['update_password'])){
        $currentPass = $_POST['current_password'];
        $newPass     = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];

        // Récupérer le mot de passe actuel
        $res  = $conn->query("SELECT password FROM users WHERE id=$id");
        $user = $res->fetch_assoc();

        if(!password_verify($currentPass, $user['password'])){
            $message = "Mot de passe actuel incorrect.";
            $msgType = "error";
        } elseif(strlen($newPass) < 6){
            $message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
            $msgType = "error";
        } elseif($newPass !== $confirmPass){
            $message = "Les deux nouveaux mots de passe ne correspondent pas.";
            $msgType = "error";
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hash' WHERE id=$id");
            $message = "Mot de passe modifié avec succès !";
            $msgType = "success";
        }
    }
}

// Récupérer les infos admin
$admin = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    *,
    *::before,
    *::after {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    :root {
        --sidebar-w: 260px;
        --dark: #0f172a;
        --gray: #64748b;
        --border: #e2e8f0;
        --primary: #6366f1;
        --green: #10b981;
        --red: #ef4444;
        --light: #f8fafc;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f0f4ff;
        min-height: 100vh;
    }

    /* SIDEBAR */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: var(--sidebar-w);
        height: 100vh;
        background: var(--dark);
        display: flex;
        flex-direction: column;
        z-index: 100;
        overflow: hidden;
    }

    .sidebar::before {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(99, 102, 241, 0.3), transparent);
        top: -60px;
        right: -60px;
        border-radius: 50%;
        pointer-events: none;
    }

    .sidebar-brand {
        padding: 28px 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .brand-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, var(--primary), #818cf8);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 10px;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
    }

    .sidebar-brand h4 {
        color: white;
        font-size: 16px;
        font-weight: 700;
        margin: 0;
    }

    .sidebar-brand span {
        color: rgba(255, 255, 255, 0.4);
        font-size: 11px;
    }

    .sidebar-profile {
        margin: 0 12px 14px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.05));
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 14px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        z-index: 1;
    }

    .sidebar-profile img {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, 0.2);
        flex-shrink: 0;
    }

    .sidebar-avatar-placeholder {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 15px;
        flex-shrink: 0;
    }

    .sidebar-profile-name {
        color: white;
        font-size: 12px;
        font-weight: 700;
    }

    .sidebar-profile-role {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 20px;
        margin-top: 3px;
        display: inline-block;
        background: rgba(99, 102, 241, 0.25);
        color: #a5b4fc;
    }

    .sidebar-nav {
        flex: 1;
        padding: 8px 12px;
        overflow-y: auto;
    }

    .nav-section {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.5px;
        color: rgba(255, 255, 255, 0.25);
        text-transform: uppercase;
        padding: 0 12px;
        margin: 14px 0 6px;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 14px;
        border-radius: 10px;
        color: rgba(255, 255, 255, 0.55);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        margin-bottom: 2px;
    }

    .sidebar-nav a i {
        font-size: 17px;
        width: 20px;
        text-align: center;
    }

    .sidebar-nav a:hover {
        background: rgba(255, 255, 255, 0.07);
        color: white;
    }

    .sidebar-nav a.active {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(99, 102, 241, 0.1));
        color: #a5b4fc;
        border: 1px solid rgba(99, 102, 241, 0.3);
    }

    .sidebar-nav a.active i {
        color: var(--primary);
    }

    .admin-only-badge {
        font-size: 9px;
        font-weight: 700;
        background: rgba(99, 102, 241, 0.25);
        color: #a5b4fc;
        padding: 1px 6px;
        border-radius: 10px;
        margin-left: auto;
    }

    .sidebar-footer {
        padding: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 10px;
        color: rgba(255, 255, 255, 0.4);
        text-decoration: none;
        font-size: 13px;
        transition: all 0.2s;
    }

    .sidebar-footer a:hover {
        background: rgba(239, 68, 68, 0.1);
        color: #fca5a5;
    }

    /* CONTENT */
    .content {
        margin-left: var(--sidebar-w);
        padding: 32px;
    }

    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
    }

    .topbar h5 {
        font-size: 22px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .topbar p {
        font-size: 13px;
        color: var(--gray);
        margin: 3px 0 0;
    }

    /* PROFILE HERO */
    .profile-hero {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        border-radius: 20px;
        padding: 28px 32px;
        display: flex;
        align-items: center;
        gap: 24px;
        margin-bottom: 28px;
        position: relative;
        overflow: hidden;
    }

    .profile-hero::before {
        content: '';
        position: absolute;
        width: 250px;
        height: 250px;
        background: rgba(255, 255, 255, 0.06);
        border-radius: 50%;
        right: -60px;
        top: -60px;
    }

    .hero-photo-wrap {
        position: relative;
        cursor: pointer;
        flex-shrink: 0;
    }

    .hero-photo,
    .hero-placeholder {
        width: 90px;
        height: 90px;
        border-radius: 20px;
        object-fit: cover;
        border: 4px solid rgba(255, 255, 255, 0.3);
    }

    .hero-placeholder {
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 36px;
        font-weight: 800;
        backdrop-filter: blur(10px);
    }

    .hero-edit-btn {
        position: absolute;
        bottom: -4px;
        right: -4px;
        width: 26px;
        height: 26px;
        background: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        color: var(--primary);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .hero-info h3 {
        color: white;
        font-size: 22px;
        font-weight: 800;
        margin: 0;
    }

    .hero-info p {
        color: rgba(255, 255, 255, 0.7);
        font-size: 13px;
        margin: 4px 0 10px;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    /* TABS */
    .profile-tabs {
        display: flex;
        gap: 4px;
        background: white;
        padding: 6px;
        border-radius: 14px;
        margin-bottom: 24px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border);
    }

    .tab-btn {
        flex: 1;
        padding: 10px 16px;
        border: none;
        border-radius: 10px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: var(--gray);
        background: transparent;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
    }

    .tab-btn.active {
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .tab-btn:hover:not(.active) {
        background: #f1f5f9;
        color: var(--dark);
    }

    /* TAB CONTENT */
    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* SECTION CARD */
    .section-card {
        background: white;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .section-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        padding-bottom: 18px;
        border-bottom: 2px solid #f1f5f9;
    }

    .section-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .section-icon.purple {
        background: #ede9fe;
    }

    .section-icon.blue {
        background: #dbeafe;
    }

    .section-icon.red {
        background: #fee2e2;
    }

    .section-card-header h4 {
        font-size: 17px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .section-card-header p {
        font-size: 13px;
        color: var(--gray);
        margin: 2px 0 0;
    }

    /* FORM */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 8px;
    }

    .lbl-icon {
        width: 22px;
        height: 22px;
        background: #ede9fe;
        color: var(--primary);
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }

    .lbl-icon.blue {
        background: #dbeafe;
        color: #3b82f6;
    }

    .lbl-icon.red {
        background: #fee2e2;
        color: var(--red);
    }

    .lbl-icon.green {
        background: #d1fae5;
        color: var(--green);
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap input {
        width: 100%;
        padding: 12px 16px 12px 44px;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        color: var(--dark);
        background: #fafbff;
        outline: none;
        transition: all 0.25s;
    }

    .input-wrap input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
    }

    .input-wrap input:disabled {
        background: #f1f5f9;
        color: var(--gray);
        cursor: not-allowed;
    }

    .input-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        color: #94a3b8;
        pointer-events: none;
    }

    .input-wrap:focus-within .input-icon {
        color: var(--primary);
    }

    .input-hint {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* PASSWORD STRENGTH */
    .strength-bar-wrap {
        height: 4px;
        background: var(--border);
        border-radius: 4px;
        margin-top: 8px;
        overflow: hidden;
    }

    .strength-bar {
        height: 100%;
        width: 0%;
        border-radius: 4px;
        transition: width 0.3s, background 0.3s;
    }

    .strength-label {
        font-size: 11px;
        color: var(--gray);
        margin-top: 4px;
    }

    /* BOUTONS */
    .btn-save {
        padding: 13px 28px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        border: none;
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    }

    .btn-save.green {
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .btn-save.green:hover {
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-save.red {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-save.red:hover {
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    }

    /* ALERT */
    .alert-msg {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 18px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 24px;
        animation: slideDown 0.3s ease;
    }

    .alert-msg i {
        font-size: 18px;
        flex-shrink: 0;
    }

    .alert-msg.success {
        background: #f0fdf4;
        color: #16a34a;
        border: 1.5px solid #bbf7d0;
    }

    .alert-msg.error {
        background: #fef2f2;
        color: #dc2626;
        border: 1.5px solid #fecaca;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* PHOTO UPLOAD */
    .photo-upload-area {
        border: 2px dashed var(--border);
        border-radius: 14px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: #fafbff;
        margin-bottom: 20px;
    }

    .photo-upload-area:hover {
        border-color: var(--primary);
        background: #eef2ff;
    }

    .photo-upload-area img {
        width: 80px;
        height: 80px;
        border-radius: 16px;
        object-fit: cover;
        border: 3px solid #ede9fe;
        margin-bottom: 10px;
    }

    .photo-placeholder-upload {
        width: 80px;
        height: 80px;
        border-radius: 16px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 32px;
        font-weight: 700;
        margin: 0 auto 10px;
    }

    .photo-upload-area strong {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: var(--dark);
    }

    .photo-upload-area span {
        font-size: 12px;
        color: var(--gray);
    }
    </style>
</head>

<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">📦</div>
            <h4>Stock App</h4>
            <span>Administration</span>
        </div>

        <div class="sidebar-profile">
            <?php if(!empty($admin['photo'])): ?>
            <img src="../uploads/<?= htmlspecialchars($admin['photo']) ?>" alt="Photo">
            <?php else: ?>
            <div class="sidebar-avatar-placeholder">
                <?= strtoupper(substr($_SESSION['user'], 0, 1)) ?>
            </div>
            <?php endif; ?>
            <div>
                <div class="sidebar-profile-name"><?= htmlspecialchars($_SESSION['user']) ?></div>
                <div class="sidebar-profile-role">👑 Administrateur</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Principal</div>
            <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <div class="nav-section">Inventaire</div>
            <a href="../products/list.php"><i class="bi bi-box-seam"></i> Produits</a>
            <a href="../products/add.php">
                <i class="bi bi-plus-circle"></i> Ajouter produit
                <span class="admin-only-badge">Admin</span>
            </a>
            <div class="nav-section">Ventes</div>
            <a href="../sales/sell.php"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
            <a href="../sales/list.php"><i class="bi bi-clock-history"></i> Historique</a>
            <div class="nav-section">Administration</div>
            <a href="create_employee.php">
                <i class="bi bi-people"></i> Employés
                <span class="admin-only-badge">Admin</span>
            </a>
            <a href="profile.php" class="active">
                <i class="bi bi-person-circle"></i> Mon profil
                <span class="admin-only-badge">Admin</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <div class="topbar">
            <div>
                <h5>⚙️ Mon profil</h5>
                <p>Gérez vos informations personnelles et vos identifiants</p>
            </div>
        </div>

        <!-- ALERT GLOBALE -->
        <?php if($message): ?>
        <div class="alert-msg <?= $msgType ?>" id="alertMsg">
            <i class="bi bi-<?= $msgType==='success' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

        <!-- HERO PROFIL -->
        <div class="profile-hero">
            <div class="hero-photo-wrap" onclick="document.getElementById('heroPhotoInput').click()">
                <?php if(!empty($admin['photo'])): ?>
                <img id="heroImg" src="../uploads/<?= htmlspecialchars($admin['photo']) ?>" class="hero-photo"
                    alt="Photo">
                <?php else: ?>
                <div class="hero-placeholder" id="heroPlaceholder">
                    <?= strtoupper(substr($admin['username'], 0, 1)) ?>
                </div>
                <img id="heroImg" class="hero-photo" style="display:none;" alt="">
                <?php endif; ?>
                <div class="hero-edit-btn"><i class="bi bi-camera"></i></div>
            </div>
            <div class="hero-info">
                <h3><?= htmlspecialchars($admin['username']) ?></h3>
                <p><?= htmlspecialchars($admin['email']) ?></p>
                <div class="hero-badge"><i class="bi bi-shield-fill"></i> Administrateur</div>
            </div>
        </div>

        <!-- TABS -->
        <div class="profile-tabs">
            <button class="tab-btn active" onclick="switchTab('profile')">
                <i class="bi bi-person"></i> Profil & Photo
            </button>
            <button class="tab-btn" onclick="switchTab('email')">
                <i class="bi bi-envelope"></i> Changer l'email
            </button>
            <button class="tab-btn" onclick="switchTab('password')">
                <i class="bi bi-lock"></i> Changer le mot de passe
            </button>
        </div>

        <!-- TAB 1 : PROFIL & PHOTO -->
        <div class="tab-content active" id="tab-profile">
            <div class="section-card">
                <div class="section-card-header">
                    <div class="section-icon purple"><i class="bi bi-person-circle" style="color:#6366f1;"></i></div>
                    <div>
                        <h4>Informations du profil</h4>
                        <p>Modifiez votre nom et votre photo</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">

                    <!-- PHOTO UPLOAD -->
                    <div class="photo-upload-area" onclick="document.getElementById('heroPhotoInput').click()">
                        <?php if(!empty($admin['photo'])): ?>
                        <img id="uploadPreview" src="../uploads/<?= htmlspecialchars($admin['photo']) ?>" alt="">
                        <?php else: ?>
                        <div class="photo-placeholder-upload" id="uploadPlaceholder">
                            <?= strtoupper(substr($admin['username'], 0, 1)) ?>
                        </div>
                        <img id="uploadPreview" style="display:none;" alt="">
                        <?php endif; ?>
                        <strong>Cliquez pour changer la photo</strong>
                        <span>JPG, PNG, WEBP — Max 5MB</span>
                    </div>
                    <input type="file" name="photo" id="heroPhotoInput" accept="image/*" style="display:none;">

                    <div class="form-group">
                        <label>
                            <span class="lbl-icon"><i class="bi bi-person"></i></span>
                            Nom d'affichage
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>"
                                required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="lbl-icon blue"><i class="bi bi-envelope"></i></span>
                            Email (non modifiable ici)
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" value="<?= htmlspecialchars($admin['email']) ?>" disabled>
                        </div>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i>
                            Pour changer l'email, utilisez l'onglet "Changer l'email"
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="bi bi-check-circle-fill"></i> Enregistrer le profil
                    </button>
                </form>
            </div>
        </div>

        <!-- TAB 2 : EMAIL -->
        <div class="tab-content" id="tab-email">
            <div class="section-card">
                <div class="section-card-header">
                    <div class="section-icon blue"><i class="bi bi-envelope-fill" style="color:#3b82f6;"></i></div>
                    <div>
                        <h4>Changer l'adresse email</h4>
                        <p>Email actuel : <strong><?= htmlspecialchars($admin['email']) ?></strong></p>
                    </div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>
                            <span class="lbl-icon blue"><i class="bi bi-envelope"></i></span>
                            Nouvel email
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="nouveau@email.com" required>
                        </div>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i>
                            Vous utiliserez ce nouvel email pour vous connecter
                        </div>
                    </div>

                    <button type="submit" name="update_email" class="btn-save green">
                        <i class="bi bi-check-circle-fill"></i> Mettre à jour l'email
                    </button>
                </form>
            </div>
        </div>

        <!-- TAB 3 : MOT DE PASSE -->
        <div class="tab-content" id="tab-password">
            <div class="section-card">
                <div class="section-card-header">
                    <div class="section-icon red"><i class="bi bi-lock-fill" style="color:#ef4444;"></i></div>
                    <div>
                        <h4>Changer le mot de passe</h4>
                        <p>Choisissez un mot de passe fort d'au moins 6 caractères</p>
                    </div>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>
                            <span class="lbl-icon red"><i class="bi bi-lock"></i></span>
                            Mot de passe actuel
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="current_password" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="lbl-icon green"><i class="bi bi-lock-fill"></i></span>
                            Nouveau mot de passe
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill input-icon"></i>
                            <input type="password" name="new_password" id="newPassInput" placeholder="••••••••" required
                                oninput="checkStrength(this.value)">
                        </div>
                        <div class="strength-bar-wrap">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-label" id="strengthLabel"></div>
                    </div>

                    <div class="form-group">
                        <label>
                            <span class="lbl-icon green"><i class="bi bi-shield-check"></i></span>
                            Confirmer le nouveau mot de passe
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-shield-check input-icon"></i>
                            <input type="password" name="confirm_password" id="confirmPassInput" placeholder="••••••••"
                                required oninput="checkMatch()">
                        </div>
                        <div class="input-hint" id="matchHint"></div>
                    </div>

                    <button type="submit" name="update_password" class="btn-save red">
                        <i class="bi bi-shield-lock-fill"></i> Changer le mot de passe
                    </button>
                </form>
            </div>
        </div>

    </div>

    <script>
    // TABS
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    // PREVIEW PHOTO
    document.getElementById('heroPhotoInput').addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                // Hero
                const heroImg = document.getElementById('heroImg');
                const heroPh = document.getElementById('heroPlaceholder');
                heroImg.src = e.target.result;
                heroImg.style.display = 'block';
                if (heroPh) heroPh.style.display = 'none';

                // Upload area
                const upPrev = document.getElementById('uploadPreview');
                const upPh = document.getElementById('uploadPlaceholder');
                upPrev.src = e.target.result;
                upPrev.style.display = 'block';
                if (upPh) upPh.style.display = 'none';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // FORCE MOT DE PASSE
    function checkStrength(val) {
        const bar = document.getElementById('strengthBar');
        const label = document.getElementById('strengthLabel');
        let score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const levels = [{
                pct: '0%',
                color: '',
                text: ''
            },
            {
                pct: '25%',
                color: '#ef4444',
                text: '🔴 Très faible'
            },
            {
                pct: '50%',
                color: '#f59e0b',
                text: '🟡 Faible'
            },
            {
                pct: '70%',
                color: '#3b82f6',
                text: '🔵 Moyen'
            },
            {
                pct: '88%',
                color: '#10b981',
                text: '🟢 Fort'
            },
            {
                pct: '100%',
                color: '#059669',
                text: '✅ Très fort'
            },
        ];
        bar.style.width = levels[score].pct;
        bar.style.background = levels[score].color;
        label.textContent = levels[score].text;
        checkMatch();
    }

    // CORRESPONDANCE MDP
    function checkMatch() {
        const newP = document.getElementById('newPassInput').value;
        const confP = document.getElementById('confirmPassInput').value;
        const hint = document.getElementById('matchHint');
        if (!confP) {
            hint.textContent = '';
            return;
        }
        if (newP === confP) {
            hint.innerHTML =
            '<i class="bi bi-check-circle" style="color:#10b981;"></i> Les mots de passe correspondent';
            hint.style.color = '#10b981';
        } else {
            hint.innerHTML =
                '<i class="bi bi-x-circle" style="color:#ef4444;"></i> Les mots de passe ne correspondent pas';
            hint.style.color = '#ef4444';
        }
    }

    // DISPARITION ALERTE
    setTimeout(function() {
        const alert = document.getElementById('alertMsg');
        if (alert) {
            alert.style.transition = 'opacity 0.8s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 800);
        }
    }, 10000);
    </script>

</body>

</html>