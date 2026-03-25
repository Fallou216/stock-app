<?php 
include('../config/db.php');

$message = "";

if(isset($_POST['register'])){
    $u = $_POST['username'];
    $e = $_POST['email'];
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if(!empty($u) && !empty($e) && !empty($_POST['password'])){
        // Vérifier si email déjà utilisé
        $check = $conn->query("SELECT id FROM users WHERE email='$e'");
        if($check->num_rows > 0){
            $message = "<div class='alert-msg error'><i class='bi bi-x-circle-fill'></i> Cet email est déjà utilisé</div>";
        } else {
            $conn->query("INSERT INTO users(username,email,password) VALUES('$u','$e','$p')");
            $message = "<div class='alert-msg success'><i class='bi bi-check-circle-fill'></i> Compte créé avec succès ! Vous pouvez vous connecter.</div>";
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
    <title>Inscription — Stock App</title>
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

    /* FOND ANIMÉ */
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
        background: radial-gradient(circle, #10b981, #059669);
        top: -150px;
        left: -150px;
        animation-delay: 0s;
    }

    .orb-2 {
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, #4f46e5, #7c3aed);
        bottom: -100px;
        right: -100px;
        animation-delay: 3s;
    }

    .orb-3 {
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, #06b6d4, #3b82f6);
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

    /* WRAPPER */
    .register-wrapper {
        position: relative;
        z-index: 1;
        display: flex;
        width: 900px;
        max-width: 95vw;
        min-height: 580px;
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

    /* PANNEAU DROIT (formulaire) */
    .panel-right {
        width: 420px;
        background: #ffffff;
        padding: 45px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        order: 2;
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
        margin-bottom: 24px;
    }

    /* PANNEAU GAUCHE (info) */
    .panel-left {
        flex: 1;
        background: linear-gradient(145deg, #10b981 0%, #059669 40%, #0d9488 100%);
        padding: 50px 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        position: relative;
        overflow: hidden;
        order: 1;
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
        font-size: 26px;
        font-weight: 700;
        line-height: 1.3;
        margin-bottom: 14px;
    }

    .panel-left p {
        color: rgba(255, 255, 255, 0.8);
        font-size: 14px;
        line-height: 1.7;
        margin-bottom: 30px;
    }

    .steps {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .step-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .step-num {
        width: 26px;
        height: 26px;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: white;
        flex-shrink: 0;
        border: 1px solid rgba(255, 255, 255, 0.4);
    }

    .step-text {
        color: rgba(255, 255, 255, 0.85);
        font-size: 13px;
        line-height: 1.5;
    }

    .step-text strong {
        color: white;
        display: block;
        margin-bottom: 2px;
    }

    /* ALERTES */
    .alert-msg {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 15px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        margin-bottom: 18px;
        animation: fadeIn 0.3s ease;
    }

    .alert-msg.success {
        background: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
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
        margin-bottom: 16px;
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
        border-color: var(--success);
        background: white;
        box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }

    .input-wrap:focus-within .icon {
        color: var(--success);
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
        color: var(--success);
    }

    /* FORCE MOT DE PASSE */
    .password-strength {
        margin-top: 6px;
        height: 4px;
        border-radius: 4px;
        background: var(--border);
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

    /* BOUTON */
    .btn-register {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #10b981, #059669);
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
        margin-top: 8px;
        position: relative;
        overflow: hidden;
    }

    .btn-register:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-register:active {
        transform: translateY(0);
    }

    /* LIEN */
    .login-link {
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
        color: var(--gray);
    }

    .login-link a {
        color: var(--success);
        font-weight: 600;
        text-decoration: none;
        transition: color 0.2s;
    }

    .login-link a:hover {
        color: #059669;
        text-decoration: underline;
    }

    /* RESPONSIVE */
    @media(max-width: 700px) {
        .panel-left {
            display: none;
        }

        .panel-right {
            width: 100%;
            padding: 40px 28px;
            order: 1;
        }

        .register-wrapper {
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

    <div class="register-wrapper">

        <!-- PANNEAU GAUCHE -->
        <div class="panel-left">
            <div class="brand-icon">🚀</div>
            <h1>Rejoignez<br>Stock App</h1>
            <p>Créez votre compte en quelques secondes et commencez à gérer votre stock efficacement.</p>
            <div class="steps">
                <div class="step-item">
                    <div class="step-num">1</div>
                    <div class="step-text">
                        <strong>Créez votre compte</strong>
                        Remplissez le formulaire en face
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-num">2</div>
                    <div class="step-text">
                        <strong>Connectez-vous</strong>
                        Accédez à votre tableau de bord
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-num">3</div>
                    <div class="step-text">
                        <strong>Gérez votre stock</strong>
                        Ajoutez produits, suivez les ventes
                    </div>
                </div>
            </div>
        </div>

        <!-- PANNEAU DROIT (formulaire) -->
        <div class="panel-right">
            <div class="subtitle">Stock App</div>
            <h2>Créer un compte ✨</h2>
            <p class="tagline">Remplissez les informations ci-dessous pour commencer.</p>

            <?= $message ?>

            <form method="POST" id="registerForm">

                <div class="form-group">
                    <label>Nom complet</label>
                    <div class="input-wrap">
                        <i class="bi bi-person icon"></i>
                        <input type="text" name="username" placeholder="Ex: Dioum Fall" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Adresse email</label>
                    <div class="input-wrap">
                        <i class="bi bi-envelope icon"></i>
                        <input type="email" name="email" placeholder="exemple@email.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mot de passe</label>
                    <div class="input-wrap">
                        <i class="bi bi-lock icon"></i>
                        <input type="password" name="password" id="password" placeholder="••••••••" required
                            oninput="checkStrength(this.value)">
                        <button type="button" class="toggle-pass" onclick="togglePass()">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <!-- BARRE DE FORCE -->
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>

                <button type="submit" name="register" class="btn-register">
                    <i class="bi bi-person-check"></i>
                    Créer mon compte
                </button>

            </form>

            <div class="login-link">
                Déjà un compte ? <a href="login.php">Se connecter</a>
            </div>
        </div>

    </div>

    <script>
    // Afficher/masquer mot de passe
    function togglePass() {
        const input = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    }

    // Barre de force du mot de passe
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