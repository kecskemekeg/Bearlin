<?php
include('db.php');
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // User is logged in
    $user_id = $_SESSION['user_id'];
    
} else {
    // User is not logged in, redirect to login page
    header("Location: login.php");
    exit();
}

// Check if the invite code is stored in the session
if (!isset($_SESSION['invite_code'])) {
    // Redirect to the main page if no invite code is found
    header("Location: index.php");
    exit();
}else{
    $invite_code = $_SESSION['invite_code'];
}

if(!isGameStarted($invite_code,$user_id)){
    header("Location: lobby.php");
    exit();
}

function getRole($user_id, $invite_code){
    global $pdo;
    try{

        $query = "SELECT player_role FROM games WHERE player=:user_id AND game_id = :inviteCode";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':inviteCode', $invite_code, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC)[0]['player_role'];
    }catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

}

function isGameStarted($game_id, $player){
    global $pdo;
    try {

        // Retrieve players in the lobby
        $query = "SELECT is_started FROM games WHERE game_id = :game_id AND player=:player";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->bindParam(':player', $player, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data[0]['is_started'];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game</title>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>
<h3>Logged in as: <?php echo $user_id?></h3>
<h3>Your role is <?php echo getRole($user_id,$invite_code)?></h3>
</body>
</html>
