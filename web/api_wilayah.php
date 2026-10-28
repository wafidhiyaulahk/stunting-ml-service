<?php
/**
 * Endpoint pembantu dropdown alamat bertingkat.
 *   api_wilayah.php?kelurahan=Kedaung          -> daftar RW
 *   api_wilayah.php?kelurahan=Kedaung&rw=002   -> daftar RT
 */
require_once __DIR__ . '/helpers/fungsi.php';
header('Content-Type: application/json');

$kelurahan = $_GET['kelurahan'] ?? '';
$rw        = $_GET['rw'] ?? '';

if ($kelurahan === '') {
    echo json_encode([]);
    exit;
}

if ($rw === '') {
    $stmt = $pdo->prepare('SELECT DISTINCT rw FROM wilayah WHERE kelurahan = ? ORDER BY rw');
    $stmt->execute([$kelurahan]);
} else {
    $stmt = $pdo->prepare('SELECT rt FROM wilayah WHERE kelurahan = ? AND rw = ? ORDER BY rt');
    $stmt->execute([$kelurahan, $rw]);
}

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
