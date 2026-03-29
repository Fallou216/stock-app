<?php
/**
 * bell.php — Icône cloche avec badge compteur
 * Usage : include('../notifications/bell.php');
 * ou     include('notifications/bell.php');
 */

// Compter les notifications non lues
$unreadCount = 0;
if(isset($conn)){
    $res = $conn->query("
        SELECT COUNT(*) AS c FROM notifications
        WHERE type='stock_alert' AND is_read=0
    ");
    if($res) $unreadCount = $res->fetch_assoc()['c'];
}

// Détecter le préfixe de chemin selon la page actuelle
$script = $_SERVER['SCRIPT_FILENAME'];
$isRoot = strpos($script, '/products/') === false
       && strpos($script, '/sales/') === false
       && strpos($script, '/purchases/') === false
       && strpos($script, '/admin/') === false
       && strpos($script, '/notifications/') === false;

$prefix = $isRoot ? 'notifications/' : '../notifications/';
?>
<a href="<?= $prefix ?>index.php" style="position:relative;">
    <i class="bi bi-bell<?= $unreadCount > 0 ? '-fill' : '' ?>"
        style="<?= $unreadCount > 0 ? 'color:#ef4444!important;' : '' ?>">
    </i>
    Notifications
    <?php if($unreadCount > 0): ?>
    <span style="background:#ef4444;color:white;padding:1px 7px;
                 border-radius:20px;font-size:10px;font-weight:700;
                 margin-left:auto;animation:pulse-badge 2s infinite;">
        <?= $unreadCount ?>
    </span>
    <style>
    @keyframes pulse-badge {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }

        50% {
            box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
        }
    }
    </style>
    <?php endif; ?>
</a>