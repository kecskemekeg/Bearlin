<?php
include('db.php');
global $pdo;
if (isset($_POST['username'])) {
    
    $username = $_POST['username'];
       
    try {

        $query = "REPLACE INTO players (player,game_id,leader, is_started,is_in_party) VALUES (:username,'',0,0,0)";
        $stmt = $pdo->prepare($query);       
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

       

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

?>