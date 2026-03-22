<?php
header("Content-Type: application/json");
require_once "../config/db.php";

$stmt = $conn->prepare("SELECT id, name, slug, price_per_kg, discount_percent, unit, stock, image, category, description, is_featured FROM products WHERE is_active = 1 ORDER BY id DESC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  "status" => "success",
  "products" => $products
]);
?>