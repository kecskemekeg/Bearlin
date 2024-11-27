document.getElementById("messageForm").addEventListener("submit", function(e) {
    e.preventDefault();

    let username = document.getElementById("username").textContent;
    let message = document.getElementById("message").value;
    let game_id = document.getElementById("game_id").textContent;

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
    let game_id = document.getElementById("game_id").textContent;
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
document.onload = loadMessages;
// Load messages every 2 seconds
setInterval(loadMessages, 2000);