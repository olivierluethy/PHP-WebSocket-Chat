<?php
/**
 * @var list<array{id:int,name:string,created_by:int|null,created_at:string}> $rooms
 * @var string       $username
 * @var list<string> $errors
 */
?>
<header class="topbar">
    <div class="topbar__brand">💬 PHP WebSocket Chat</div>
    <div class="topbar__user">
        <span class="topbar__name">Angemeldet als <strong><?= e($username) ?></strong></span>
        <form method="post" action="/logout" class="topbar__logout">
            <?= \App\Core\Csrf::field() ?>
            <button class="btn btn--ghost" type="submit">Abmelden</button>
        </form>
    </div>
</header>

<main class="rooms">
    <section class="rooms__panel">
        <h1 class="rooms__title">Raeume</h1>

        <?php if ($errors !== []): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($rooms === []): ?>
            <p class="rooms__empty">Noch keine Raeume. Erstelle den ersten!</p>
        <?php else: ?>
            <ul class="rooms__list">
                <?php foreach ($rooms as $room): ?>
                    <li class="rooms__item">
                        <a class="rooms__link" href="/room/<?= (int) $room['id'] ?>">
                            <span class="rooms__hash">#</span><?= e($room['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/rooms" class="rooms__create">
            <?= \App\Core\Csrf::field() ?>
            <input class="form__input" name="name" type="text" placeholder="Neuer Raumname"
                   required maxlength="40" pattern="[\p{L}\p{N} _\-]{2,40}" title="2-40 Zeichen">
            <button class="btn btn--primary" type="submit">Raum erstellen</button>
        </form>
    </section>
</main>
