<?php
// public/cambiar_tema.php
session_start();
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$tema_actual = $_SESSION['tema'] ?? 'claro';
$nuevo_tema = ($tema_actual === 'oscuro') ? 'claro' : 'oscuro';

// Actualizar en BD
$stmt = $pdo->prepare("UPDATE admins SET tema = ? WHERE id = ?");
$stmt->execute([$nuevo_tema, $admin_id]);

// Actualizar en sesión
$_SESSION['tema'] = $nuevo_tema;

// Volver a la página desde donde se hizo el cambio
$origen = $_POST['origen'] ?? '';
if ($origen && strpos($origen, "\n") === false && strpos($origen, "\r") === false) {
    header("Location: " . $origen);
} else {
    header('Location: dashboard.php');
}
exit;
