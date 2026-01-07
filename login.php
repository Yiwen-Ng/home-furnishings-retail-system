<?php
session_start();
include 'db_config.php'; // Use PDO from db_config.php

$errors = [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Validate inputs
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT user_id, full_name, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION["user"] = [
                        "user_id" => $user['user_id'],
                        "full_name" => $user['full_name'],
                        "email" => $email,
                        "role" => $user['role']
                    ];
                    if ($user['role'] === 'admin') {
                        header("Location: admin_dashboard.php");
                    } else {
                        header("Location: homepage.php");
                    }
                    exit();
                } else {
                    $errors[] = "Invalid password.";
                }
            } else {
                $errors[] = "User not found. Try Again.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Clear success message after displaying it
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login Page</title>
    <link rel="stylesheet" href="login.css">
    <style>
        .error {
            color: #d32f2f;
            font-size: 0.9em;
            margin: 5px 0;
            background-color: #ffebee;
            padding: 8px;
            border-radius: 4px;
        }
        .success {
            color: #2e7d32;
            font-size: 0.9em;
            margin: 5px 0;
            background-color: #e8f5e9;
            padding: 8px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-left">
            <a href="homepage.php" class="back-button">← Back</a>
            <div class="left-content">
                <h1><span>Login</span> to your account.</h1>
                <p>Enjoy a smoother shopping experience across our site. Access order history, checkout faster, and more.</p>
            </div>
        </div>
        <div class="login-right">
            <div class="form-box">
                <?php 
                if (!empty($success)) {
                    echo "<p class='success'>" . htmlspecialchars($success) . "</p>";
                }
                if (!empty($errors)) {
                    foreach ($errors as $e) {
                        echo "<p class='error'>" . htmlspecialchars($e) . "</p>";
                    }
                }
                ?>
                <form method="POST" action="">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="Enter your email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter your password">
                    
                    <button type="submit" name="login">Continue</button>
                </form>
                <div class="divider"></div>
                <p class="no-account">Don't have an account yet?</p>
                <a href="register.php" class="register-btn">I'm buying for my home</a>
                <a href="register.php" class="register-btn">I'm buying for my business</a>
            </div>
        </div>
    </div>
</body>
</html>