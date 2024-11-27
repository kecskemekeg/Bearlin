<?php
include('db.php');
header('Content-Type: application/json');
$game_id = $_POST['game_id'];
global $pdo;
    try {

        // Retrieve players in the lobby
        $query = "SELECT * FROM players WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        
        

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }



echo json_encode($data);

?>
