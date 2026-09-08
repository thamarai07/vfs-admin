<?php
session_start();
require_once "config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Remove mappings first (no FK cascade assumed), then the festival itself.
    $conn->prepare("DELETE FROM festival_products WHERE festival_id = ?")->execute([$id]);
    $conn->prepare("DELETE FROM festivals WHERE id = ?")->execute([$id]);
}

header("Location: festivals.php?msg=deleted");
exit;
