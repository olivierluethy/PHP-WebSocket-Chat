<?php

declare(strict_types=1);

use App\Controller\ChatController;

test('Raumname-Validierung akzeptiert und lehnt korrekt ab', function (): void {
    assertTrue(ChatController::isValidRoomName('General'));
    assertTrue(ChatController::isValidRoomName('Team Schweiz'));
    assertTrue(ChatController::isValidRoomName('raum_1-2'));
    assertTrue(ChatController::isValidRoomName('Gruessgott'));

    assertFalse(ChatController::isValidRoomName('a'));            // zu kurz
    assertFalse(ChatController::isValidRoomName(''));             // leer
    assertFalse(ChatController::isValidRoomName('<script>'));     // HTML
    assertFalse(ChatController::isValidRoomName(str_repeat('x', 41))); // zu lang
});
