<?php
session_start();
if (isset($_SESSION['user'])) {
  header("Location: dashboard.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login | CRM Portal</title>
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
  <div class="login-container">
    <form action="verify_login.php" method="POST" class="login-box">
      <h2>CRM Admin Login</h2>
      <div class="input-group">
        <input type="text" name="username" required placeholder="Username">
      </div>
      <div class="input-group">
        <input type="password" name="password" required placeholder="Password">
      </div>
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>
