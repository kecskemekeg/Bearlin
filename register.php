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
    // Check if username already exists
    if (usernameExists($username,$pdo)) {
        $error_message = "Létező felhasználónév. Válassz másikat!";
        echo $error_message;
    }else{
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Insert user into database
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->execute([$username, $password]);

    // Redirect to login page
    header('Location: login.php');
    exit;
    }
}

function usernameExists($username, $pdo) {

    try {
        // Check if username already exists
        $query = "SELECT username FROM users WHERE username = :username";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        return ($stmt->rowCount() > 0);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/login.css">
    <title>Regisztráció</title>
</head>
<body>
<header>
        <h1>The Resistance: Avalon</h1>
    </header>
    <form method="post" action="">
    <h2>Regisztráció</h2>
        <label for="username">Felhasználónév:</label>
        <input type="text" name="username" required><br>

        <label for="password">Jelszó:</label>
        <input type="password" name="password" required><br>

        <button type="submit" value="Register">Regisztráció</button>
        <p><a href="login.php">Bejelentkezés</a></p>
    </form>
   
</body>
</html>
