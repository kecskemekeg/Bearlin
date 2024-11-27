<?php
include('db.php');
header('Content-Type: application/json');
$game_id = $_POST['game_id'];
global $pdo;
    try {

        $query = "SELECT username, msg, timestamp FROM messages WHERE game_id=:game_id ORDER BY timestamp DESC LIMIT 20";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }



echo json_encode(array_reverse($result));

?>
