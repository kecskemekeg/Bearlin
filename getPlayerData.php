<?php
include('db.php');
header('Content-Type: application/json');
$user_id = $_POST['user_id'];
global $pdo;
    try {

        // Retrieve players in the lobby
        $query = "SELECT * FROM players WHERE player = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        
        

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }



echo json_encode($data);

?>