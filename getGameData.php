<?php
include('db.php');
header('Content-Type: application/json');
$game_id = $_POST['game_id'];
global $pdo;
    try {

        $query = "SELECT * FROM games WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($data[0]);

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }