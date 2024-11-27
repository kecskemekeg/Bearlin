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
if (!isset($_SESSION['game_id'])) {
    // Redirect to the main page if no invite code is found
    header("Location: index.php");
    exit();
}else{
    $game_id = $_SESSION['game_id'];
}

if(!isGameStarted($game_id,$user_id)){
    header("Location: lobby.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    switch ($_POST['action']) {
        case 'select_team':
            if (getGameState($game_id) != "selection" || getKing($game_id) != $user_id){
                break;
            }
            $team = $_POST['select_party_cb'];
            $current_round_size = getRoundList($game_id)[getCurrentRound($game_id)-1];
            if (sizeof($team) == $current_round_size) {
                resetTeam($game_id);
                resetVote($game_id);
                selectTeam($team, $game_id);
                resetMissionVote($game_id);
                setGameState($game_id, "voting");
            }else{
                $error = "Válassz ".$current_round_size." játékost a csapatba!";
            }
               
            
            break;
        case 'vote_yes':
            if (getGameState($game_id) != "voting" || getVote($user_id) != 0){
                break;
            }
            setVote($user_id, 1);
            if(isVoteDone($game_id)){
                selectNewKing($game_id);
                if(isVoteSuccess($game_id)){

                    setGameState($game_id, "mission");
                    setFailedVotes($game_id,0);
                }else{
                    setGameState($game_id, "selection");
                    increaseFailedVotes($game_id);
                    if(getFailedVotes($game_id) == 5){
                        setGameState($game_id, "failedvote");
                    }
                }
            }
            break;
        case 'vote_no':
            if (getGameState($game_id) != "voting" || getVote($user_id) != 0){
                break;
            }
            setVote($user_id, -1);
            if(isVoteDone($game_id)){
                selectNewKing($game_id);
                if(isVoteSuccess($game_id)){

                    setGameState($game_id, "mission");
                    setFailedVotes($game_id,0);
                }else{
                    setGameState($game_id, "selection");
                    increaseFailedVotes($game_id);
                    if(getFailedVotes($game_id) == 5){
                        setGameState($game_id, "failedvote");
                    }
                }
            }
            break;
        case 'success':
            if (getGameState($game_id) != "mission" || isInParty($user_id) != 1 || getMissionVote($user_id)!=0){
                break;
            }
            setMissionVote($user_id,1);
            if(isMissionVoteDone($game_id)){
                if(isMissionSuccess($game_id, getCurrentRound($game_id))){
                    
                    setRoundData(getCurrentRound($game_id),$game_id,"good");
                    if(isGameOver($game_id) == "good"){
                        setGameState($game_id, "assassin");
                    }else{
                        setGameState($game_id, "selection");
                    }
                }else{
                    setRoundData(getCurrentRound($game_id),$game_id,"evil");
                    if(isGameOver($game_id) == "evil"){
                        setGameState($game_id, "evil");
                    }else{
                        setGameState($game_id, "selection");
                    }
                }
                
            }
            break;
        case 'fail':
            if (getGameState($game_id) != "mission" || isInParty($user_id) != 1 || getMissionVote($user_id)!=0){
                break;
            }
            setMissionVote($user_id,-1);
            if(isMissionVoteDone($game_id)){
                if(isMissionSuccess($game_id, getCurrentRound($game_id))){
                    
                    setRoundData(getCurrentRound($game_id),$game_id,"good");
                    if(isGameOver($game_id) == "good"){
                        setGameState($game_id, "assassin");
                    }else{
                        setGameState($game_id, "selection");
                    }
                }else{
                    setRoundData(getCurrentRound($game_id),$game_id,"evil");
                    if(isGameOver($game_id) == "evil"){
                        setGameState($game_id, "evil");
                    }else{
                        setGameState($game_id, "selection");
                    }
                }
                
            }
            break;
        case 'assassin':
            if (getGameState($game_id) != "assassin" || getRole($user_id,$game_id) != "Orgyilkos"){
                break;
            }
            $kill_target = $_POST['kill_target'];
            if(getRole($kill_target, $game_id)=="Merlin"){
                setGameState($game_id, "evil");
            }else{
                setGameState($game_id,"good");
            }
            break;
        case 'main_menu':
            header("Location: index.php");
            exit();
        case 'chat':
            $msg = $_POST['message'];
            sendMessage($game_id, $user_id, $msg);
            break;
        default:
            # code...
            break;
    }
}

function sendMessage($game_id, $username, $msg){
    global $pdo;
    try {
        $insertQuery = "INSERT INTO messages (game_id, username, msg) VALUES (:game_id,:username,:msg)";
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $insertStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $insertStmt->bindParam(':msg', $msg, PDO::PARAM_STR);
        $insertStmt->execute();
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

}

function isGameOver($game_id){
    $evil = 0;
    $good = 0;
    for ($i=1; $i <= 5; $i++) { 
        if(getRoundData($i, $game_id)=="good"){
            $good++;
            if($good==3){
                return "good";
            }
        }else if(getRoundData($i, $game_id)=="evil"){
            $evil++;
            if($evil==3){
                return "evil";
            }
        }
    }
    return "no";
}

function increaseFailedVotes($game_id){
    $failed_votes = getFailedVotes($game_id);
    setFailedVotes($game_id, $failed_votes+1);
}

function isVoteSuccess($game_id){
    $votes = getPlayersAndVotes($game_id);
    $count = 0;
    foreach ($votes as $player => $vote) {
        $count += $vote;
    }
    return $count > 0;
}

function setFailedVotes($game_id, $failed_votes){
    global $pdo;
    try {

        $query = "UPDATE games SET failed_votes=:failed_votes WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->bindParam(':failed_votes', $failed_votes, PDO::PARAM_INT);
        $stmt->execute();

       

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getFailedVotes($game_id){
    global $pdo;
    try {

        $query = "SELECT failed_votes FROM games WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data[0]["failed_votes"];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getMissionVote($player){
    global $pdo;
    try {

        $query = "SELECT mission_vote FROM players WHERE player = :player";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':player', $player, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data[0]["mission_vote"];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function setMissionVote($player, $vote){
    global $pdo;
    try {

        $query = "UPDATE players SET mission_vote=:vote WHERE player = :player";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':player', $player, PDO::PARAM_STR);
        $stmt->bindParam(':vote', $vote, PDO::PARAM_INT);
        $stmt->execute();

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getMissionFails($game_id){
    $fails = 0;
    $players = getPlayers($game_id);
    foreach ($players as $player) {
        if(getMissionVote($player)== -1){
            $fails++;
        }
    }
    return $fails;
}

function isMissionSuccess($game_id, $current_round){
    $fails = getMissionFails($game_id);
    $players = getPlayers($game_id);
    if ($current_round == 4 && sizeof($players) >=7) {
        return $fails <2;
    }else{
        return $fails <1;
    }
}

function isMissionVoteDone($game_id){
    $players = getPlayersAndTeams($game_id);
    foreach ($players as $player => $in_team) {
        if ($in_team == 1 && getMissionVote($player) == 0) {
            return false;
        }
    }
    return true;
}

function resetMissionVote($game_id){
    $players = getPlayers($game_id);
    foreach ($players as $player) {
        setMissionVote($player,0);
    }
}

function resetVote($game_id){
    $players = getPlayers($game_id);
    foreach ($players as $player) {
        setVote($player,0);
    }
}

function isVoteDone($game_id){
    $votes = getPlayersAndVotes($game_id);
    foreach ($votes as $player => $vote) {
        if($vote == 0){
            return false;
        }
    }
    return true;
}

function setVote($player, $vote){
    global $pdo;
    try {

        $query = "UPDATE players SET vote=:vote WHERE player = :player";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':player', $player, PDO::PARAM_STR);
        $stmt->bindParam(':vote', $vote, PDO::PARAM_INT);
        $stmt->execute();

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getVote($player){
    global $pdo;
    try {

        $query = "SELECT vote FROM players WHERE player = :player";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':player', $player, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data[0]["vote"];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function setGameState($game_id, $gamestate){
    global $pdo;
    try {

        $query = "UPDATE games SET gamestate=:gamestate WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->bindParam(':gamestate', $gamestate, PDO::PARAM_STR);
        $stmt->execute();

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getGameState($game_id){
    global $pdo;
    try {

        $query = "SELECT gamestate FROM games WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data[0]["gamestate"];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getPlayersAndVotes($game_id){
    global $pdo;

    try {

        
        $query = "SELECT player, vote FROM players WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $players = array();
        $votes = array();
        foreach ($data as $d){
            array_push($players,$d['player']);
            array_push($votes,$d['vote']);
        } 
        return array_combine($players,$votes);

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getPlayersAndTeams($game_id){
    global $pdo;

    try {

        
        $query = "SELECT player, is_in_party FROM players WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $players = array();
        $teams = array();
        foreach ($data as $d){
            array_push($players,$d['player']);
            array_push($teams,$d['is_in_party']);
        } 
        return array_combine($players,$teams);

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function setTeam($player, $team){
    global $pdo;
    try {
        
            $insertQuery = "UPDATE players SET is_in_party=:team  WHERE player = :player";
                        $insertStmt = $pdo->prepare($insertQuery);
                        $insertStmt->bindParam(':team', $team, PDO::PARAM_INT);
                        $insertStmt->bindParam(':player', $player, PDO::PARAM_STR);
                        $insertStmt->execute();
                        
        
    } catch (PDOException $e){
        die("Connection failed: " . $e->getMessage());
    }
}
function selectTeam($team, $game_id){
    foreach ($team as $player) {
        setTeam($player, 1);
    }
}

function resetTeam($game_id){
    $players = getPlayers($game_id);
    foreach ($players as $player) {
        setTeam($player,0);
    }
}

function selectNewKing($game_id){
    global $pdo;
    $players = getPlayersAndKings($game_id);
    $nextking = false;
    foreach ($players as $player => $king) {
        if($king == 1){
            $nextking = true;
            setKing($player,0);
        }else{
            if($nextking==true){
                setKing($player,1);
                $nextking = false;
                break;
            }
        }
    }
    if($nextking == true){
        setKing(array_keys($players)[0],1);
    }
    
}

function setKing($player, $king){
    global $pdo;
    try {

        $query = "UPDATE players SET king=:king WHERE player = :player";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':player', $player, PDO::PARAM_STR);
        $stmt->bindParam(':king', $king, PDO::PARAM_INT);
        $stmt->execute();

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getKing($game_id){
    global $pdo;
    try {

        $query = "SELECT player FROM players WHERE game_id = :game_id AND king = 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data[0]["player"];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getRoundList($game_id){
    switch (sizeof(getPlayersAndRoles($game_id))) {
        case 5:
            return array(2,3,2,3,3);
        case 6:
            return array(2,3,4,3,4);
        case 7:
            return array(2,3,3,4,4);    
        case 8:
            return array(3,4,4,5,5);
        case 9:
            return array(3,4,4,5,5);
        case 10:
            return array(3,4,4,5,5);
        default:
            # code...
            break;
    }
}

function getCurrentRound($game_id){
    for ($i=1; $i <= 5; $i++) { 
        if (getRoundData($i,$game_id) != "evil" && getRoundData($i,$game_id) != "good") {
            return $i;
        }
    }
}

function getRole($user_id, $game_id){
    global $pdo;
    try{

        $query = "SELECT player_role FROM players WHERE player=:user_id AND game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC)[0]['player_role'];
    }catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

}

function getPlayers($game_id){
    global $pdo;

    try {

        
        $query = "SELECT player FROM players WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
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

function getAlignment($user_id, $game_id){
    $role = getRole($user_id, $game_id);
    if($role=="Merlin" || $role=="Knight" || $role=="Percival"){
        return "good";
    }else{
        return "evil";
    }
}

function getPlayersAndKings($game_id){
    global $pdo;

    try {

        
        $query = "SELECT player, king FROM players WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $players = array();
        $kings = array();
        foreach ($data as $d){
            array_push($players,$d['player']);
            array_push($kings,$d['king']);
        } 
        return array_combine($players,$kings);

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getPlayersAndRoles($game_id) {
    global $pdo;

    try {

        
        $query = "SELECT player, player_role FROM players WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $players = array();
        $roles = array();
        foreach ($data as $d){
            array_push($players,$d['player']);
            array_push($roles,$d['player_role']);
        } 
        return array_combine($players,$roles);

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getSpecialRoles($players){
    $result = array();
    foreach ($players as $player => $role) {
        if($role != "Knight" && $role !="Minion"){
            array_push($result, $role);
        }
    }
    return $result;
}

function getPlayersPOV($user_id, $game_id){
    $players = getPlayersAndRoles($game_id);
    $tmp_players = array();
    $tmp_roles = array();
    $special_roles = getSpecialRoles($players);
    $kings = array_values(getPlayersAndKings($game_id));
    $votes = array_values(getPlayersAndVotes($game_id));
    $teams = array_values(getPlayersAndTeams($game_id));
    if($players[$user_id]=="Knight"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "JÓ");
            }else{

                array_push($tmp_roles, "?");
            }
        }
        $result = array($tmp_players, $tmp_roles,  $votes,$kings, $teams);
        return $result;
    }
    if($players[$user_id]=="Oberon"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "GONOSZ");
            }else{

                array_push($tmp_roles, "?");
            }
        }
        $result = array($tmp_players, $tmp_roles,  $votes,$kings, $teams);
        return $result;
    }
    if($players[$user_id]=="Minion"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "GONOSZ");
            }else if($role == "Orgyilkos" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
                array_push($tmp_roles, "GONOSZ");
            }else if(in_array("Oberon", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "JÓ");
            }
        }
        $result = array($tmp_players, $tmp_roles,  $votes,$kings, $teams);
        return $result;
    }
    if($players[$user_id]=="Orgyilkos"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "GONOSZ");
            }else if($role == "Orgyilkos" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
                array_push($tmp_roles, "GONOSZ");
            }else if(in_array("Oberon", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "JÓ");
            }
        }
        $result = array($tmp_players, $tmp_roles,  $votes,$kings, $teams);
        return $result;
    }
    if($players[$user_id]=="Mordred"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "GONOSZ");
            }else if($role == "Orgyilkos" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
                array_push($tmp_roles, "GONOSZ");
            }else if(in_array("Oberon", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "JÓ");
            }
        }
        $result = array($tmp_players, $tmp_roles,  $votes,$kings, $teams);
        return $result;
    }
    if($players[$user_id]=="Morgana"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "GONOSZ");
            }else if($role == "Orgyilkos" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
                array_push($tmp_roles, "GONOSZ");
            }else if(in_array("Oberon", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "JÓ");
            }
        }
        $result = array($tmp_players, $tmp_roles,  $votes,$kings, $teams);
        return $result;
    }
    if($players[$user_id]=="Percival"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "JÓ");
            }else if($role == "Merlin"){
                if(in_array("Morgana", $special_roles)){
                    array_push($tmp_roles, "Merlin/Morgana");
                }else{

                    array_push($tmp_roles, $role);
                }
            }else if($role == "Morgana"){
                array_push($tmp_roles, "Merlin/Morgana");
            }else{
                array_push($tmp_roles, "?");
            }
        }
        $result = array($tmp_players, $tmp_roles,  $votes,$kings, $teams);
        return $result;
    }
    if($players[$user_id]=="Merlin"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "JÓ");
            }else if($role == "Orgyilkos" || $role == "Minion" || $role == "Oberon" || $role == "Morgana"){
                array_push($tmp_roles, "GONOSZ");
            }else if(in_array("Mordred", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "JÓ");
            }
        }
        
        $result = array($tmp_players, $tmp_roles,  $votes,$kings, $teams);
        return $result;
    }
}

function displayRole($role){
    switch ($role) {
        case "Knight":
            return "Artúr hű követője";
        case "Minion":
            return "Mordred bérence";
        
        default:
            return $role;
    }
}

function displayAlignment($al){
    switch ($al) {
        case 'good':
            return "JÓ";
        case 'evil':
            return "GONOSZ";
        default:
            return $al;
    }
}
function isGamestarted($game_id, $player){
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

function isInParty($player){
    global $pdo;
    try {

        
        $query = "SELECT is_in_party FROM players WHERE player=:player";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':player', $player, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data[0]['is_in_party'];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getRoundData($i, $game_id){
    global $pdo;
    try{
        $query = "SELECT * FROM games WHERE game_id = :game_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $res= $data[0]['round'.$i];
        if ($res == "evil"){
            return "evil";
        }else if($res == "good"){
            return "good";
        }else{
            $roundlist = getRoundList($game_id);
            return $roundlist[$i-1];
        }
    }catch (PDOException $e){
        die("Connection failed: " . $e->getMessage());
    }
}

function displayGamestate($gamestate){
    switch ($gamestate) {
        case 'voting':
            return "Szavazás fázis";    
        case 'selection':
            return "Csapat összeállítása fázis";
        case 'mission':
            return "Küldetés fázis";
        case 'assassin':
            return "Orgyilkos fázis";
        case 'evil':
            return "Játék vége";
        case 'good':
            return "Játék vége";
        default:
            # code...
            break;
    }
}

function displayRoundWinner($game_id){
    
    $fails = getMissionFails($game_id);
    if(isMissionSuccess($game_id, getCurrentRound($game_id))){
        return "A küldetés sikerrel járt. Balsiker: ".$fails;
    }else{
        return "A küldetés elbukott. Balsiker: ".$fails;
    }

}

function setRoundData($i, $game_id, $round_data){
    global $pdo;
    try{
        switch ($i) {
            case 1:
                $query = "UPDATE games SET round1=:round_data WHERE game_id = :game_id";
                break;
            case 2:
                $query = "UPDATE games SET round2=:round_data WHERE game_id = :game_id";
                break;
            case 3:
                $query = "UPDATE games SET round3=:round_data WHERE game_id = :game_id";
                break;
            case 4:
                $query = "UPDATE games SET round4=:round_data WHERE game_id = :game_id";
                break;
            case 5:
                $query = "UPDATE games SET round5=:round_data WHERE game_id = :game_id";
                break;
            default:
                # code...
                break;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->bindParam(':round_data', $round_data, PDO::PARAM_STR);
        $stmt->execute();

       
    }catch (PDOException $e){
        die("Connection failed: " . $e->getMessage());
    }
}

$players_and_data = getPlayersPOV($user_id, $game_id);
$players = getPlayers($game_id);
$roundlist = getRoundList($game_id);
$gamestate = getGameState($game_id);
$current_round = getCurrentRound($game_id);
$special_roles = getSpecialRoles(getPlayersAndRoles($game_id));
sort($special_roles);
$special_roles = implode(', ', $special_roles);

?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avalon</title>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <link rel="stylesheet" href="css/game.css">
</head>
<body>

<p>Bejelentkezve mint: <label id="username"><?php echo $user_id?></label></p>
<p>A játék kódja: <label id="game_id"><?php echo $game_id?></label></p>

<div class="header">
  
<h3>A szereped: <?php echo displayRole(getRole($user_id,$game_id)).' ('. displayAlignment(getAlignment($user_id,$game_id)).')'?></h3>
<h3 id="gamestate"></h3>
  </div>



<div class="row">
  
  
  <div class="column">
 

<h3>Játékosok</h3>
<table>
    <tr>
        <th>Játékos</th>
        <th>Szerep</th>
        
    </tr>
    <?php
    // Iterate over the rows of the table
    for ($i = 0; $i < count($players); $i++) {
        echo "<tr>";
        // Iterate over the columns of the table
        for ($j = 0; $j < 2; $j++) {
            if($j == 0 && $players_and_data[$j][$i] == $user_id){
                echo '<td style="color:#ea8007">' . $players_and_data[$j][$i] . '</td>';
            }else{
                echo "<td>" . $players_and_data[$j][$i] . "</td>";
            }
        }
        echo "<td id='playerdata$i'>"; //will be populated by JavaScript
        echo "</td>";
        echo "</tr>";
    }
    ?>
</table>
<p>Játékban lévő speciális karakterek: <?php echo $special_roles;?></p>

</div>
  
<div id="game-rounds" class="column">
<h3>Körök</h3>
<div id="round_result" hidden>
    <p id="round_result_label"></p>
    
</div>
    <div class="table-responsive">
        <table class="table table-primary" style="border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align:center; border: 1px solid black; padding: 5px;">1. kör</th>
                    <th style="text-align:center; border: 1px solid black; padding: 5px;">2. kör</th>
                    <th style="text-align:center; border: 1px solid black; padding: 5px;">3. kör</th>
                    <th style="text-align:center; border: 1px solid black; padding: 5px;">4. kör</th>
                    <th style="text-align:center; border: 1px solid black; padding: 5px;">5. kör</th>
                </tr>
            </thead>
            <tbody id="round-results">
                <!-- Rows will be populated by JavaScript -->
            </tbody>
        </table>
    </div>


<form id="select_party" method="post" action="" hidden>
<p>Válaszd ki, kiket szeretnél elvinni a küldetésre! (Magadat is választhatod)</p>
        <div>
            <?php foreach ($players as $player) {
                echo '<input type="checkbox" name="select_party_cb[]" value="'.$player.'" id="'.$player.'" ><label for="'.$player.'">'.$player.'</label><br>';
            }?>
        </div>
        <button type="submit" name="action" value="select_team">Csapat kiválasztása</button>
        <p class="error"><?php echo $error?> </p>   
</form>

<form id="vote" method="post" action="" hidden>
<p>Elfogadod ezt a csapat összeállítást? (A ⚔ ikonok jelölik kik vannak a csapatban)</p>
<div>
    <button type="submit" name="action" value="vote_yes">Elfogad</button>
    <button type="submit" name="action" value="vote_no">Elutasít</button>
</div>
</form>

<form id="mission" method="post" action="" hidden>
        <p>Sikerre viszed a küldetést?</p>
        <div>
            <button type="submit" name="action" value="success">Siker</button>
            <button id="fail_button" type="submit" name="action" value="fail" hidden>Balsiker</button>
        </div>
</form>
<form id="assassin" method="post" action="" hidden>
        <p>Öld meg Merlint</p>
        <div>
            <?php foreach ($players as $player) {
                echo '<input type="radio" name="kill_target" value="'.$player.'" id="'.$player.'" ><label for="'.$player.'">'.$player.'</label><br>';
            }?>
        </div>
        <button type="submit" name="action" value="assassin">Véglegesít</button>
        
</form>
<div id="good" hidden>
    <p>Gratulálok, Artúr hű követői legyőzték Mordred bérenceit!</p>
</div>
<div id="evil" hidden>
    <p>Mordred és a gonosz sötét erői győzedelmeskedtek!</p>
</div>

</div>

<div class="column">
<h3>Chat</h3>
<div id="chatbox"></div>
            
    <form method="post" action="" id="messageForm">
        <input type="text" id="message" name="message" placeholder="Írj egy üzenetet" required>
        <button type="submit" name="action" value="chat">Küldés</button>
    </form>
  
  
</div>

</div>

</div>  

<ul>
    <li>
        Válassz csapatot a küldetésre! (A köröknél látod, hány játékost kell választani az adott körben, a 👑 ikonnal jelzett játékos választ.)
    </li>
    <li>Szavazzátok meg a csapatot! (Többség dönt, a ⚔ ikonnal jelzett játékosok vannak a csapatban.)</li>
    <li>Ha egymás után 5-ször nem sikerül megszavazni a csapatot a gonoszok nyernek!</li>
    <li>A küldetésen lévők eldöntik, hogy sikerre viszik-e a küldetést (a jók csak sikerre tudnak szavazni).</li>
    <li>Egy balsiker esetén elbukik a küldetés, ez alól egyetlen kivétel a 4. kör hét vagy több játékos esetén, mert ott legalább két balsiker kell!</li>
    <li>3 sikertelen küldetés esetén a gonoszok nyernek!</li>
    <li>3 sikeres küldetés után az Orgyilkos megpróbálja megölni Merlint, ha sikerül akkor a gonoszok nyernek, ha nem, akkor a jók!</li>
    <li>Részletes szabályok itt, <a href="rulebook_hu.pdf" target="blank">magyar</a>, illetve <a
                href="rulebook.pdf" target="blank">angol</a> nyelven.</li>
</ul>


<div class="footer">
  <form method="post" action="">

                <button type="submit" name="action" value="main_menu">Főmenü</button>
            </form>
<form action="logout.php" method="post">
     <button type="submit">Kijelentkezés</button>
</form>
  </div>

<script src="game.js"></script>
<script>
    //https://www.sitepoint.com/quick-tip-persist-checkbox-checked-state-after-page-reload/
    var checkboxValues = JSON.parse(localStorage.getItem('checkboxValues')) || {};
    var $checkboxes = $("#select_party :checkbox");

    $checkboxes.on("change", function(){
    $checkboxes.each(function(){
        checkboxValues[this.id] = this.checked;
    });
    localStorage.setItem("checkboxValues", JSON.stringify(checkboxValues));
    });
    $.each(checkboxValues, function(key, value) {
    $("#" + key).prop('checked', value);
    });

    //keep scrollposition
    document.addEventListener("DOMContentLoaded", function(event) { 
        var scrollpos = localStorage.getItem('scrollpos');
        if (scrollpos) window.scrollTo(0, scrollpos);
    });

    window.onbeforeunload = function(e) {
        localStorage.setItem('scrollpos', window.scrollY);
    };

    function refreshPage() {
        setTimeout(function() {
            window.location.href = window.location.href;
        }, 5000); 
    }
    //refreshPage();
</script>

</body>
</html>
