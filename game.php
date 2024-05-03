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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])){
    switch ($_POST['action']) {
        case 'select_team':
            if (getGameState($invite_code) != "selection" || getKing($invite_code) != $user_id){
                break;
            }
            $team = $_POST['select_party_cb'];
            $current_round_size = getRoundList($invite_code)[getCurrentRound($invite_code)-1];
            if (sizeof($team) == $current_round_size) {
                resetTeam($invite_code);
                resetVote($invite_code);
                selectTeam($team, $invite_code);
                selectNewKing($invite_code);
                resetMissionVote($invite_code);
                setGameState($invite_code, "voting");
                echo "Csapat kiválasztva";
            }else{
                echo "Válassz ".$current_round_size." játékost a csapatba!";
            }
               
            
            break;
        case 'vote_yes':
            if (getGameState($invite_code) != "voting" || getVote($user_id) != 0){
                break;
            }
            setVote($user_id, 1);
            if(isVoteDone($invite_code)){
                selectNewKing($invite_code);
                if(isVoteSuccess($invite_code)){

                    setGameState($invite_code, "mission");
                    setFailedVotes($invite_code,0);
                }else{
                    setGameState($invite_code, "selection");
                    increaseFailedVotes($invite_code);
                    if(getFailedVotes($invite_code) == 5){
                        setGameState($invite_code, "failedvote");
                    }
                }
            }
            break;
        case 'vote_no':
            if (getGameState($invite_code) != "voting" || getVote($user_id) != 0){
                break;
            }
            setVote($user_id, -1);
            if(isVoteDone($invite_code)){
                selectNewKing($invite_code);
                if(isVoteSuccess($invite_code)){

                    setGameState($invite_code, "mission");
                    setFailedVotes($invite_code,0);
                }else{
                    setGameState($invite_code, "selection");
                    increaseFailedVotes($invite_code);
                    if(getFailedVotes($invite_code) == 5){
                        setGameState($invite_code, "failedvote");
                    }
                }
            }
            break;
        case 'success':
            if (getGameState($invite_code) != "mission" || isInParty($user_id) != 1 || getMissionVote($user_id)!=0){
                break;
            }
            setMissionVote($user_id,1);
            if(isMissionVoteDone($invite_code)){
                if(isMissionSuccess($invite_code, getCurrentRound($invite_code))){
                    
                    setRoundData(getCurrentRound($invite_code),$invite_code,"good");
                    if(isGameOver($invite_code) == "good"){
                        setGameState($invite_code, "assassin");
                    }else{
                        setGameState($invite_code, "selection");
                    }
                }else{
                    setRoundData(getCurrentRound($invite_code),$invite_code,"evil");
                    if(isGameOver($invite_code) == "evil"){
                        setGameState($invite_code, "evil");
                    }else{
                        setGameState($invite_code, "selection");
                    }
                }
                
            }
            break;
        case 'fail':
            if (getGameState($invite_code) != "mission" || isInParty($user_id) != 1 || getMissionVote($user_id)!=0){
                break;
            }
            setMissionVote($user_id,-1);
            if(isMissionVoteDone($invite_code)){
                if(isMissionSuccess($invite_code, getCurrentRound($invite_code))){
                    
                    setRoundData(getCurrentRound($invite_code),$invite_code,"good");
                    if(isGameOver($invite_code) == "good"){
                        setGameState($invite_code, "assassin");
                    }else{
                        setGameState($invite_code, "selection");
                    }
                }else{
                    setRoundData(getCurrentRound($invite_code),$invite_code,"evil");
                    if(isGameOver($invite_code) == "evil"){
                        setGameState($invite_code, "evil");
                    }else{
                        setGameState($invite_code, "selection");
                    }
                }
                
            }
            break;
        case 'assassin':
            if (getGameState($invite_code) != "assassin" || getRole($user_id,$invite_code) != "Assassin"){
                break;
            }
            $kill_target = $_POST['kill_target'];
            if(getRole($kill_target, $invite_code)=="Merlin"){
                setGameState($invite_code, "evil");
            }else{
                setGameState($invite_code,"good");
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

function isGameOver($invite_code){
    $evil = 0;
    $good = 0;
    for ($i=1; $i <= 5; $i++) { 
        if(getRoundData($i, $invite_code)=="good"){
            $good++;
            if($good==3){
                return "good";
            }
        }else if(getRoundData($i, $invite_code)=="evil"){
            $evil++;
            if($evil==3){
                return "evil";
            }
        }
    }
    return "no";
}

function increaseFailedVotes($invite_code){
    $failed_votes = getFailedVotes($invite_code);
    setFailedVotes($invite_code, $failed_votes+1);
}

function isVoteSuccess($invite_code){
    $votes = getPlayersAndVotes($invite_code);
    $count = 0;
    foreach ($votes as $player => $vote) {
        $count += $vote;
    }
    return $count > 0;
}

function setFailedVotes($invite_code, $failed_votes){
    global $pdo;
    try {

        $query = "UPDATE games SET failed_votes=:failed_votes WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
        $stmt->bindParam(':failed_votes', $failed_votes, PDO::PARAM_INT);
        $stmt->execute();

       

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getFailedVotes($invite_code){
    global $pdo;
    try {

        $query = "SELECT failed_votes FROM games WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
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

function getMissionFails($invite_code){
    $fails = 0;
    $players = getPlayers($invite_code);
    foreach ($players as $player) {
        if(getMissionVote($player)== -1){
            $fails++;
        }
    }
    return $fails;
}

function isMissionSuccess($invite_code, $current_round){
    $fails = getMissionFails($invite_code);
    $players = getPlayers($invite_code);
    if ($current_round == 4 && sizeof($players) >=7) {
        return $fails <2;
    }else{
        return $fails <1;
    }
}

function isMissionVoteDone($invite_code){
    $players = getPlayersAndTeams($invite_code);
    foreach ($players as $player => $in_team) {
        if ($in_team == 1 && getMissionVote($player) == 0) {
            return false;
        }
    }
    return true;
}

function resetMissionVote($invite_code){
    $players = getPlayers($invite_code);
    foreach ($players as $player) {
        setMissionVote($player,0);
    }
}

function resetVote($invite_code){
    $players = getPlayers($invite_code);
    foreach ($players as $player) {
        setVote($player,0);
    }
}

function isVoteDone($invite_code){
    $votes = getPlayersAndVotes($invite_code);
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

function setGameState($invite_code, $gamestate){
    global $pdo;
    try {

        $query = "UPDATE games SET gamestate=:gamestate WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
        $stmt->bindParam(':gamestate', $gamestate, PDO::PARAM_STR);
        $stmt->execute();

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getGameState($invite_code){
    global $pdo;
    try {

        $query = "SELECT gamestate FROM games WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data[0]["gamestate"];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getPlayersAndVotes($invite_code){
    global $pdo;

    try {

        // Retrieve players in the lobby
        $query = "SELECT player, vote FROM players WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
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

function getPlayersAndTeams($invite_code){
    global $pdo;

    try {

        // Retrieve players in the lobby
        $query = "SELECT player, is_in_party FROM players WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
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
function selectTeam($team, $invite_code){
    foreach ($team as $player) {
        setTeam($player, 1);
    }
}

function resetTeam($invite_code){
    $players = getPlayers($invite_code);
    foreach ($players as $player) {
        setTeam($player,0);
    }
}

function selectNewKing($invite_code){
    global $pdo;
    $players = getPlayersAndKings($invite_code);
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

function getKing($invite_code){
    global $pdo;
    try {

        $query = "SELECT player FROM players WHERE game_id = :invite_code AND king = 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data[0]["player"];

    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function getRoundList($invite_code){
    switch (sizeof(getPlayersAndRoles($invite_code))) {
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

function getRole($user_id, $invite_code){
    global $pdo;
    try{

        $query = "SELECT player_role FROM players WHERE player=:user_id AND game_id = :inviteCode";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':inviteCode', $invite_code, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC)[0]['player_role'];
    }catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

}

function getPlayers($invite_code){
    global $pdo;

    try {

        // Retrieve players in the lobby
        $query = "SELECT player FROM players WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
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

function getAlignment($user_id, $invite_code){
    $role = getRole($user_id, $invite_code);
    if($role=="Merlin" || $role=="Knight" || $role=="Percival"){
        return "good";
    }else{
        return "evil";
    }
}

function getPlayersAndKings($invite_code){
    global $pdo;

    try {

        // Retrieve players in the lobby
        $query = "SELECT player, king FROM players WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
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

function getPlayersAndRoles($invite_code) {
    global $pdo;

    try {

        // Retrieve players in the lobby
        $query = "SELECT player, player_role FROM players WHERE game_id = :invite_code";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':invite_code', $invite_code, PDO::PARAM_STR);
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

function getPlayersPOV($user_id, $invite_code){
    $players = getPlayersAndRoles($invite_code);
    $tmp_players = array();
    $tmp_roles = array();
    $special_roles = getSpecialRoles($players);
    $kings = array_values(getPlayersAndKings($invite_code));
    $votes = array_values(getPlayersAndVotes($invite_code));
    $teams = array_values(getPlayersAndTeams($invite_code));
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
            }else if($role == "Assassin" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
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
    if($players[$user_id]=="Assassin"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, "GONOSZ");
            }else if($role == "Assassin" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
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
            }else if($role == "Assassin" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
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
            }else if($role == "Assassin" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
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
            }else if($role == "Assassin" || $role == "Minion" || $role == "Oberon" || $role == "Morgana"){
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
        case "Assassin":
            return "Orgyilkos";
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

function isInParty($player){
    global $pdo;
    try {

        // Retrieve players in the lobby
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

function displayRoundWinner($invite_code){
    
    $fails = getMissionFails($invite_code);
    if(isMissionSuccess($invite_code, getCurrentRound($invite_code))){
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

$players_and_data = getPlayersPOV($user_id, $invite_code);
$players = getPlayers($invite_code);
$roundlist = getRoundList($invite_code);
$gamestate = getGameState($invite_code);
$current_round = getCurrentRound($invite_code);

?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avalon</title>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>
<h3>Bejelentkezve mint: <?php echo $user_id?></h3>
<h3>A szereped: <?php echo displayRole(getRole($user_id,$invite_code)).' ('. displayAlignment(getAlignment($user_id,$invite_code)).')'?></h3>
<h3><?php echo displayGamestate($gamestate)?></h3>

<h3>Körök</h3>
<div <?php if($current_round == 1 || $gamestate != "selection") echo "hidden"; ?>>
    
    <p><?php echo displayRoundWinner($invite_code)?></p>
</div>
<div
    class="table-responsive"
>
    <table
        class="table table-primary"
    >
        
        <tbody>
            <tr class="">
                <?php for ($i=1; $i <= 5; $i++) :?>
                    <td><?php switch(getRoundData($i, $invite_code)){
                        case 'good':
                            echo "✔";
                            break;
                        case 'evil':
                            echo "❌";
                            break;
                        default:
                            echo getRoundData($i, $invite_code);
                    }?></td>
                <?php endfor;?>
            </tr>          
        </tbody>
    </table>
</div>

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
        for ($j = 0; $j < count($players_and_data); $j++) {
            
            if($j == 2 && $gamestate == "voting"){
                echo "<td>szavaz...</td>";
            }else if($players_and_data[$j][$i] == "0"){
                echo "";
            }else if($j == 2 && $players_and_data[$j][$i] == 1){
                echo "<td>👍</td>";
            }else if($j == 2 && $players_and_data[$j][$i] == -1){
                echo "<td>👎</td>";
            }else if($j == 3 && $players_and_data[$j][$i] == 1){
                echo "<td>👑</td>";
            }
            else if($j == 4 && $players_and_data[$j][$i] == 1){
                echo "<td>⚔</td>";
            }
            else{

                echo "<td>" . $players_and_data[$j][$i] . "</td>";
            }
        }
        echo "</tr>";
    }
    ?>
</table>
<form id="select_party" method="post" action="" <?php if ($gamestate != "selection" || getKing($invite_code) != $user_id) echo 'hidden'; ?>>
        <?php foreach ($players as $player) {
            echo '<input type="checkbox" name="select_party_cb[]" value='.$player.' id='.$player.' >'.$player;
        }?>
        <button type="submit" name="action" value="select_team">Csapat kiválasztása</button>
           
</form>

<form id="vote" method="post" action="" <?php if ($gamestate != "voting" || getVote($user_id) != 0) echo 'hidden'; ?>>
<p>Elfogadod ezt a csapat összeállítást?</p>
<button type="submit" name="action" value="vote_yes">Accept</button>
<button type="submit" name="action" value="vote_no">Decline</button>
</form>

<form id="mission" method="post" action="" <?php if ($gamestate != "mission" || isInParty($user_id) != 1 || getMissionVote($user_id)!=0) echo 'hidden'; ?>>
        <p>Sikerre viszed a küldetést?</p>
        <button type="submit" name="action" value="success">Siker</button>
        <button type="submit" name="action" value="fail" <?php if (getAlignment($user_id,$invite_code)=="good") echo 'hidden'; ?>>Balsiker</button>
</form>
<form id="assassin" method="post" action="" <?php if ($gamestate != "assassin" || getRole($user_id,$invite_code) != "Assassin") echo 'hidden'; ?>>
        <p>Öld meg Merlint</p>
        <?php foreach ($players as $player) {
            echo '<input type="radio" name="kill_target" value='.$player.' id='.$player.'>'.$player;
        }?>
        <button type="submit" name="action" value="assassin">Véglegesít</button>
        
</form>
<div id="good" <?php if ($gamestate != "good") echo 'hidden'; ?>>
    <p>Gratulálok, Artúr hű követői legyőzték Mordred bérenceit!</p>
</div>
<div id="evil" <?php if ($gamestate != "evil") echo 'hidden'; ?>>
    <p>Mordred és a gonosz sötét erői győzedelmeskedtek!</p>
</div>
<form method="post" action="">

                <button type="submit" name="action" value="main_menu">Főmenü</button>
            </form>
<form action="logout.php" method="post">
     <button type="submit">Kijelentkezés</button>
</form>

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
    
    function refreshPage() {
        setTimeout(function() {
            window.location.href = window.location.href;
        }, 5000); 
    }
    refreshPage();
</script>
</body>
</html>
