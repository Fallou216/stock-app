<?php
session_start();
require_once('../config/db.php');
requireLogin();
requireAdmin();

$message = '';
$msgType = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = trim($_POST['username']);
    $id       = $_SESSION['user_id'];
    $photo    = $_SESSION['photo'];

    if(!empty($_FILES['photo']['name'])){
        $ext      = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = 'user_' . time() . '.' . $ext;
        if(!is_dir('../uploads')) mkdir('../uploads', 0755, true);
        move_uploaded_file($_FILES['photo']['tmp_name'], '../uploads/' . $filename);
        $photo = $filename;
    }

    $conn->query("UPDATE users SET username='$username', photo='$photo' WHERE id=$id");
    $_SESSION['user']  = $username;
    $_SESSION['photo'] = $photo;
    $message = "Profil mis à jour !";
    $msgType = "success";
}

$admin = $conn->query("SELECT * FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Mon Profil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
    /* Même style sidebar que create_employee.php */
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
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #f0f4ff;
        min-height: 100vh;
    }

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

    .sidebar-nav a:hover {
        background: rgba(255, 255, 255, 0.07);
        color: white;
    }

    .sidebar-nav a.active {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(99, 102, 241, 0.1));
        color: #a5b4fc;
        border: 1px solid rgba(99, 102, 241, 0.3);
    }

    .sidebar-nav a i {
        font-size: 17px;
        width: 20px;
        text-align: center;
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

    .content {
        margin-left: var(--sidebar-w);
        padding: 32px;
        display: flex;
        justify-content: center;
    }

    .profile-card {
        background: white;
        border-radius: 24px;
        padding: 40px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .profile-header {
        text-align: center;
        margin-bottom: 32px;
    }

    .profile-photo-wrap {
        position: relative;
        display: inline-block;
        margin-bottom: 16px;
        cursor: pointer;
    }

    .profile-photo,
    .profile-photo-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 24px;
        object-fit: cover;
        border: 4px solid #ede9fe;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.2);
    }

    .profile-photo-placeholder {
        background: linear-gradient(135deg, var(--primary), #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 40px;
        font-weight: 700;
    }

    .photo-edit-btn {
        position: absolute;
        bottom: -6px;
        right: -6px;
        width: 28px;
        height: 28px;
        background: var(--primary);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        border: 2px solid white;
    }

    .profile-header h3 {
        font-size: 22px;
        font-weight: 800;
        color: var(--dark);
        margin: 0;
    }

    .profile-header .role-tag {
        background: #ede9fe;
        color: var(--primary);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        display: inline-block;
        margin-top: 6px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 700;
        color: var(--dark);
        display: block;
        margin-bottom: 7px;
    }

    .form-group input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        color: var(--dark);
        background: #fafbff;
        outline: none;
        transition: all 0.25s;
    }

    .form-group input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
    }

    .btn-save {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #6366f1, #818cf8);
        color: white;
        border: none;
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    }

    .alert-msg {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 13px 16px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .alert-msg.success {
        background: #f0fdf4;
        color: #16a34a;
        border: 1.5px solid #bbf7d0;
    }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">📦</div>
            <h4>Stock App</h4>
            <span>Administration</span>
        </div>
        <nav class="sidebar-nav">
            <div class="admin-badge">
                <?php if($_SESSION['photo']): ?>
                <img src="../uploads/<?= $_SESSION['photo'] ?>" class="admin-avatar">
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
            <div class="nav-section">Administration</div>
            <a href="create_employee.php"><i class="bi bi-people"></i> Employés</a>
            <a href="profile.php" class="active"><i class="bi bi-person-circle"></i> Mon profil</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>

    <div class="content">
        <div class="profile-card">
            <div class="profile-header">
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="profile-photo-wrap" onclick="document.getElementById('photoInput').click()">
                        <?php if($admin['photo']): ?>
                        <img id="profileImg" src="../uploads/<?= $admin['photo'] ?>" class="profile-photo">
                        <?php else: ?>
                        <div class="profile-photo-placeholder" id="profilePlaceholder">
                            <?= strtoupper(substr($admin['username'],0,1)) ?>
                        </div>
                        <img id="profileImg" class="profile-photo" style="display:none;">
                        <?php endif; ?>
                        <div class="photo-edit-btn"><i class="bi bi-camera"></i></div>
                    </div>
                    <input type="file" name="photo" id="photoInput" accept="image/*" style="display:none;">
                    <h3><?= htmlspecialchars($admin['username']) ?></h3>
                    <span class="role-tag">👑 Administrateur</span>
            </div>

            <?php if($message): ?>
            <div class="alert-msg success"><i class="bi bi-check-circle-fill"></i><?= $message ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label>Nom d'affichage</label>
                <input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" value="<?= htmlspecialchars($admin['email']) ?>" disabled
                    style="opacity:0.6;cursor:not-allowed;">
            </div>
            <button type="submit" class="btn-save">
                <i class="bi bi-check-circle-fill"></i> Enregistrer les modifications
            </button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('photoInput').addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.getElementById('profileImg');
                const ph = document.getElementById('profilePlaceholder');
                img.src = e.target.result;
                img.style.display = 'block';
                if (ph) ph.style.display = 'none';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    </script>
</body>

</html>