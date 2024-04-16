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

function getPlayersInGame($inviteCode) {
    global $pdo;

    try {

        // Retrieve players in the lobby
        $query = "SELECT player, player_role FROM games WHERE game_id = :inviteCode";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':inviteCode', $inviteCode, PDO::PARAM_STR);
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
    $players = getPlayersInGame($invite_code);
    $tmp_players = array();
    $tmp_roles = array();
    $special_roles = getSpecialRoles($players);
    if($players[$user_id]=="Knight"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, $role);
            }else{

                array_push($tmp_roles, "?");
            }
        }
        $result = array_combine($tmp_players, $tmp_roles);
        return $result;
    }
    if($players[$user_id]=="Oberon"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, $role);
            }else{

                array_push($tmp_roles, "?");
            }
        }
        $result = array_combine($tmp_players, $tmp_roles);
        return $result;
    }
    if($players[$user_id]=="Minion"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, $role);
            }else if($role == "Assassin" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
                array_push($tmp_roles, "Evil");
            }else if(in_array("Oberon", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "Good");
            }
        }
        $result = array_combine($tmp_players, $tmp_roles);
        return $result;
    }
    if($players[$user_id]=="Assassin"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, $role);
            }else if($role == "Assassin" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
                array_push($tmp_roles, "Evil");
            }else if(in_array("Oberon", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "Good");
            }
        }
        $result = array_combine($tmp_players, $tmp_roles);
        return $result;
    }
    if($players[$user_id]=="Mordred"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, $role);
            }else if($role == "Assassin" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
                array_push($tmp_roles, "Evil");
            }else if(in_array("Oberon", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "Good");
            }
        }
        $result = array_combine($tmp_players, $tmp_roles);
        return $result;
    }
    if($players[$user_id]=="Morgana"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, $role);
            }else if($role == "Assassin" || $role == "Minion" || $role == "Mordred" || $role == "Morgana"){
                array_push($tmp_roles, "Evil");
            }else if(in_array("Oberon", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "Good");
            }
        }
        $result = array_combine($tmp_players, $tmp_roles);
        return $result;
    }
    if($players[$user_id]=="Percival"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, $role);
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
        $result = array_combine($tmp_players, $tmp_roles);
        return $result;
    }
    if($players[$user_id]=="Merlin"){
        foreach ($players as $player => $role) {
            array_push($tmp_players, $player);
            if($player == $user_id){
                array_push($tmp_roles, $role);
            }else if($role == "Assassin" || $role == "Minion" || $role == "Oberon" || $role == "Morgana"){
                array_push($tmp_roles, "Evil");
            }else if(in_array("Mordred", $special_roles)){

                array_push($tmp_roles, "?");
            }else{
                array_push($tmp_roles, "Good");
            }
        }
        $result = array_combine($tmp_players, $tmp_roles);
        return $result;
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
$players_and_roles = getPlayersPOV($user_id, $invite_code);


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
<!-- Players List -->
<?php print_r(getPlayersPOV("test",$invite_code))?>
<br>
<?php print_r(getPlayersPOV("test2",$invite_code))?>
<br>
<?php print_r(getPlayersPOV("test3",$invite_code))?>
<br>
<?php print_r(getPlayersPOV("test4",$invite_code))?>
<br>
<?php print_r(getPlayersPOV("test5",$invite_code))?>
<br>
<?php print_r(getPlayersPOV("test6",$invite_code))?>
<br>
<?php print_r(getPlayersPOV("test7",$invite_code))?>
<br>
<?php print_r(getPlayersPOV("test8",$invite_code))?>
<br>
<?php print_r(getPlayersPOV("test9",$invite_code))?>
<br>
<h3>Players in the Game</h3>
    <ul id="playersList">
        <?php foreach ($players_and_roles as $player => $role) : ?>
            <li><?php echo $player . " : " . $role; ?></li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
