<?php
require_once __DIR__ . "/config/admin_auth.php";
requireAdmin();
require_once "config/db.php";
$id = (int) ($_GET['id'] ?? 0);
$conn->query("UPDATE logo_master SET is_active = 0");
$stmt = $conn->prepare("UPDATE logo_master SET is_active = 1 WHERE id = ?");
$stmt->execute([$id]);
header("Location: logo_master.php");
exit;
?>
