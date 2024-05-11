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

if(getLeader()==""){
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    switch ($_POST['action']) {
        case 'start_game':
            $leader = getLeader();
    
            if($user_id == $leader){
                
                $spec = $_POST['specialChars'];
                $roles = getRoles($spec,$inviteCode);
                if(!$roles){
                    echo "Túl sok speciális karakter!";
                    break;
                }
                startGame($roles, $inviteCode);
            }else{
                echo "Nem te vagy a lobbi létrehozója!";
            }
            
            break;
        case 'main_menu':
            header("Location: index.php");
            exit();
        default:
            # code...
            break;
    }
}

function getRoles($spec, $inviteCode){

    $good = 0;
    $evil = 0;
    $minions = 0;
    $knights = 0;
    if(in_array("Orgyilkos",$spec)){
        $evil++;
    }
    if(in_array("Oberon",$spec)){
        $evil++;
    }
    if(in_array("Mordred",$spec)){
        $evil++;
    }
    if(in_array("Morgana",$spec)){
        $evil++;
    }
    if(in_array("Merlin",$spec)){
        $good++;
    }
    if(in_array("Percival",$spec)){
        $good++;
    }
    $num = sizeof(getPlayersInLobby($inviteCode));
    switch ($num) {
        case 5:
            $knights = 3-$good;
            $minions = 2-$evil;
            break;
        case 6:
            $knights = 4-$good;
            $minions = 2-$evil;
            break;
        case 7:
            $knights = 4-$good;
            $minions = 3-$evil;
            break;
        case 8:
            $knights = 5-$good;
            $minions = 3-$evil;
            break;
        case 9:
            $knights = 6-$good;
            $minions = 3-$evil;
            break;
        case 10:
            $knights = 6-$good;
            $minions = 4-$evil;
            break;
    
        default:
            break;
    }
    $char_roles = $spec;
    if($minions < 0){
        return false;
    }
    for ($index = 0; $index < $knights; $index++) {
        array_push($char_roles,"Knight");
        
    }
    for ($index = 0; $index < $minions; $index++) {
        array_push($char_roles,"Minion");
        
    }
    
    return $char_roles;

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
        echo "Legalább 5 játékos szükséges!";
    }else
    if($player_size>10){
        echo "Maximum 10 játékos játszhat!";
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


$playersInLobby = getPlayersInLobby($inviteCode);


?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobbi</title>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>
    <h3>Bejelentkezve mint: <?php echo $user_id?></h3>
    <h2>Lobbi létrehozója: <?php echo getLeader()?></h2>

    <p>Invitációs kód: <?php echo $inviteCode; ?></p>

    <!-- Players List -->
    <h3>A lobbiban lévő játékosok</h3>
    <ul id="playersList">
        <?php foreach ($playersInLobby as $player) : ?>
            <li><?php echo $player; ?></li>
        <?php endforeach; ?>
    </ul>

    <label for="numPlayers">Játékosok száma (min 5 - max 10): <?php echo sizeof($playersInLobby); ?></label>
    <form method="post" action="" <?php if (getLeader() != $user_id) echo 'hidden';?>>
        <!-- Settings Form -->
        <h3>Beállítások</h3>
        <br>
        
        <label>Speciális szerepek:</label>
        <div id="checkbox-container">
    
            <input id="option1" type="checkbox" name="specialChars[]" value="Orgyilkos" checked="true"> Orgyilkos
            <input id="option2" type="checkbox" name="specialChars[]" value="Merlin" checked="true"> Merlin
            <input id="option3" type="checkbox" name="specialChars[]" value="Oberon"> Oberon
            <input id="option4" type="checkbox" name="specialChars[]" value="Percival"> Percival
            <input id="option5" type="checkbox" name="specialChars[]" value="Mordred"> Mordred
            <input id="option6" type="checkbox" name="specialChars[]" value="Morgana"> Morgana
        </div>
        
        <br><br>
        
        <button type="submit" name="action" value="start_game">Játék indítása</button>
        
    </form>
            <form method="post" action="">

                <button type="submit" name="action" value="main_menu">Főmenü</button>
            </form>
        <form action="logout.php" method="post">
            <button type="submit">Kijelentkezés</button>
        </form>
        
    

    <script>
        
        //https://www.sitepoint.com/quick-tip-persist-checkbox-checked-state-after-page-reload/
        var checkboxValues = JSON.parse(localStorage.getItem('checkboxValues')) || {};
        var $checkboxes = $("#checkbox-container :checkbox");

        $checkboxes.on("change", function(){
        $checkboxes.each(function(){
            checkboxValues[this.id] = this.checked;
        });
        localStorage.setItem("checkboxValues", JSON.stringify(checkboxValues));
        });
        $.each(checkboxValues, function(key, value) {
        $("#" + key).prop('checked', value);
        });

        function refreshPage() {
            setTimeout(function() {
                window.location.href = window.location.href;
            }, 5000); 
        }
        refreshPage();
    </script>
</body>
</html>
