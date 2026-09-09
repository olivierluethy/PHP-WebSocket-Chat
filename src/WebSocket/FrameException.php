<?php

declare(strict_types=1);

namespace App\WebSocket;

/** Signalisiert eine Protokollverletzung beim Framing (z. B. zu grosser Frame). */
final class FrameException extends \RuntimeException
{
}
