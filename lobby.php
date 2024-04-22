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

// Check if an invite code is provided
if (isset($_GET['code'])) {
    $inviteCode = $_GET['code'];

    // Store the invite code in the session
    $_SESSION['invite_code'] = $inviteCode;
    if (isGameStarted($inviteCode, $user_id)){
        header("Location: game.php");
        exit();
    }
} else {
    // Redirect to the main page if no invite code is provided
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    switch ($_POST['action']) {
        case 'start_game':
            $leader = getLeader();
    
            if($user_id == $leader){
                
                $roles = $_POST['roles'];
                startGame($roles, $inviteCode);
            }else{
                echo "You are not the leader!";
            }
            
            break;
        
        default:
            # code...
            break;
    }
}

function getLeader(){
    global $pdo;
    $query = "SELECT player FROM players WHERE game_id = :inviteCode AND leader = 1";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':inviteCode', $_SESSION['invite_code'], PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC)[0]['player'];
}

function startGame($roles, $inviteCode){
    $players = getPlayersInLobby($inviteCode);
    $player_size = sizeof($players);
    if($player_size<5){
        echo "Minimum 5 player is required!";
    }else
    if($player_size>10){
        echo "Maximum 10 players can play!";
    }else{

        shuffle($players);
        shuffle($roles);
        global $pdo;
        $content = array_combine($players, $roles);
        $first = true;
        try{
    
            foreach ($content as $player => $role){
                if($first){
                    $insertQuery = "UPDATE players SET king=1, player_role=:player_role, is_started=1 WHERE game_id = :inviteCode AND player = :player";
                    $insertStmt = $pdo->prepare($insertQuery);
                    $insertStmt->bindParam(':inviteCode', $inviteCode, PDO::PARAM_STR);
                    $insertStmt->bindParam(':player', $player, PDO::PARAM_STR);
                    $insertStmt->bindParam(':player_role', $role, PDO::PARAM_STR);
                    $insertStmt->execute();
                    $first = false;
                }else{
    
                    $insertQuery = "UPDATE players SET king=0, player_role=:player_role, is_started=1  WHERE game_id = :inviteCode AND player = :player";
                        $insertStmt = $pdo->prepare($insertQuery);
                        $insertStmt->bindParam(':inviteCode', $inviteCode, PDO::PARAM_STR);
                        $insertStmt->bindParam(':player', $player, PDO::PARAM_STR);
                        $insertStmt->bindParam(':player_role', $role, PDO::PARAM_STR);
                        $insertStmt->execute();
                }
            }
            $insertQuery = "REPLACE INTO games (game_id, player_size, gamestate) VALUES (:game_id, :player_size, 'selection')";
                $insertStmt = $pdo->prepare($insertQuery);
                $insertStmt->bindParam(':game_id', $inviteCode, PDO::PARAM_STR);
                $insertStmt->bindParam(':player_size', $player_size, PDO::PARAM_STR);
                $insertStmt->execute();
        }catch (PDOException $e){
            die("Connection failed: " . $e->getMessage());
        }
        header("Location: game.php");
        exit();
    }
}



// Function to get the list of players in the lobby
function getPlayersInLobby($inviteCode) {
    global $pdo;

    try {

        // Retrieve players in the lobby
        $query = "SELECT player FROM players WHERE game_id = :inviteCode";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':inviteCode', $inviteCode, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $players = array();
        foreach ($data as $d){
            array_push($players,$d['player']);
        } 
        return $players;

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function isGameStarted($game_id, $player){
    global $pdo;
    try {

        // Retrieve players in the lobby
        $query = "SELECT is_started FROM players WHERE game_id = :game_id AND player=:player";
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

// Retrieve the list of players in the lobby
$playersInLobby = getPlayersInLobby($inviteCode);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Lobby</title>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>
    <h3>Logged in as: <?php echo $user_id?></h3>
    <h2>Game Lobby made by: <?php echo getLeader()?></h2>

    <p>Invite Code: <?php echo $inviteCode; ?></p>

    <!-- Players List -->
    <h3>Players in the Lobby</h3>
    <ul id="playersList">
        <?php foreach ($playersInLobby as $player) : ?>
            <li><?php echo $player; ?></li>
        <?php endforeach; ?>
    </ul>

    <!-- Settings Form -->
    <h3>Game Settings</h3>
    
        <label for="numPlayers">Number of Players (min 5 - max 10): <?php echo sizeof($playersInLobby); ?></label>
        <br>
        <label>Special Characters:</label>
        <input type="checkbox" name="specialChars[]" value="Assassin" checked="true"> Assassin
        <input type="checkbox" name="specialChars[]" value="Merlin" checked="true"> Merlin
        <input type="checkbox" name="specialChars[]" value="Oberon"> Oberon
        <input type="checkbox" name="specialChars[]" value="Percival"> Percival
        <input type="checkbox" name="specialChars[]" value="Mordred"> Mordred
        <input type="checkbox" name="specialChars[]" value="Morgana"> Morgana
        <!-- Add more checkboxes for additional characters -->
        <br><br>
        <form method="post" action="">
        <button onclick="startGame()" type="submit" name="action" value="start_game">Start Game</button>
        </form>
        
    

    <script>
        function getSpecialChars(){
            var checkboxes = document.getElementsByName("specialChars[]");
            var checkedCheckboxes = [];
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].checked) {
                    checkedCheckboxes.push(checkboxes[i].value);
                }
            }
            return checkedCheckboxes;
        }

        function getRoles(){
            special_chars=getSpecialChars();
            good = 0;
            evil = 0;
            minions = 0;
            knights = 0;
            if(special_chars.includes("Assassin")){
                evil++;
            }
            if(special_chars.includes("Oberon")){
                evil++;
            }
            if(special_chars.includes("Mordred")){
                evil++;
            }
            if(special_chars.includes("Morgana")){
                evil++;
            }
            if(special_chars.includes("Merlin")){
                good++;
            }
            if(special_chars.includes("Percival")){
                good++;
            }
            num = <?php echo sizeof($playersInLobby)?>;
            switch (num) {
                case 5:
                    knights = 3-good;
                    minions = 2-evil;
                    break;
                case 6:
                    knights = 4-good;
                    minions = 2-evil;
                    break;
                case 7:
                    knights = 4-good;
                    minions = 3-evil;
                    break;
                case 8:
                    knights = 5-good;
                    minions = 3-evil;
                    break;
                case 9:
                    knights = 6-good;
                    minions = 3-evil;
                    break;
                case 10:
                    knights = 6-good;
                    minions = 4-evil;
                    break;
            
                default:
                    break;
            }
            char_roles = special_chars;
            for (let index = 0; index < knights; index++) {
                char_roles.push("Knight");
                
            }
            for (let index = 0; index < minions; index++) {
                char_roles.push("Minion");
                
            }
            console.log(char_roles);
            return char_roles;

        }
        

        
        function startGame() {
            roles =getRoles();
            $.post('lobby.php?code=<?php echo $inviteCode; ?>', {action:'start_game',roles:roles});
        }
    </script>
</body>
</html>
