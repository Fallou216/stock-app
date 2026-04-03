<?php
session_start();
require_once('../config/db.php');
requireLogin();
requireAdmin();

$message = '';
$msgType = '';

// Upload photo
function uploadPhoto($file){
    if($file['error'] !== 0) return null;
    $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg','jpeg','png','webp'];
    if(!in_array(strtolower($ext), $allowed)) return null;
    $filename = 'user_' . time() . '.' . $ext;
    $dest     = '../uploads/' . $filename;
    if(!is_dir('../uploads')) mkdir('../uploads', 0755, true);
    move_uploaded_file($file['tmp_name'], $dest);
    return $filename;
}

// ── CRÉER un employé ────────────────────────────────
if(isset($_POST['action']) && $_POST['action'] === 'create'){
    $u = trim($_POST['username']);
    $e = trim($_POST['email']);
    $p = $_POST['password'];

    if(!empty($u) && !empty($e) && !empty($p)){
        if(!filter_var($e, FILTER_VALIDATE_EMAIL)){
            $message = "L'adresse email n'est pas valide.";
            $msgType = "error";
        } else {
            $stmtChk = $conn->prepare("SELECT id FROM users WHERE email=?");
            $stmtChk->bind_param("s", $e);
            $stmtChk->execute();
            $stmtChk->store_result();

            if($stmtChk->num_rows > 0){
                $message = "Cet email est déjà utilisé.";
                $msgType = "error";
            } else {
                $hash  = password_hash($p, PASSWORD_DEFAULT);
                $photo = uploadPhoto($_FILES['photo'] ?? ['error'=>4]);

                $stmtIns = $conn->prepare("INSERT INTO users(username,email,password,role,photo) VALUES(?,?,?,'employee',?)");
                $stmtIns->bind_param("ssss", $u, $e, $hash, $photo);
                $stmtIns->execute();
                $stmtIns->close();

                $message = "✅ Compte employé créé pour $u !";
                $msgType = "success";
            }
            $stmtChk->close();
        }
    } else {
        $message = "Remplissez tous les champs.";
        $msgType = "error";
    }
}

// ── MODIFIER un employé ─────────────────────────────
if(isset($_POST['action']) && $_POST['action'] === 'edit'){
    $edit_id  = intval($_POST['edit_id']);
    $u        = trim($_POST['username']);
    $e        = trim($_POST['email']);
    $newPass  = $_POST['new_password'];

    if(!empty($u) && !empty($e)){
        if(!filter_var($e, FILTER_VALIDATE_EMAIL)){
            $message = "L'adresse email n'est pas valide.";
            $msgType = "error";
        } else {
            // Vérifier email pas déjà utilisé par un autre
            $stmtChk = $conn->prepare("SELECT id FROM users WHERE email=? AND id != ?");
            $stmtChk->bind_param("si", $e, $edit_id);
            $stmtChk->execute();
            $stmtChk->store_result();

            if($stmtChk->num_rows > 0){
                $message = "Cet email est déjà utilisé par un autre compte.";
                $msgType = "error";
            } else {
                // Gérer la photo
                $photoUpdate = "";
                $newPhoto    = uploadPhoto($_FILES['edit_photo'] ?? ['error'=>4]);
                if($newPhoto){
                    // Supprimer ancienne photo
                    $oldRes = $conn->query("SELECT photo FROM users WHERE id=$edit_id");
                    $oldRow = $oldRes->fetch_assoc();
                    if($oldRow['photo'] && file_exists('../uploads/' . $oldRow['photo'])){
                        unlink('../uploads/' . $oldRow['photo']);
                    }
                    $stmtPh = $conn->prepare("UPDATE users SET photo=? WHERE id=?");
                    $stmtPh->bind_param("si", $newPhoto, $edit_id);
                    $stmtPh->execute();
                    $stmtPh->close();
                }

                // Mettre à jour username + email
                $stmtUpd = $conn->prepare("UPDATE users SET username=?, email=? WHERE id=?");
                $stmtUpd->bind_param("ssi", $u, $e, $edit_id);
                $stmtUpd->execute();
                $stmtUpd->close();

                // Mettre à jour mot de passe si fourni
                if(!empty($newPass)){
                    if(strlen($newPass) < 6){
                        $message = "Le mot de passe doit contenir au moins 6 caractères.";
                        $msgType = "error";
                    } else {
                        $hash    = password_hash($newPass, PASSWORD_DEFAULT);
                        $stmtPw  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                        $stmtPw->bind_param("si", $hash, $edit_id);
                        $stmtPw->execute();
                        $stmtPw->close();
                    }
                }

                if($msgType !== "error"){
                    $message = "✅ Compte de $u mis à jour avec succès !";
                    $msgType = "success";
                }
            }
            $stmtChk->close();
        }
    } else {
        $message = "Le nom et l'email sont obligatoires.";
        $msgType = "error";
    }
}

// ── Récupérer l'employé à modifier ──────────────────
$editEmployee = null;
$editMode     = false;
if(isset($_GET['edit'])){
    $editId = intval($_GET['edit']);
    $res    = $conn->prepare("SELECT * FROM users WHERE id=? AND role='employee'");
    $res->bind_param("i", $editId);
    $res->execute();
    $editEmployee = $res->get_result()->fetch_assoc();
    $res->close();
    if($editEmployee) $editMode = true;
}

// Liste des employés
$employees = $conn->query("SELECT * FROM users WHERE role='employee' ORDER BY id DESC");

// Compteur notifications
$bellCount = 0;
$bellRes   = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE type='stock_alert' AND is_read=0");
if($bellRes) $bellCount = $bellRes->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Employés — Stock App</title>
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
        --orange: #f59e0b;
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

    .sidebar-nav {
        flex: 1;
        padding: 20px 12px;
        overflow-y: auto;
    }

    .nav-section {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 1.5px;
        color: rgba(255, 255, 255, 0.25);
        text-transform: uppercase;
        padding: 0 12px;
        margin: 16px 0 8px;
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

    .notif-badge {
        background: #ef4444;
        color: white;
        padding: 1px 7px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        margin-left: auto;
    }

    .admin-badge {
        margin: 0 12px 16px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(99, 102, 241, 0.05));
        border: 1px solid rgba(99, 102, 241, 0.3);
        border-radius: 12px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .admin-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .admin-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--primary), #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .admin-info-name {
        color: white;
        font-size: 13px;
        font-weight: 700;
    }

    .admin-info-role {
        color: #a5b4fc;
        font-size: 11px;
        font-weight: 600;
        background: rgba(99, 102, 241, 0.2);
        padding: 1px 7px;
        border-radius: 10px;
        display: inline-block;
        margin-top: 2px;
    }

    .sidebar-footer {
        padding: 16px 12px;
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

    /* LAYOUT */
    .page-layout {
        display: grid;
        grid-template-columns: 420px 1fr;
        gap: 24px;
        align-items: start;
    }

    /* FORM CARD */
    .form-card {
        background: white;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
        transition: all 0.3s;
    }

    .form-card.edit-mode {
        border-color: #f59e0b;
        box-shadow: 0 4px 20px rgba(245, 158, 11, 0.15);
    }

    .form-card-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 24px;
        padding-bottom: 18px;
        border-bottom: 2px solid #f1f5f9;
    }

    .form-header-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .form-header-icon.create {
        background: linear-gradient(135deg, #ede9fe, #ddd6fe);
    }

    .form-header-icon.edit {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
    }

    .form-card-header h4 {
        font-size: 18px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .form-card-header p {
        font-size: 13px;
        color: var(--gray);
        margin: 3px 0 0;
    }

    /* MODE TAG */
    .mode-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        margin-top: 5px;
    }

    .mode-tag.create {
        background: #ede9fe;
        color: var(--primary);
    }

    .mode-tag.edit {
        background: #fef3c7;
        color: #d97706;
    }

    /* PHOTO */
    .photo-upload {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 16px;
        background: #fafbff;
        border: 2px dashed var(--border);
        border-radius: 12px;
        margin-bottom: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .photo-upload:hover {
        border-color: var(--primary);
        background: #eef2ff;
    }

    .photo-preview {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        object-fit: cover;
        border: 2px solid var(--border);
        flex-shrink: 0;
    }

    .photo-placeholder {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        background: #ede9fe;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }

    .photo-upload-text strong {
        font-size: 13px;
        font-weight: 700;
        color: var(--dark);
        display: block;
    }

    .photo-upload-text span {
        font-size: 11px;
        color: var(--gray);
    }

    /* FORM ELEMENTS */
    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 13px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 7px;
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

    .lbl-icon.orange {
        background: #fef3c7;
        color: var(--orange);
    }

    .optional-tag {
        font-size: 10px;
        color: #94a3b8;
        font-weight: 500;
        background: #f1f5f9;
        padding: 1px 6px;
        border-radius: 10px;
        margin-left: 4px;
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap input {
        width: 100%;
        padding: 12px 16px 12px 42px;
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

    .input-wrap input::placeholder {
        color: #cbd5e1;
    }

    .input-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
        color: #94a3b8;
        pointer-events: none;
        transition: color 0.2s;
    }

    .input-wrap:focus-within .input-icon {
        color: var(--primary);
    }

    .input-hint {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* STRENGTH BAR */
    .strength-bar {
        height: 4px;
        background: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 6px;
    }

    .strength-fill {
        height: 100%;
        border-radius: 4px;
        width: 0%;
        transition: width 0.3s, background 0.3s;
    }

    .strength-label {
        font-size: 10px;
        color: var(--gray);
        margin-top: 3px;
    }

    /* MATCH HINT */
    .match-hint {
        font-size: 11px;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* DIVIDER */
    .form-divider {
        border: none;
        border-top: 2px dashed #e2e8f0;
        margin: 18px 0;
    }

    /* BUTTONS */
    .btn-create {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        border: none;
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
    }

    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    }

    .btn-edit-save {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        border: none;
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-edit-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
    }

    .btn-cancel {
        width: 100%;
        padding: 12px;
        background: white;
        color: var(--gray);
        border: 2px solid var(--border);
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 10px;
        text-decoration: none;
    }

    .btn-cancel:hover {
        border-color: var(--red);
        color: var(--red);
        background: #fef2f2;
    }

    /* ALERT */
    .alert-msg {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 18px;
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
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* TABLE CARD */
    .table-card {
        background: white;
        border-radius: 20px;
        padding: 28px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .table-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .table-card-header h5 {
        font-size: 16px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .count-badge {
        background: #ede9fe;
        color: var(--primary);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 700;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table th {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: var(--gray);
        padding: 8px 14px;
        border-bottom: 2px solid var(--border);
    }

    table td {
        padding: 12px 14px;
        font-size: 13px;
        color: var(--dark);
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    table tbody tr:last-child td {
        border-bottom: none;
    }

    table tbody tr:hover td {
        background: #f8faff;
    }

    table tbody tr.editing td {
        background: #fffbeb !important;
    }

    .emp-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        object-fit: cover;
    }

    .emp-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        color: white;
        flex-shrink: 0;
    }

    .role-badge.employee {
        background: #d1fae5;
        color: var(--green);
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }

    .action-btn {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }

    .action-btn.edit-btn {
        background: #fef3c7;
        color: #d97706;
    }

    .action-btn.edit-btn:hover {
        background: var(--orange);
        color: white;
        transform: scale(1.15);
    }

    .action-btn.delete {
        background: #fef2f2;
        color: var(--red);
    }

    .action-btn.delete:hover {
        transform: scale(1.15);
        background: var(--red);
        color: white;
    }

    /* EMPTY STATE */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 40px;
        opacity: 0.3;
        display: block;
        margin-bottom: 12px;
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
        <nav class="sidebar-nav">
            <div class="admin-badge">
                <?php if(!empty($_SESSION['photo'])): ?>
                <img src="../uploads/<?= $_SESSION['photo'] ?>" class="admin-avatar" alt="">
                <?php else: ?>
                <div class="admin-avatar-placeholder"><?= strtoupper(substr($_SESSION['user'],0,1)) ?></div>
                <?php endif; ?>
                <div>
                    <div class="admin-info-name"><?= htmlspecialchars($_SESSION['user']) ?></div>
                    <div class="admin-info-role">👑 Administrateur</div>
                </div>
            </div>

            <div class="nav-section">Principal</div>
            <a href="../dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <div class="nav-section">Inventaire</div>
            <a href="../products/list.php"><i class="bi bi-box-seam"></i> Produits</a>
            <a href="../products/add.php"><i class="bi bi-plus-circle"></i> Ajouter produit</a>
            <div class="nav-section">Achats</div>
            <a href="../purchases/add.php"><i class="bi bi-bag-plus"></i> Nouvel achat</a>
            <a href="../purchases/list.php"><i class="bi bi-clock-history"></i> Historique achats</a>
            <a href="../purchases/suppliers.php"><i class="bi bi-building"></i> Fournisseurs</a>
            <div class="nav-section">Ventes</div>
            <a href="../sales/sell.php"><i class="bi bi-cart-plus"></i> Nouvelle vente</a>
            <a href="../sales/list.php"><i class="bi bi-clock-history"></i> Historique ventes</a>
            <div class="nav-section">Alertes</div>
            <a href="../notifications/index.php">
                <i class="bi bi-bell<?= $bellCount > 0 ? '-fill' : '' ?>"></i> Notifications
                <?php if($bellCount > 0): ?>
                <span class="notif-badge"><?= $bellCount > 9 ? '9+' : $bellCount ?></span>
                <?php endif; ?>
            </a>
            <div class="nav-section">Administration</div>
            <a href="create_employee.php" class="active"><i class="bi bi-people"></i> Employés</a>
            <a href="profile.php"><i class="bi bi-person-circle"></i> Mon profil</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">
        <div class="topbar">
            <div>
                <h5>👥 Gestion des employés</h5>
                <p>
                    <?= $editMode ? '✏️ Modification du compte de ' . htmlspecialchars($editEmployee['username']) : 'Créez et gérez les comptes de vos employés' ?>
                </p>
            </div>
        </div>

        <div class="page-layout">

            <!-- FORMULAIRE CRÉATION / MODIFICATION -->
            <div class="form-card <?= $editMode ? 'edit-mode' : '' ?>">
                <div class="form-card-header">
                    <div class="form-header-icon <?= $editMode ? 'edit' : 'create' ?>">
                        <?= $editMode ? '✏️' : '👤' ?>
                    </div>
                    <div>
                        <h4><?= $editMode ? 'Modifier l\'employé' : 'Nouvel employé' ?></h4>
                        <p><?= $editMode ? htmlspecialchars($editEmployee['username']) : 'Créer un compte employé' ?>
                        </p>
                        <div class="mode-tag <?= $editMode ? 'edit' : 'create' ?>">
                            <?= $editMode ? '✏️ Mode modification' : '➕ Mode création' ?>
                        </div>
                    </div>
                </div>

                <!-- ALERT -->
                <?php if($message): ?>
                <div class="alert-msg <?= $msgType ?>" id="alertMsg">
                    <i class="bi bi-<?= $msgType==='success' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                    <?= $message ?>
                </div>
                <?php endif; ?>

                <!-- ══ FORMULAIRE CRÉATION ══ -->
                <?php if(!$editMode): ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">

                    <!-- PHOTO -->
                    <div class="photo-upload" onclick="document.getElementById('photoInput').click()">
                        <div class="photo-placeholder" id="photoPlaceholder">📷</div>
                        <img id="photoPreview" class="photo-preview" style="display:none;" alt="">
                        <div class="photo-upload-text">
                            <strong>Photo de profil</strong>
                            <span>Cliquez pour choisir une image (JPG, PNG, WebP)</span>
                        </div>
                    </div>
                    <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none;">

                    <div class="form-group">
                        <label><span class="lbl-icon"><i class="bi bi-person"></i></span> Nom complet</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="username" placeholder="Ex: Mamadou Diallo" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><span class="lbl-icon"><i class="bi bi-envelope"></i></span> Email</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="employe@email.com" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><span class="lbl-icon"><i class="bi bi-lock"></i></span> Mot de passe</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" name="password" id="newPassCreate" placeholder="••••••••" required
                                oninput="checkStrengthCreate(this.value)">
                        </div>
                        <div class="strength-bar">
                            <div class="strength-fill" id="sfCreate"></div>
                        </div>
                        <div class="strength-label" id="slCreate"></div>
                    </div>

                    <button type="submit" class="btn-create">
                        <i class="bi bi-person-plus-fill"></i> Créer le compte
                    </button>
                </form>

                <!-- ══ FORMULAIRE MODIFICATION ══ -->
                <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_id" value="<?= $editEmployee['id'] ?>">

                    <!-- PHOTO actuelle + changement -->
                    <div class="photo-upload" onclick="document.getElementById('editPhotoInput').click()">
                        <?php if($editEmployee['photo']): ?>
                        <img id="editPhotoPreview" class="photo-preview"
                            src="../uploads/<?= htmlspecialchars($editEmployee['photo']) ?>" alt="">
                        <div class="photo-placeholder" id="editPhotoPlaceholder" style="display:none;">📷</div>
                        <?php else: ?>
                        <div class="photo-placeholder" id="editPhotoPlaceholder">
                            <?= strtoupper(substr($editEmployee['username'],0,1)) ?>
                        </div>
                        <img id="editPhotoPreview" class="photo-preview" style="display:none;" alt="">
                        <?php endif; ?>
                        <div class="photo-upload-text">
                            <strong>Changer la photo</strong>
                            <span>Cliquez pour sélectionner une nouvelle image</span>
                        </div>
                    </div>
                    <input type="file" name="edit_photo" id="editPhotoInput" accept="image/*" style="display:none;">

                    <!-- NOM -->
                    <div class="form-group">
                        <label><span class="lbl-icon"><i class="bi bi-person"></i></span> Nom complet</label>
                        <div class="input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" name="username"
                                value="<?= htmlspecialchars($editEmployee['username']) ?>" required>
                        </div>
                    </div>

                    <!-- EMAIL -->
                    <div class="form-group">
                        <label><span class="lbl-icon"><i class="bi bi-envelope"></i></span> Email</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" name="email" value="<?= htmlspecialchars($editEmployee['email']) ?>"
                                required>
                        </div>
                    </div>

                    <hr class="form-divider">

                    <!-- NOUVEAU MOT DE PASSE -->
                    <div class="form-group">
                        <label>
                            <span class="lbl-icon orange"><i class="bi bi-key"></i></span>
                            Nouveau mot de passe
                            <span class="optional-tag">facultatif</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-key input-icon"></i>
                            <input type="password" name="new_password" id="newPassEdit"
                                placeholder="Laisser vide pour ne pas changer" oninput="checkStrengthEdit(this.value)">
                        </div>
                        <div class="strength-bar">
                            <div class="strength-fill" id="sfEdit"></div>
                        </div>
                        <div class="strength-label" id="slEdit"></div>
                        <div class="input-hint">
                            <i class="bi bi-info-circle"></i>
                            Minimum 6 caractères — laisser vide pour conserver l'actuel
                        </div>
                    </div>

                    <!-- CONFIRMER MDP -->
                    <div class="form-group">
                        <label>
                            <span class="lbl-icon orange"><i class="bi bi-shield-check"></i></span>
                            Confirmer le mot de passe
                            <span class="optional-tag">facultatif</span>
                        </label>
                        <div class="input-wrap">
                            <i class="bi bi-shield-check input-icon"></i>
                            <input type="password" name="confirm_password" id="confirmPassEdit"
                                placeholder="Répétez le nouveau mot de passe" oninput="checkMatchEdit()">
                        </div>
                        <div class="match-hint" id="matchHintEdit"></div>
                    </div>

                    <button type="submit" name="save_edit" class="btn-edit-save" onclick="return validateEdit()">
                        <i class="bi bi-check-circle-fill"></i>
                        Enregistrer les modifications
                    </button>
                    <a href="create_employee.php" class="btn-cancel">
                        <i class="bi bi-arrow-left"></i> Annuler
                    </a>
                </form>
                <?php endif; ?>
            </div>

            <!-- LISTE EMPLOYÉS -->
            <div class="table-card">
                <div class="table-card-header">
                    <h5>👥 Liste des employés</h5>
                    <span class="count-badge"><?= $employees->num_rows ?> employé(s)</span>
                </div>

                <?php if($employees->num_rows === 0): ?>
                <div class="empty-state">
                    <i class="bi bi-people"></i>
                    <p>Aucun employé créé pour l'instant.</p>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Créé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                $colors = ['#6366f1','#10b981','#f59e0b','#ec4899','#3b82f6'];
                $idx = 0;
                // Re-fetch pour avoir la liste à jour après modifications
                $empList = $conn->query("SELECT * FROM users WHERE role='employee' ORDER BY id DESC");
                while($emp = $empList->fetch_assoc()):
                    $color     = $colors[$idx % count($colors)]; $idx++;
                    $isEditing = ($editMode && $editEmployee['id'] == $emp['id']);
                ?>
                        <tr class="<?= $isEditing ? 'editing' : '' ?>">
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php if($emp['photo']): ?>
                                    <img src="../uploads/<?= htmlspecialchars($emp['photo']) ?>" class="emp-avatar"
                                        alt="">
                                    <?php else: ?>
                                    <div class="emp-avatar-placeholder" style="background:<?= $color ?>">
                                        <?= strtoupper(substr($emp['username'],0,1)) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= htmlspecialchars($emp['username']) ?></strong>
                                        <?php if($isEditing): ?>
                                        <span
                                            style="background:#fef3c7;color:#d97706;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:700;margin-left:4px;">En
                                            cours de modification</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="color:#64748b;"><?= htmlspecialchars($emp['email']) ?></td>
                            <td><span class="role-badge employee">👤 Employé</span></td>
                            <td style="color:#94a3b8;"><?= date('d/m/Y', strtotime($emp['created_at'] ?? 'now')) ?></td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <!-- Bouton MODIFIER -->
                                    <a href="create_employee.php?edit=<?= $emp['id'] ?>" class="action-btn edit-btn"
                                        title="Modifier cet employé">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <!-- Bouton SUPPRIMER -->
                                    <a href="delete_employee.php?id=<?= $emp['id'] ?>" class="action-btn delete"
                                        onclick="return confirm('Supprimer <?= htmlspecialchars($emp['username']) ?> ?')"
                                        title="Supprimer cet employé">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // ── Preview photo CRÉATION ───────────────
    document.getElementById('photoInput')?.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('photoPreview').src = e.target.result;
                document.getElementById('photoPreview').style.display = 'block';
                document.getElementById('photoPlaceholder').style.display = 'none';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // ── Preview photo MODIFICATION ───────────
    document.getElementById('editPhotoInput')?.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('editPhotoPreview').src = e.target.result;
                document.getElementById('editPhotoPreview').style.display = 'block';
                document.getElementById('editPhotoPlaceholder').style.display = 'none';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // ── Barre force mot de passe ─────────────
    function checkStrength(val, fillId, labelId) {
        const fill = document.getElementById(fillId);
        const label = document.getElementById(labelId);
        if (!fill || !label) return;
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
                pct: '75%',
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
        fill.style.width = levels[score].pct;
        fill.style.background = levels[score].color;
        label.textContent = levels[score].text;
    }

    function checkStrengthCreate(val) {
        checkStrength(val, 'sfCreate', 'slCreate');
    }

    function checkStrengthEdit(val) {
        checkStrength(val, 'sfEdit', 'slEdit');
        checkMatchEdit();
    }

    // ── Vérification correspondance ──────────
    function checkMatchEdit() {
        const newP = document.getElementById('newPassEdit')?.value || '';
        const conf = document.getElementById('confirmPassEdit')?.value || '';
        const hint = document.getElementById('matchHintEdit');
        if (!hint || !conf) return;
        if (newP === conf) {
            hint.innerHTML =
            '<i class="bi bi-check-circle" style="color:#10b981;"></i> Les mots de passe correspondent';
            hint.style.color = '#10b981';
        } else {
            hint.innerHTML =
                '<i class="bi bi-x-circle" style="color:#ef4444;"></i> Les mots de passe ne correspondent pas';
            hint.style.color = '#ef4444';
        }
    }

    // ── Validation avant submit MODIFICATION ─
    function validateEdit() {
        const newP = document.getElementById('newPassEdit')?.value || '';
        const conf = document.getElementById('confirmPassEdit')?.value || '';
        if (newP && newP !== conf) {
            alert('Les mots de passe ne correspondent pas.');
            return false;
        }
        if (newP && newP.length < 6) {
            alert('Le mot de passe doit contenir au moins 6 caractères.');
            return false;
        }
        return true;
    }

    // ── Disparition alertes ──────────────────
    setTimeout(function() {
        document.querySelectorAll('.alert-msg').forEach(el => {
            el.style.transition = 'opacity 0.8s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 800);
        });
    }, 10000);
    </script>
</body>

</html>