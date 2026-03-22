<?php
session_start();
require_once "config/db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logo_name = $_POST['logo_name'];
    $target_dir = "assets/uploads/logo/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    $file_name = time() . "_" . basename($_FILES["logo"]["name"]);
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
        $conn->query("UPDATE logo_master SET is_active = 0");
        $stmt = $conn->prepare("INSERT INTO logo_master (logo_name, logo_path, is_active) VALUES (?, ?, 1)");
        $stmt->execute([$logo_name, $target_file]);
        header("Location: logo_master.php");
        exit;
    } else {
        echo "<script>alert('Logo upload failed');window.location.href='logo_master.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Logo</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex justify-center items-center min-h-screen">
  <form method="POST" enctype="multipart/form-data" class="bg-white shadow-lg rounded-xl p-8 w-96">
    <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Upload New Logo</h2>
    <label class="block text-gray-700 font-semibold mb-2">Logo Name</label>
    <input type="text" name="logo_name" class="border rounded-lg w-full px-3 py-2 mb-4" required>

    <label class="block text-gray-700 font-semibold mb-2">Choose Image</label>
    <input type="file" name="logo" class="w-full mb-4" accept="image/*" required>

    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white w-full py-2 rounded-lg font-semibold">Upload</button>
    <a href="logo_master.php" class="block text-center mt-3 text-gray-600 hover:underline">Back</a>
  </form>
</body>
</html>
