<?php
require_once "config/db.php";
$id = $_GET['id'];
$conn->query("UPDATE logo_master SET is_active = 0");
$stmt = $conn->prepare("UPDATE logo_master SET is_active = 1 WHERE id = ?");
$stmt->execute([$id]);
header("Location: logo_master.php");
exit;
?>
