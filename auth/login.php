<?php
session_start();
include('../config/db.php');

$message = "";

if(isset($_POST['login'])){
    $email = $_POST['email'];
    $pass  = $_POST['password'];

    if(!empty($email) && !empty($pass)){
        $res  = $conn->query("SELECT * FROM users WHERE email='$email'");
        $user = $res->fetch_assoc();

        if($user && password_verify($pass, $user['password'])){
            // ── Stocker toutes les infos de session ──
            $_SESSION['user']    = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['photo']   = $user['photo'] ?? null;

            header("Location: ../dashboard.php");
            exit();
        } else {
            $message = "<div class='alert-msg error'><i class='bi bi-x-circle-fill'></i> Email ou mot de passe incorrect</div>";
        }
    } else {
        $message = "<div class='alert-msg warning'><i class='bi bi-exclamation-triangle-fill'></i> Veuillez remplir tous les champs</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Stock App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
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
        --primary: #4f46e5;
        --primary-dk: #3730a3;
        --primary-lt: #eef2ff;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --dark: #0f172a;
        --gray: #64748b;
        --light: #f8fafc;
        --border: #e2e8f0;
    }

    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        background: #0f172a;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
    }

    .bg-orbs {
        position: fixed;
        inset: 0;
        z-index: 0;
        overflow: hidden;
    }

    .orb {
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.35;
        animation: drift 10s ease-in-out infinite alternate;
    }

    .orb-1 {
        width: 500px;
        height: 500px;
        background: radial-gradient(circle, #4f46e5, #7c3aed);
        top: -150px;
        left: -150px;
        animation-delay: 0s;
    }

    .orb-2 {
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, #06b6d4, #3b82f6);
        bottom: -100px;
        right: -100px;
        animation-delay: 3s;
    }

    .orb-3 {
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, #10b981, #059669);
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        animation-delay: 6s;
    }

    @keyframes drift {
        from {
            transform: translate(0, 0) scale(1);
        }

        to {
            transform: translate(30px, 20px) scale(1.05);
        }
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        background-image: radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
        background-size: 30px 30px;
        z-index: 0;
    }

    .login-wrapper {
        position: relative;
        z-index: 1;
        display: flex;
        width: 900px;
        max-width: 95vw;
        min-height: 540px;
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
        animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(40px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .panel-left {
        flex: 1;
        background: linear-gradient(145deg, #4f46e5 0%, #7c3aed 50%, #2563eb 100%);
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        position: relative;
        overflow: hidden;
    }

    .panel-left::before {
        content: '';
        position: absolute;
        width: 300px;
        height: 300px;
        background: rgba(255, 255, 255, 0.07);
        border-radius: 50%;
        top: -80px;
        right: -80px;
    }

    .panel-left::after {
        content: '';
        position: absolute;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
        bottom: -60px;
        left: -60px;
    }

    .brand-icon {
        width: 64px;
        height: 64px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin-bottom: 28px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        animation: pulse 3s ease-in-out infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.3);
        }

        50% {
            box-shadow: 0 0 0 12px rgba(255, 255, 255, 0);
        }
    }

    .panel-left h1 {
        color: white;
        font-size: 28px;
        font-weight: 700;
        line-height: 1.3;
        margin-bottom: 14px;
    }

    .panel-left p {
        color: rgba(255, 255, 255, 0.75);
        font-size: 14px;
        line-height: 1.7;
        margin-bottom: 30px;
    }

    .features {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 10px;
        color: rgba(255, 255, 255, 0.85);
        font-size: 13px;
    }

    .feature-dot {
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: 0 0 8px #10b981;
    }

    /* ADMIN BADGE sur le panneau gauche */
    .admin-hint {
        margin-top: 28px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        backdrop-filter: blur(6px);
    }

    .admin-hint i {
        font-size: 20px;
        color: #fcd34d;
    }

    .admin-hint p {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.8);
        margin: 0;
        line-height: 1.5;
    }

    .admin-hint strong {
        color: white;
    }

    .panel-right {
        width: 420px;
        background: #ffffff;
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .panel-right .subtitle {
        font-size: 13px;
        color: var(--gray);
        font-weight: 500;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .panel-right h2 {
        font-size: 26px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 6px;
    }

    .panel-right .tagline {
        font-size: 13.5px;
        color: var(--gray);
        margin-bottom: 28px;
    }

    /* ALERT */
    .alert-msg {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 15px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 20px;
        animation: fadeIn 0.3s ease;
    }

    .alert-msg.error {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .alert-msg.warning {
        background: #fffbeb;
        color: #d97706;
        border: 1px solid #fde68a;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* FORM */
    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 7px;
    }

    .input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-wrap .icon {
        position: absolute;
        left: 14px;
        color: var(--gray);
        font-size: 16px;
        pointer-events: none;
        transition: color 0.2s;
    }

    .input-wrap input {
        width: 100%;
        padding: 12px 14px 12px 42px;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 14px;
        color: var(--dark);
        background: var(--light);
        transition: all 0.2s;
        outline: none;
    }

    .input-wrap input:focus {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }

    .input-wrap:focus-within .icon {
        color: var(--primary);
    }

    .toggle-pass {
        position: absolute;
        right: 14px;
        background: none;
        border: none;
        color: var(--gray);
        cursor: pointer;
        font-size: 16px;
        padding: 0;
        transition: color 0.2s;
    }

    .toggle-pass:hover {
        color: var(--primary);
    }

    /* ROLE TABS */
    .role-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 22px;
    }

    .role-tab {
        flex: 1;
        padding: 9px 12px;
        border: 2px solid var(--border);
        border-radius: 10px;
        background: white;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: var(--gray);
        cursor: pointer;
        transition: all 0.2s;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .role-tab.active {
        border-color: var(--primary);
        background: var(--primary-lt);
        color: var(--primary);
    }

    .role-tab:hover:not(.active) {
        border-color: #c7d2fe;
        background: #f5f3ff;
    }

    /* BOUTON */
    .btn-login {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: white;
        border: none;
        border-radius: 12px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 6px;
        position: relative;
        overflow: hidden;
    }

    .btn-login::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, 0);
        transition: background 0.2s;
    }

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
    }

    .btn-login:hover::after {
        background: rgba(255, 255, 255, 0.08);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    .register-link {
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
        color: var(--gray);
    }

    .register-link a {
        color: var(--primary);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.2s;
    }

    .register-link a:hover {
        color: var(--primary-dk);
        text-decoration: underline;
    }

    @media(max-width:700px) {
        .panel-left {
            display: none;
        }

        .panel-right {
            width: 100%;
            padding: 40px 28px;
        }

        .login-wrapper {
            width: 95vw;
            min-height: auto;
        }
    }
    </style>
</head>

<body>

    <!-- FOND -->
    <div class="bg-orbs">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <div class="login-wrapper">

        <!-- PANNEAU GAUCHE -->
        <div class="panel-left">
            <div class="brand-icon">📦</div>
            <h1>Bienvenue sur<br>Stock App</h1>
            <p>Votre solution complète pour gérer votre stock, suivre vos ventes et piloter votre activité en temps
                réel.</p>
            <div class="features">
                <div class="feature-item"><span class="feature-dot"></span> Gestion des produits & stocks</div>
                <div class="feature-item"><span class="feature-dot"></span> Suivi des ventes en temps réel</div>
                <div class="feature-item"><span class="feature-dot"></span> Tableaux de bord & statistiques</div>
                <div class="feature-item"><span class="feature-dot"></span> Alertes stock faible automatiques</div>
            </div>

            <!-- HINT RÔLES -->
            <div class="admin-hint">
                <i class="bi bi-shield-lock-fill"></i>
                <p>
                    <strong>Accès sécurisé par rôle</strong><br>
                    Admin : accès complet &amp; gestion des équipes<br>
                    Employé : accès limité à son périmètre
                </p>
            </div>
        </div>

        <!-- PANNEAU DROIT -->
        <div class="panel-right">
            <div class="subtitle">Stock App</div>
            <h2>Connexion 👋</h2>
            <p class="tagline">Veuillez vous connecter pour accéder à votre espace.</p>

            <!-- TABS RÔLE (visuel uniquement, le rôle est détecté auto en BDD) -->
            <div class="role-tabs">
                <button type="button" class="role-tab active" id="tabAdmin" onclick="setTab('admin')">
                    <i class="bi bi-shield-fill"></i> Administrateur
                </button>
                <button type="button" class="role-tab" id="tabEmployee" onclick="setTab('employee')">
                    <i class="bi bi-person-fill"></i> Employé
                </button>
            </div>

            <?= $message ?>

            <form method="POST" id="loginForm">

                <div class="form-group">
                    <label>Adresse email</label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope icon"></i>
                        <input type="email" name="email" id="emailInput" placeholder="exemple@email.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mot de passe</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock icon"></i>
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <button type="button" class="toggle-pass" onclick="togglePass()">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" class="btn-login" id="btnLogin">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Se connecter
                </button>

            </form>


            <!-- <div class="register-link">
                Pas encore de compte ? <a href="register.php">Créer un compte</a>
            </div>
        </div>-->
        
            <!-- Mot de passe oublié -->
            <div style="text-align:center; margin:20px 0 10px;">
                <a href="forgot_password.php" style="
           display:inline-block;
           font-size:14px;
           color:var(--primary);
           font-weight:600;
           text-decoration:none;
           padding:8px 16px;
           border-radius:8px;
           transition:all 0.3s ease;
       " onmouseover="this.style.background='rgba(0,0,0,0.05)'" onmouseout="this.style.background='transparent'">
                    Mot de passe oublié ?
                </a>
            </div>

        </div>



        <script>
        // Toggle mot de passe
        function togglePass() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        }

        // Tabs visuels (informatif — le rôle est détecté automatiquement en BDD)
        function setTab(role) {
            document.getElementById('tabAdmin').classList.toggle('active', role === 'admin');
            document.getElementById('tabEmployee').classList.toggle('active', role === 'employee');
            // Changer le placeholder email selon le tab
            const input = document.getElementById('emailInput');
            input.placeholder = role === 'admin' ? 'admin@stock.com' : 'employe@email.com';
        }

        // Disparition alertes après 10s
        setTimeout(function() {
            document.querySelectorAll('.alert-msg').forEach(function(el) {
                el.style.transition = 'opacity 0.8s ease';
                el.style.opacity = '0';
                setTimeout(function() {
                    el.remove();
                }, 800);
            });
        }, 10000);
        </script>

</body>

</html>