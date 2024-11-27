<?php
// Include database connection
include('db.php');
session_start();
// Check if user is already logged in, redirect to home page
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Retrieve user from database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verify password
    if ($user && password_verify($password, $user['password'])) {
        echo "Sikeres bejelentkezés!";
        // After successful login
        $_SESSION['user_id'] = $user['username'];
        // Redirect to dashboard or another page
        header('Location: index.php');
    } else {
        echo "Érvénytelen felhasználónév vagy jelszó!";
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
    <title>Bejelentkezés</title>
</head>
<body>
    
    <header>
        <h1>The Resistance: Avalon</h1>
    </header>
    
    <form method="post" action="">
    <h2>Bejelentkezés</h2>
        <label for="username">Felhasználónév:</label>
        <input type="text" name="username" required><br>

        <label for="password">Jelszó:</label>
        <input type="password" name="password" required><br>

        <button type="submit" value="Login">Bejelentkezés</button>
        <p><a href="register.php">Regisztráció</a></p>
    </form>
    
</body>
</html>
