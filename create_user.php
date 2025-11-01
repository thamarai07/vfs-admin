<?php
require_once "config/db.php";

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'staff';
    
    // Validation
    if (empty($username) || empty($password)) {
        $error = "Username and password are required!";
    } elseif (strlen($password) < 3) {
        $error = "Password must be at least 3 characters!";
    } else {
        try {
            // Check if username exists
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            
            if ($checkStmt->rowCount() > 0) {
                $error = "Username already exists!";
            } else {
                // Create user
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                
                $stmt = $conn->prepare("
                    INSERT INTO users (username, password_hash, email, full_name, role, is_active) 
                    VALUES (?, ?, ?, ?, ?, TRUE)
                ");
                
                $stmt->execute([$username, $passwordHash, $email, $full_name, $role]);
                
                $success = "User created successfully! You can now login.";
                
                // Clear form
                $username = $email = $full_name = "";
            }
        } catch (Exception $e) {
            $error = "Error creating user: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - VFS Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-2xl">
        <!-- Logo Section -->
        <div class="text-center mb-8">
            <div class="inline-block bg-white rounded-full p-6 shadow-2xl mb-4">
                <i class="fas fa-user-plus text-6xl text-purple-600"></i>
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">VFS Portal</h1>
            <p class="text-white text-opacity-90">Create New User Account</p>
        </div>

        <!-- Create User Card -->
        <div class="glass rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">User Registration</h2>
            
            <!-- Success Message -->
            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- Username -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-user mr-2 text-gray-500"></i>Username *
                        </label>
                        <input 
                            type="text" 
                            name="username" 
                            value="<?= htmlspecialchars($username ?? '') ?>"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 transition"
                            placeholder="Enter username"
                        >
                    </div>

                    <!-- Full Name -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-id-card mr-2 text-gray-500"></i>Full Name
                        </label>
                        <input 
                            type="text" 
                            name="full_name" 
                            value="<?= htmlspecialchars($full_name ?? '') ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 transition"
                            placeholder="Enter full name"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- Email -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-envelope mr-2 text-gray-500"></i>Email
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            value="<?= htmlspecialchars($email ?? '') ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 transition"
                            placeholder="user@example.com"
                        >
                    </div>

                    <!-- Role -->
                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">
                            <i class="fas fa-user-tag mr-2 text-gray-500"></i>Role *
                        </label>
                        <select 
                            name="role" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 transition"
                        >
                            <option value="admin">Admin</option>
                            <option value="staff" selected>Staff</option>
                        </select>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-semibold mb-2">
                        <i class="fas fa-lock mr-2 text-gray-500"></i>Password *
                    </label>
                    <input 
                        type="password" 
                        name="password" 
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 transition"
                        placeholder="Enter password (min 3 characters)"
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-info-circle"></i> Minimum 3 characters required
                    </p>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit"
                    class="w-full bg-gradient-to-r from-purple-600 to-blue-600 text-white font-bold py-3 rounded-lg hover:from-purple-700 hover:to-blue-700 transition transform hover:scale-105 shadow-lg"
                >
                    <i class="fas fa-user-plus mr-2"></i>Create User
                </button>
            </form>

            <!-- Quick Create Section -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h3 class="font-bold text-gray-700 mb-2 flex items-center gap-2">
                    <i class="fas fa-bolt text-yellow-500"></i>
                    Quick Create Admin
                </h3>
                <p class="text-sm text-gray-600 mb-3">Use these default credentials for quick setup:</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="bg-white p-2 rounded border">
                        <strong>Username:</strong> admin
                    </div>
                    <div class="bg-white p-2 rounded border">
                        <strong>Password:</strong> 123
                    </div>
                </div>
            </div>

            <!-- Footer Links -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600 mb-3">Already have an account?</p>
                <a 
                    href="login.php" 
                    class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold px-6 py-2 rounded-lg transition"
                >
                    <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
                </a>
            </div>
        </div>

        <!-- Copyright -->
        <p class="text-center text-white text-opacity-80 mt-6 text-sm">
            © 2024 VFS Portal. All rights reserved.
        </p>
    </div>
</body>
</html>