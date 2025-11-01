<?php
$password = "123";
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "<h2>Password Hash Generator</h2>";
echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
echo "<p><strong>Hash:</strong></p>";
echo "<textarea style='width: 100%; height: 100px; font-family: monospace;'>" . $hash . "</textarea>";
echo "<br><br>";
echo "<p>Copy this hash and use it in your SQL query!</p>";
?>