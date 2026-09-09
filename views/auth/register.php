<?php
/**
 * @var list<string> $errors
 * @var string       $username
 */
?>
<main class="auth">
    <div class="auth__card">
        <h1 class="auth__brand">💬 PHP WebSocket Chat</h1>
        <h2 class="auth__title">Registrieren</h2>

        <?php if ($errors !== []): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/register" class="form" autocomplete="off">
            <?= \App\Core\Csrf::field() ?>
            <label class="form__label" for="username">Benutzername</label>
            <input class="form__input" id="username" name="username" type="text"
                   value="<?= e($username) ?>" required autofocus
                   maxlength="20" pattern="[a-zA-Z0-9_]{3,20}"
                   title="3-20 Zeichen: Buchstaben, Zahlen, Unterstrich">

            <label class="form__label" for="password">Passwort</label>
            <input class="form__input" id="password" name="password" type="password"
                   required minlength="8">

            <label class="form__label" for="password_repeat">Passwort wiederholen</label>
            <input class="form__input" id="password_repeat" name="password_repeat" type="password"
                   required minlength="8">

            <button class="btn btn--primary" type="submit">Konto erstellen</button>
        </form>

        <p class="auth__switch">
            Bereits registriert? <a href="/login">Zur Anmeldung</a>
        </p>
    </div>
</main>
