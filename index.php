<?php
include('db.php');
session_start();
// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in
    $user_id = $_SESSION['user_id'];
    echo "Welcome, User $user_id!";
} else {
    // User is not logged in, redirect to login page
    header("Location: login.php");
    exit();
}

// Handle joining a lobby if an invitation code is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['joinCode'])) {
    $joinCode = strtoupper($_POST['joinCode']); // Convert to uppercase for consistency
    try{

        global $pdo;
        global $user_id;
        $insertQuery = "REPLACE INTO games (player,game_id,leader, is_started) VALUES (:user_id,:game_id,0,0)";
                $insertStmt = $pdo->prepare($insertQuery);
                $insertStmt->bindParam(':game_id', $joinCode, PDO::PARAM_STR);
                $insertStmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
                $insertStmt->execute();
    }catch (PDOException $e){
        die("Connection failed: " . $e->getMessage());
    }
    // Redirect to the lobby page with the invitation code
    header("Location: lobby.php?code=$joinCode");
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'make_leader'){
    global $pdo;
    global $user_id;
    $game_id = $_POST['game_id'];
    $insertQuery = "REPLACE INTO games (player,game_id, leader,is_started ) VALUES (:user_id,:game_id,1,0)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
            $insertStmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $insertStmt->execute();

}
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bearlin</title>
        <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    </head>

    <body>
        <h2>Welcome to the Game!</h2>

        <!-- Button to create a game -->
        <button onclick="createGame()">Create Game</button>
        <!-- Join Game Form -->
        <h3>Join a Game</h3>
        <form method="post" action="">
            <label for="joinCode">Enter Invitation Code:</label>
            <input type="text" name="joinCode" required>
            <button type="submit">Join Game</button>
        </form>

        <script>
            function createGame() {
                invitecode = generateInviteCode();
                $.post('index.php', {action:'make_leader', game_id: invitecode});
                // Redirect to the lobby page with a generated invite code
                window.location.href = 'lobby.php?code=' + invitecode;
            }

            function generateInviteCode() {
                // Replace this with your logic to generate a unique invite code
                // For simplicity, using a random 6-character alphanumeric code
                return Math.random().toString(36).substring(2, 8).toUpperCase();
            }
        </script>
        <form action="logout.php" method="post">
            <button type="submit">Logout</button>
        </form>
    </body>

    </html>