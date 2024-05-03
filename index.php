<?php
include ('db.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'resumeGame':
            $game_id = getGameid($user_id);
            if (isGameidValid($game_id)) {
                header("Location: lobby.php?code=$game_id");
                exit();
            } else {
                echo "Nincs folyamatban lévő játékod!";
            }
            break;
        case 'joinGame':
            if (isset($_POST['joinCode'])) {
                $joinCode = strtoupper($_POST['joinCode']);
                if (strlen($joinCode) == 6) {
                    if (isGameidValid($joinCode)) {
                        if (getGameid($user_id) == $joinCode) {
                            echo "Már csatlakoztál ehhez a játékhoz, kérlek nyomj rá a játék folytatása gombra!";
                        } else {

                            try {
                                $insertQuery = "REPLACE INTO players (player,game_id,leader, is_started,is_in_party) VALUES (:user_id,:game_id,0,0,0)";
                                $insertStmt = $pdo->prepare($insertQuery);
                                $insertStmt->bindParam(':game_id', $joinCode, PDO::PARAM_STR);
                                $insertStmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
                                $insertStmt->execute();
                            } catch (PDOException $e) {
                                die("Connection failed: " . $e->getMessage());
                            }
                            // Redirect to the lobby page with the invitation code
                            header("Location: lobby.php?code=$joinCode");
                            exit();
                        }
                    } else {
                        echo "Nem létezik játék ezzel a kóddal";
                    }
                } else {
                    echo "A kód 6 karakter kell hogy legyen";
                }
            } else {
                echo "Hiányzó kód";
            }
            break;
        case "create_game":
            $game_id = generateGameID();
            $insertQuery = "REPLACE INTO players (player,game_id, leader,is_started,is_in_party ) VALUES (:user_id,:game_id,1,0,0)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
            $insertStmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $insertStmt->execute();
            header("Location: lobby.php?code=$game_id");
            exit();
        default:
            # code...
            break;
    }
}

function generateGameID() {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $gameID = '';
    $length = strlen($characters);
    // Generate a random character 6 times and append it to the game ID
    for ($i = 0; $i < 6; $i++) {
        $gameID .= $characters[rand(0, $length - 1)];
    }
    return $gameID;
}

function getGameid($user_id)
{
    global $pdo;
    try {

        $query = "SELECT game_id FROM players WHERE player=:user_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC)[0]['game_id'];
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

}

function isGameidValid($game_id)
{
    global $pdo;
    try {

        $query = "SELECT * FROM players WHERE game_id=:game_id AND leader=1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_STR);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return (sizeof($res) == 1);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
    <title>Bearlin</title>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>

<body>
    <h2>Üdvözöllek a játékban <?php echo $user_id ?>!</h2>
    <div>
        <p>Az Avalon a titkolt hűség játéka. A játékosok vagy Artúr hű követői, akik a jóért és
a becsületért harcolnak, vagy Mordred gonosz céljai mellett törnek lándzsát. A jók három küldetés
sikeres teljesítésével nyerhetik meg a játékot. A gonoszok akkor győzedelmeskednek, ha három
küldetés kudarcba fullad. A gonoszok akkor is sikerrel járnak, ha a játék végén meggyilkolják Merlint
vagy ha egy küldetést a csapat nem tud elvállalni.
A játékosok a játék során bármikor bármit állíthatnak, bármit megvitathatnak. A beszélgetés, a vádaskodás, a megtévesztés és a logikai következtetés a jó győzelméhez és a gonosz uralmához is fontos. <a href="rulebook_hu.pdf" target="blank">magyar</a> illetve <a
                href="rulebook.pdf" target="blank">angol</a> nyelven.</p>
    </div>

    <!-- Button to create a game -->
    <form method="post" action="">

        <button type="submit" name="action" value="create_game">Játék létrehozása</button>
    </form>
    <!-- Button to resume a game -->

    <form method="post" action="">
        <button type="submit" name="action" value="resumeGame">Játék folytatása</button>
    </form>
    <!-- Join Game Form -->
    <h3>Csatlakozás meglévő játékba</h3>
    <form method="post" action="">
        <label for="joinCode">Írd be az invitációs kódot!</label>
        <input type="text" name="joinCode" required>
        <button type="submit" name="action" value="joinGame">Csatlakozás</button>
    </form>
    <div class="footer">
        <form action="logout.php" method="post">
            <button type="submit">Kijelentkezés</button>
        </form>
    </div>

</body>

</html>