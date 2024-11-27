<?php
include('db.php');
global $pdo;
if (isset($_POST['username']) && isset($_POST['message']) && isset($_POST['game_id'])) {
    $game_id = $_POST['game_id'];
    $username = $_POST['username'];
    $message = $_POST['message'];

    
    try {

        $query = "INSERT INTO messages (game_id, username, msg) VALUES (:game_id, :username, :msg)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':msg', $message, PDO::PARAM_STR);
        $stmt->execute();

       

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

?>
