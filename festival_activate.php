<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$to = (int)($_GET['to'] ?? 1) === 1 ? 1 : 0;

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE festivals SET is_active = ? WHERE id = ?");
    $stmt->execute([$to, $id]);
}

header("Location: festivals.php?msg=status");
exit;
