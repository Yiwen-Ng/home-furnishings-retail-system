<?php
session_start();
$conn = new mysqli("localhost", "root", "", "home_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errors = [];
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize and validate inputs
    $name = filter_var(trim($_POST["name"]), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST["password"]);
    $confirm = trim($_POST["confirm"]);

    // Name validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters long.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        $errors[] = "Name can only contain letters and spaces.";
    }

    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check for duplicate email
        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "This email is already registered.";
        }
        $stmt->close();
    }

    // Password validation
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/[0-9]/", $password) || !preg_match("/[A-Z]/", $password)) {
        $errors[] = "Password must contain at least one number and one uppercase letter.";
    }

    // Confirm password
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // Proceed with registration if no errors
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashed_password);

        if ($stmt->execute()) {
            $_SESSION["user"] = $name;
            $_SESSION["success"] = "Registration successful! Welcome, $name!";
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register Page</title>
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
                <h1><span>Create</span> your account.</h1>
                <p>Join us and enjoy a smoother shopping experience, fast checkout, and exclusive access to your order history.</p>
            </div>
        </div>
        <div class="login-right">
            <div class="form-box">
                <?php 
                if (!empty($errors)) {
                    foreach ($errors as $e) {
                        echo "<p class='error'>$e</p>";
                    }
                }
                if (!empty($success)) {
                    echo "<p class='success'>$success</p>";
                }
                ?>
                <form method="POST" action="">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Enter your full name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">

                    <label>Email</label>
                    <input type="email" name="email" required placeholder="Enter your email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">

                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter a password">

                    <label>Confirm Password</label>
                    <input type="password" name="confirm" required placeholder="Re-enter your password">

                    <button type="submit" name="register">Create Account</button>
                </form>
                <div class="divider"></div>
                <p class="no-account">Already have an account?</p>
                <a href="login.php" class="register-btn">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>