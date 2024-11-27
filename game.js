let good_characters = ["Knight", "Merlin", "Percival"];

//#region chat
document.getElementById("messageForm").addEventListener("submit", function(e) {
    e.preventDefault();

    const username = document.getElementById("username").textContent;
    let message = document.getElementById("message").value;
    const game_id = document.getElementById("game_id").textContent;

    fetch("sendMessage.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `username=${encodeURIComponent(username)}&message=${encodeURIComponent(message)}&game_id=${encodeURIComponent(game_id)}`
    }).then(() => {
        document.getElementById("message").value = "";
        loadMessages();
    });
});

function loadMessages() {
    const game_id = document.getElementById("game_id").textContent;
    fetch("getMessages.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `game_id=${encodeURIComponent(game_id)}`
        })
        .then(response => response.json())
        .then(data => {
            let chatbox = document.getElementById("chatbox");
            chatbox.innerHTML = "";
            data.forEach(message => {
                let messageElement = document.createElement("div");
                messageElement.textContent = `[${message.timestamp}] ${message.username}: ${message.msg}`;
                chatbox.appendChild(messageElement);
            });
            chatbox.scrollTop = chatbox.scrollHeight;
        });
}

//#endregion

function loadGamestate() {
    const game_id = document.getElementById("game_id").textContent;
    const user_id = document.getElementById("username").textContent;
    fetch("getGameData.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `game_id=${encodeURIComponent(game_id)}`
        })
        .then(response => response.json())
        .then(async data => {
            let gamestate = document.getElementById("gamestate");
            const state = data.gamestate;
            const player = await getPlayerData();
            const players = await getAllPlayerData();
            const roundlist = [
                [2, 3, 2, 3, 3],
                [2, 3, 4, 3, 4],
                [2, 3, 3, 4, 4],
                [3, 4, 4, 5, 5],
                [3, 4, 4, 5, 5],
                [3, 4, 4, 5, 5]
            ];
            const rounds = roundlist[players.length - 5];
            //console.log(player);
            switch (state) {
                case 'voting':
                    gamestate.textContent = "Szavazás fázis";
                    break;
                case 'selection':
                    gamestate.textContent = "Csapat összeállítása fázis";
                    break;
                case 'mission':
                    gamestate.textContent = "Küldetés fázis";
                    break;
                case 'assassin':
                    gamestate.textContent = "Orgyilkos fázis";
                    break;
                case 'evil':
                    gamestate.textContent = "Játék vége";
                    break;
                case 'good':
                    gamestate.textContent = "Játék vége";
                    break;
                default:
                    gamestate.textContent = state;
                    break;
            }


            // Map the round data to corresponding table cells
            const roundResults = document.getElementById("round-results");
            const row = document.createElement("tr");
            roundResults.innerHTML = "";

            for (let i = 1; i <= 5; i++) {
                const roundResult = data[`round${i}`];
                const cell = document.createElement("td");
                cell.style.textAlign = "center";
                cell.style.border = "1px solid black";

                if (roundResult === "good") {
                    cell.textContent = "✔";
                } else if (roundResult === "evil") {
                    cell.textContent = "❌";
                } else {
                    cell.textContent = rounds[i - 1];
                }
                row.appendChild(cell);
            }

            roundResults.appendChild(row);



            // Loop through each player and populate the table
            for (let i = 0; i < players.length; i++) {
                let str = 'playerdata' + i;
                const dataCell = document.getElementById(str);
                dataCell.innerHTML = '';
                const votingStatus = players[i].vote;
                const isKing = players[i].king;
                const hasSword = players[i].is_in_party;

                if (state == "voting") {
                    dataCell.textContent = "szavaz...";
                } else if (votingStatus == 1) {
                    dataCell.textContent = "👍";
                } else if (votingStatus == -1) {
                    dataCell.textContent = "👎";
                }
                if (isKing == 1) {
                    dataCell.textContent += "👑";
                }
                if (hasSword == 1) {
                    dataCell.textContent += "⚔";
                }

            }
            //#region forms
            let round_result = document.getElementById("round_result");
            let round_result_label = document.getElementById("round_result_label");
            if (state == 'selection' && data.round1 != null) {
                let fails = players.filter(x => x.mission_vote == -1).length;
                round_result_label.textContent = "Balsikerek száma: " + fails;
                round_result.hidden = false;
            } else {
                round_result.hidden = false;
            }

            let vote_form = document.getElementById("vote");
            if (state == 'voting' && player.vote == 0) {
                vote_form.hidden = false;
            } else {
                vote_form.hidden = true;
            }

            let selection_form = document.getElementById("select_party");
            if (state == 'selection' && player.king == 1) {
                selection_form.hidden = false;
            } else {
                selection_form.hidden = true;
            }

            let mission_form = document.getElementById("mission");
            if (state == 'mission' && player.is_in_party == 1 && player.mission_vote == 0) {
                let fail_button = document.getElementById("fail_button");
                if (good_characters.includes(player.player_role)) {
                    fail_button.hidden = true;
                } else {
                    fail_button.hidden = false;
                }
                mission_form.hidden = false;
            } else {
                mission_form.hidden = true;
            }

            let good_form = document.getElementById("good");
            if (state == 'good') {
                good_form.hidden = false;
            } else {
                good_form.hidden = true;
            }

            let evil_form = document.getElementById("evil");
            if (state == 'evil') {
                evil_form.hidden = false;
            } else {
                evil_form.hidden = true;
            }

            let assassin_form = document.getElementById("assassin");
            if (state == 'assassin' && player.player_role == "Orgyilkos") {
                assassin_form.hidden = false;
            } else {
                assassin_form.hidden = true;
            }

            //#endregion

        });
}

async function getPlayerData() {
    const user_id = document.getElementById("username").textContent;
    let result;
    await fetch("getPlayerData.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `user_id=${encodeURIComponent(user_id)}`
        })
        .then(response => response.json())
        .then(data => {
            //console.log("Player role is: " + data[0].player_role);
            result = data[0];


        });

    return result;
}

async function getAllPlayerData() {
    const game_id = document.getElementById("game_id").textContent;
    let result;
    await fetch("getAllPlayerData.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `game_id=${encodeURIComponent(game_id)}`
        })
        .then(response => response.json())
        .then(data => {

            result = data;


        });

    return result;
}

function update() {
    loadMessages();
    loadGamestate();
}


window.onload = update;
setInterval(update, 2000);