<?php
/**
 * @var array{id:int,name:string,created_by:int|null,created_at:string} $room
 * @var string $username
 * @var string $wsUrl
 * @var string $wsToken
 */
?>
<div class="chat"
     data-ws-url="<?= e($wsUrl) ?>"
     data-token="<?= e($wsToken) ?>"
     data-room-id="<?= (int) $room['id'] ?>"
     data-room-name="<?= e($room['name']) ?>"
     data-username="<?= e($username) ?>">

    <header class="topbar">
        <div class="topbar__brand">
            <a class="topbar__back" href="/rooms" title="Zur Raumliste">&larr;</a>
            <span class="chat__roomname">#<?= e($room['name']) ?></span>
            <span class="chat__status chat__status--connecting" data-role="status">verbinde…</span>
        </div>
        <div class="topbar__user">
            <span class="topbar__name"><strong><?= e($username) ?></strong></span>
            <form method="post" action="/logout" class="topbar__logout">
                <?= \App\Core\Csrf::field() ?>
                <button class="btn btn--ghost" type="submit">Abmelden</button>
            </form>
        </div>
    </header>

    <div class="chat__body">
        <main class="chat__main">
            <ul class="chat__messages" data-role="messages" aria-live="polite"></ul>

            <form class="chat__inputbar" data-role="form">
                <button type="button" class="btn btn--emoji" data-role="emoji-toggle"
                        title="Emoji" aria-label="Emoji auswaehlen">😊</button>
                <textarea class="chat__input" data-role="input" rows="1"
                          placeholder="Nachricht schreiben…" maxlength="2000"
                          aria-label="Nachricht"></textarea>
                <button type="submit" class="btn btn--primary chat__send">Senden</button>
                <div class="emoji-picker" data-role="emoji-picker" hidden></div>
            </form>
        </main>

        <aside class="chat__sidebar">
            <h2 class="chat__sidebar-title">Online <span data-role="user-count">0</span></h2>
            <ul class="chat__users" data-role="users"></ul>
        </aside>
    </div>
</div>

<script src="/assets/js/emoji.js" defer></script>
<script src="/assets/js/chat.js" defer></script>
