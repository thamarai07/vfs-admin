<?php
require_once "config/db.php";
$id = $_GET['id'];
$stmt = $conn->prepare("DELETE FROM logo_master WHERE id = ?");
$stmt->execute([$id]);
header("Location: logo_master.php");
exit;
?>
