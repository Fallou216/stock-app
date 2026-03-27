<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: ../auth/login.php");
    exit();
}
include('../config/db.php');

// Réservé à l'admin uniquement
requireAdmin();

// Récupérer toutes les ventes
$sales = $conn->query("
    SELECT 
        s.id,
        p.name AS produit,
        s.quantity_sold AS quantite_vendue,
        s.sale_price AS prix_unitaire,
        (s.quantity_sold * s.sale_price) AS total,
        s.sold_at AS date_vente
    FROM sales s
    JOIN products p ON s.product_id = p.id
    ORDER BY s.sold_at DESC
");

// Stats globales
$stats = $conn->query("
    SELECT 
        COUNT(*) AS total_ventes,
        SUM(quantity_sold * sale_price) AS revenu_total,
        SUM(quantity_sold) AS total_unites
    FROM sales
")->fetch_assoc();

// ── Générer le fichier CSV ──────────────────────────────
$filename = 'ventes_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ── EN-TÊTE DU RAPPORT ──────────────────────────────────
fputcsv($output, ['STOCK APP — RAPPORT DES VENTES'], ';');
fputcsv($output, ['Généré le : ' . date('d/m/Y à H:i:s')], ';');
fputcsv($output, ['Généré par : ' . $_SESSION['user']], ';');
fputcsv($output, [], ';'); // ligne vide

// ── STATISTIQUES GLOBALES ───────────────────────────────
fputcsv($output, ['=== STATISTIQUES GLOBALES ==='], ';');
fputcsv($output, ['Total des ventes', $stats['total_ventes']], ';');
fputcsv($output, ['Unités totales vendues', $stats['total_unites']], ';');
fputcsv($output, ['Revenu total (FCFA)', number_format($stats['revenu_total'] ?? 0, 0, '', ' ')], ';');
fputcsv($output, [], ';'); // ligne vide

// ── EN-TÊTES DU TABLEAU ─────────────────────────────────
fputcsv($output, [
    '#',
    'Produit',
    'Quantité vendue',
    'Prix unitaire (FCFA)',
    'Total (FCFA)',
    'Date & Heure'
], ';');

// ── DONNÉES ─────────────────────────────────────────────
$grandTotal = 0;
while($s = $sales->fetch_assoc()){
    $grandTotal += $s['total'];
    fputcsv($output, [
        $s['id'],
        $s['produit'],
        $s['quantite_vendue'],
        number_format($s['prix_unitaire'], 0, '', ' '),
        number_format($s['total'], 0, '', ' '),
        date('d/m/Y H:i:s', strtotime($s['date_vente']))
    ], ';');
}

// ── LIGNE TOTAL ─────────────────────────────────────────
fputcsv($output, [], ';');
fputcsv($output, [
    '', '', '', 'TOTAL GÉNÉRAL',
    number_format($grandTotal, 0, '', ' ') . ' FCFA', ''
], ';');

fclose($output);
exit();