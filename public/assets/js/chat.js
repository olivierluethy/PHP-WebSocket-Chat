/* PHP WebSocket Chat — Client.
   Verbindet sich mit dem WebSocket-Server, rendert Nachrichten sicher
   (textContent, kein innerHTML), zeigt Presence und Live-Status, verbindet
   sich bei Verbindungsabbruch automatisch neu. Keine externen Abhaengigkeiten. */
(function () {
    "use strict";

    var root = document.querySelector(".chat");
    if (!root) { return; }

    var cfg = {
        wsUrl: root.dataset.wsUrl,
        token: root.dataset.token,
        roomId: root.dataset.roomId,
        username: root.dataset.username
    };

    var els = {
        messages: root.querySelector('[data-role="messages"]'),
        users: root.querySelector('[data-role="users"]'),
        userCount: root.querySelector('[data-role="user-count"]'),
        form: root.querySelector('[data-role="form"]'),
        input: root.querySelector('[data-role="input"]'),
        status: root.querySelector('[data-role="status"]')
    };

    /* ---- Darstellungshelfer ------------------------------------------- */

    // Stabile Farbe aus dem Benutzernamen (Signatur-Element).
    function colorForName(name) {
        var hash = 0;
        for (var i = 0; i < name.length; i++) {
            hash = (hash * 31 + name.charCodeAt(i)) % 360;
        }
        return "hsl(" + hash + ", 58%, 45%)";
    }

    function initialFor(name) {
        return (name.trim()[0] || "?").toUpperCase();
    }

    function makeAvatar(name, extraClass) {
        var el = document.createElement("span");
        el.className = "msg__avatar" + (extraClass ? " " + extraClass : "");
        el.style.backgroundColor = colorForName(name);
        el.textContent = initialFor(name);
        return el;
    }

    function formatTime(ts) {
        var d = new Date(ts * 1000);
        return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    }

    function nearBottom() {
        var m = els.messages;
        return m.scrollHeight - m.scrollTop - m.clientHeight < 80;
    }

    function scrollToBottom() {
        els.messages.scrollTop = els.messages.scrollHeight;
    }

    var lastSender = null;

    function appendMessage(msg) {
        var stick = nearBottom();
        var own = msg.username === cfg.username;
        var grouped = lastSender === msg.username;

        var li = document.createElement("li");
        li.className = "msg" + (own ? " msg--own" : "") + (grouped ? " msg--grouped" : "");

        li.appendChild(makeAvatar(msg.username));

        var content = document.createElement("div");
        content.className = "msg__content";

        var head = document.createElement("div");
        head.className = "msg__head";
        var name = document.createElement("span");
        name.className = "msg__name";
        name.textContent = own ? "Du" : msg.username;
        var time = document.createElement("span");
        time.className = "msg__time";
        time.textContent = formatTime(msg.ts);
        head.appendChild(name);
        head.appendChild(time);

        var body = document.createElement("div");
        body.className = "msg__body";
        body.textContent = msg.body; // sicher: kein HTML

        content.appendChild(head);
        content.appendChild(body);
        li.appendChild(content);
        els.messages.appendChild(li);

        lastSender = msg.username;
        if (stick) { scrollToBottom(); }
    }

    function appendSystem(text) {
        var stick = nearBottom();
        var li = document.createElement("li");
        li.className = "msg msg--system";
        var body = document.createElement("div");
        body.className = "msg__body";
        body.textContent = text;
        li.appendChild(body);
        els.messages.appendChild(li);
        lastSender = null;
        if (stick) { scrollToBottom(); }
    }

    function setPresence(users) {
        els.users.textContent = "";
        els.userCount.textContent = String(users.length);
        users.forEach(function (name) {
            var li = document.createElement("li");
            li.className = "chat__user" + (name === cfg.username ? " chat__user--me" : "");
            li.appendChild(makeAvatar(name));
            var label = document.createElement("span");
            label.textContent = name === cfg.username ? name + " (du)" : name;
            li.appendChild(label);
            els.users.appendChild(li);
        });
    }

    function setStatus(state, text) {
        els.status.className = "chat__status chat__status--" + state;
        els.status.textContent = text;
    }

    /* ---- WebSocket ----------------------------------------------------- */

    var ws = null;
    var reconnectDelay = 1000;
    var failures = 0;
    var manualClose = false;

    function endpoint() {
        return cfg.wsUrl + "/?token=" + encodeURIComponent(cfg.token) +
            "&room=" + encodeURIComponent(cfg.roomId);
    }

    function connect() {
        setStatus("connecting", "verbinde…");
        try {
            ws = new WebSocket(endpoint());
        } catch (e) {
            scheduleReconnect();
            return;
        }

        ws.onopen = function () {
            failures = 0;
            reconnectDelay = 1000;
            setStatus("live", "verbunden");
        };

        ws.onmessage = function (event) {
            var data;
            try { data = JSON.parse(event.data); } catch (e) { return; }
            dispatch(data);
        };

        ws.onclose = function () {
            if (manualClose) { return; }
            setStatus("offline", "getrennt");
            scheduleReconnect();
        };

        ws.onerror = function () {
            if (ws) { try { ws.close(); } catch (e) {} }
        };
    }

    function scheduleReconnect() {
        failures++;
        // Nach mehreren Fehlversuchen ist das kurzlebige Token wahrscheinlich
        // abgelaufen — ein Reload holt ein frisches Token.
        if (failures >= 4) {
            setStatus("offline", "neu laden…");
            setTimeout(function () { window.location.reload(); }, 1500);
            return;
        }
        setTimeout(connect, reconnectDelay);
        reconnectDelay = Math.min(reconnectDelay * 1.8, 10000);
    }

    function dispatch(data) {
        switch (data.type) {
            case "history":
                (data.messages || []).forEach(appendMessage);
                scrollToBottom();
                break;
            case "message":
                appendMessage(data);
                break;
            case "presence":
                setPresence(data.users || []);
                break;
            case "system":
                appendSystem(data.message);
                break;
            case "error":
                appendSystem("⚠ " + data.message);
                break;
        }
    }

    function sendMessage() {
        var text = els.input.value.trim();
        if (!text || !ws || ws.readyState !== WebSocket.OPEN) { return; }
        ws.send(JSON.stringify({ type: "message", body: text }));
        els.input.value = "";
        autoGrow();
    }

    /* ---- Eingabe-Verhalten -------------------------------------------- */

    function autoGrow() {
        els.input.style.height = "auto";
        els.input.style.height = Math.min(els.input.scrollHeight, 128) + "px";
    }

    els.form.addEventListener("submit", function (e) {
        e.preventDefault();
        sendMessage();
    });

    els.input.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    els.input.addEventListener("input", autoGrow);

    /* ---- Emoji-Picker (aus emoji.js) ---------------------------------- */

    if (window.ChatEmoji) {
        window.ChatEmoji.setup(root, function (emoji) {
            var el = els.input;
            var start = el.selectionStart || el.value.length;
            var end = el.selectionEnd || el.value.length;
            el.value = el.value.slice(0, start) + emoji + el.value.slice(end);
            el.focus();
            var pos = start + emoji.length;
            el.setSelectionRange(pos, pos);
            autoGrow();
        });
    }

    window.addEventListener("beforeunload", function () {
        manualClose = true;
        if (ws) { try { ws.close(); } catch (e) {} }
    });

    connect();
    els.input.focus();
})();
