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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    switch ($_POST['action']) {
        case 'resumeGame':
            $game_id = getGameid($user_id);
            if(isGameidValid($game_id)){
                header("Location: lobby.php?code=$game_id");
                exit();
            }else{
                echo "No ongoing games";
            }
            break;
        case 'joinGame':
            if(isset($_POST['joinCode'])){
                $joinCode = strtoupper($_POST['joinCode']);
                if(strlen($joinCode)==6){
                    if(isGameidValid($joinCode)){
                        if(getGameid($user_id) == $joinCode){
                            echo "You are already part of this game, please press resume game to rejoin";
                        }else{

                            try{
                                $insertQuery = "REPLACE INTO players (player,game_id,leader, is_started,is_in_party) VALUES (:user_id,:game_id,0,0,0)";
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
                    }else{
                        echo "There are no games with this code";
                    }
                }else{
                    echo "Join code must be 6 characters";
                }
            }else{
                echo "No join code provided";
            }
            break;
        default:
            # code...
            break;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'create_game'){
    $game_id = $_POST['game_id'];
    $insertQuery = "REPLACE INTO players (player,game_id, leader,is_started,is_in_party ) VALUES (:user_id,:game_id,1,0,0)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
            $insertStmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $insertStmt->execute();
    

}

function getGameid($user_id){
    global $pdo;
    try{

        $query = "SELECT game_id FROM players WHERE player=:user_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC)[0]['game_id'];
    }catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

}

function isGameidValid($game_id){
    global $pdo;
    try{

        $query = "SELECT * FROM players WHERE game_id=:game_id AND leader=1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return (sizeof($res)==1);
    }catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
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
        <h2>Welcome to the game <?php echo $user_id?>!</h2>

        <!-- Button to create a game -->
        <button onclick="createGame()">Create Game</button>
        <!-- Button to resume a game -->
        
        <form method="post" action="">
            <button type="submit" name="action" value="resumeGame">Resume Game</button>
        </form>
        <!-- Join Game Form -->
        <h3>Join a Game</h3>
        <form method="post" action="">
            <label for="joinCode">Enter Invitation Code:</label>
            <input type="text" name="joinCode" required>
            <button type="submit" name="action" value="joinGame">Join Game</button>
        </form>

        <script>
            function createGame() {
                invitecode = generateInviteCode();
                $.post('index.php', {action:'create_game', game_id: invitecode});
                // Redirect to the lobby page with a generated invite code
                window.location.href = 'lobby.php?code=' + invitecode;
            }

            function resumeGame(){
                $.post('index.php', {action:'resumeGame'});
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