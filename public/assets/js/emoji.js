/* PHP WebSocket Chat — Emoji-Picker.
   Kuratierte Emojis nach Kategorien in einem Popover ueber der Eingabe.
   Stellt window.ChatEmoji.setup(root, onSelect) bereit. */
(function () {
    "use strict";

    var CATEGORIES = [
        { icon: "😊", name: "Smileys", emojis: ["😀","😃","😄","😁","😆","😅","😂","🤣","🙂","🙃","😉","😊","😇","😍","🥰","😘","😋","😛","🤪","🤨","🧐","🤓","😎","🥳","😏","😌","😔","😴","🤤","😐","🙄","😬","🤔","🤗","🤭","🤫","😲","😳","🥺","😢","😭","😤","😠","😡","🤬","😱","😨","😰","😥"] },
        { icon: "👍", name: "Gesten", emojis: ["👍","👎","👌","🤌","✌️","🤞","🤟","🤘","👏","🙌","👐","🤲","🙏","💪","👊","✊","🤛","🤜","👋","🤚","🖐️","✋","👆","👇","👈","👉","☝️","👀","🧠","👂","👃","🫶"] },
        { icon: "❤️", name: "Herzen", emojis: ["❤️","🧡","💛","💚","💙","💜","🖤","🤍","🤎","💔","❣️","💕","💞","💓","💗","💖","💘","💝","💟","♥️"] },
        { icon: "🐶", name: "Tiere", emojis: ["🐶","🐱","🐭","🐹","🐰","🦊","🐻","🐼","🐨","🐯","🦁","🐮","🐷","🐸","🐵","🐔","🐧","🐦","🦆","🦉","🐴","🦄","🐝","🐛","🦋","🐌","🐞","🐢","🐍","🐙","🦑","🦀","🐠","🐟","🐬","🐳","🦈"] },
        { icon: "🍕", name: "Essen", emojis: ["🍏","🍎","🍐","🍊","🍋","🍌","🍉","🍇","🍓","🫐","🍈","🍒","🍑","🥭","🍍","🥥","🥝","🍅","🥑","🥦","🌽","🥕","🍞","🧀","🥚","🍳","🥓","🍔","🍟","🍕","🌭","🥪","🌮","🌯","🍝","🍜","🍲","🍣","🍦","🍩","🍪","🎂","🍰","🍫","🍿","☕","🍺","🥂"] },
        { icon: "⚽", name: "Aktivitaet", emojis: ["⚽","🏀","🏈","⚾","🎾","🏐","🏉","🎱","🏓","🏸","🥅","⛳","🎯","🎮","🎲","🎸","🎹","🎤","🎧","🎬","🎨","♟️","🏆","🥇","🥈","🥉","🚴","🏃","🏊","⛷️","🏄"] },
        { icon: "🌍", name: "Reise", emojis: ["🚗","🚕","🚙","🚌","🏎️","🚓","🚑","🚒","🚀","✈️","🚁","⛵","🚤","🚲","🛵","🏍️","🗺️","🌍","🌊","🏔️","🌋","🏕️","🏖️","🏝️","🌅","🌄","🎆","🌈","⛅","🌧️","❄️","🔥","💧","⭐","🌙","☀️"] },
        { icon: "💡", name: "Symbole", emojis: ["💡","🔔","📌","📎","🔒","🔑","💬","💭","✅","❌","⚠️","❓","❗","➕","➖","💯","🔥","✨","⭐","🎉","🎊","🎁","🏳️","🚩","♻️","⏰","📅","📞","💻","🖥️","📱","🔗","✏️","📝"] }
    ];

    function build(picker, onSelect) {
        picker.textContent = "";

        var tabs = document.createElement("div");
        tabs.className = "emoji-picker__tabs";
        var grid = document.createElement("div");
        grid.className = "emoji-picker__grid";

        function showCategory(index) {
            grid.textContent = "";
            CATEGORIES[index].emojis.forEach(function (emoji) {
                var b = document.createElement("button");
                b.type = "button";
                b.className = "emoji-picker__btn";
                b.textContent = emoji;
                b.addEventListener("click", function () { onSelect(emoji); });
                grid.appendChild(b);
            });
            Array.prototype.forEach.call(tabs.children, function (t, i) {
                t.classList.toggle("emoji-picker__tab--active", i === index);
            });
        }

        CATEGORIES.forEach(function (cat, i) {
            var t = document.createElement("button");
            t.type = "button";
            t.className = "emoji-picker__tab";
            t.textContent = cat.icon;
            t.title = cat.name;
            t.addEventListener("click", function () { showCategory(i); });
            tabs.appendChild(t);
        });

        picker.appendChild(tabs);
        picker.appendChild(grid);
        showCategory(0);
    }

    window.ChatEmoji = {
        setup: function (root, onSelect) {
            var toggle = root.querySelector('[data-role="emoji-toggle"]');
            var picker = root.querySelector('[data-role="emoji-picker"]');
            if (!toggle || !picker) { return; }

            var built = false;

            function open() {
                if (!built) { build(picker, onSelect); built = true; }
                picker.hidden = false;
            }
            function close() { picker.hidden = true; }

            toggle.addEventListener("click", function (e) {
                e.stopPropagation();
                if (picker.hidden) { open(); } else { close(); }
            });

            // Klick ausserhalb schliesst den Picker.
            document.addEventListener("click", function (e) {
                if (!picker.hidden && !picker.contains(e.target) && e.target !== toggle) {
                    close();
                }
            });

            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape") { close(); }
            });
        }
    };
})();
