<?php
session_start();
require 'db.php';

// Basic rate limit (3 failed attempts → 1 minute lock)
if (!isset($_SESSION['attempts'])) $_SESSION['attempts'] = 0;
if (!isset($_SESSION['last_attempt_time'])) $_SESSION['last_attempt_time'] = time();

if ($_SESSION['attempts'] >= 3 && (time() - $_SESSION['last_attempt_time']) < 60) {
  die("Too many failed attempts. Please try again after 1 minute.");
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username && $password) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->execute([$username]);
  $user = $stmt->fetch();

  if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['user'] = $user['username'];
    $_SESSION['attempts'] = 0;
    header("Location: dashboard.php");
    exit;
  } else {
    $_SESSION['attempts']++;
    $_SESSION['last_attempt_time'] = time();
    echo "<script>alert('Invalid credentials');window.location='login.php';</script>";
  }
} else {
  echo "<script>alert('All fields are required');window.location='login.php';</script>";
}
