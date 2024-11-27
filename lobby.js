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

function loadPlayers() {
    let game_id = document.getElementById("game_id").textContent;
    let username = document.getElementById("username").textContent;
    let leader = document.getElementById("leader").textContent;
    fetch("getPlayers.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `game_id=${encodeURIComponent(game_id)}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.some(e => e.player == username) || !data.some(e => e.player == leader)) {
                window.location.href = "index.php";
            }
            let playersList = document.getElementById("playersList");
            playersList.innerHTML = "";
            let playersize = document.getElementById("size");
            playersize.textContent = data.length;
            data.forEach(message => {
                let messageElement = document.createElement("li");
                messageElement.textContent = `${message.player}`;
                if (leader == username) {

                    let removeBtn = document.createElement("button");
                    removeBtn.textContent = 'X';
                    removeBtn.className = 'remove';
                    removeBtn.id = 'removeBtn';
                    removeBtn.value = `${message.player}`;
                    removeBtn.addEventListener("click", function(e) {
                        e.preventDefault();

                        let uname = this.value;

                        fetch("removePlayer.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                            },
                            body: `username=${encodeURIComponent(uname)}`
                        }).then(() => {

                            loadPlayers();
                        });
                    });
                    messageElement.appendChild(removeBtn);
                }
                playersList.appendChild(messageElement);
            });

        });
    loadMessages();
}

function loadGamestate() {
    let game_id = document.getElementById("game_id").textContent;
    fetch("getGamestate.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `game_id=${encodeURIComponent(game_id)}`
        })
        .then(response => response.json())
        .then(data => {
            let state = data[0].gamestate;
            if (state != null) {
                window.location.href = window.location.href;
            }

        });
}
window.onload = loadPlayers;
// Load messages every 2 seconds
setInterval(loadPlayers, 2000);
setInterval(loadGamestate, 2000);