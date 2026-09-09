<?php

declare(strict_types=1);

namespace App\WebSocket;

/**
 * Ereignisgesteuerter WebSocket-Server auf Basis von stream_socket_server und
 * stream_select. Ein einzelner Prozess bedient alle Verbindungen non-blocking.
 * Fehler einzelner Verbindungen werden isoliert (kein Server-Absturz).
 */
final class Server
{
    private const HANDSHAKE_MAX = 16384; // 16 KiB Obergrenze fuer den Upgrade-Request

    /** @var resource|null */
    private $listen = null;

    /** @var array<int, resource> Alle Client-Sockets (pending + etabliert). */
    private array $streams = [];

    /** @var array<int, string> Handshake-Puffer noch nicht aufgeruester Verbindungen. */
    private array $pending = [];

    /** @var array<int, Connection> Etablierte Verbindungen. */
    private array $conns = [];

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly Hub $hub,
    ) {
    }

    public function run(): void
    {
        $errno = 0;
        $errstr = '';
        $listen = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);
        if ($listen === false) {
            fwrite(STDERR, "Konnte nicht auf {$this->host}:{$this->port} lauschen: {$errstr} ({$errno})\n");
            exit(1);
        }
        stream_set_blocking($listen, false);
        $this->listen = $listen;

        while (true) {
            $read = array_merge([$this->listen], array_values($this->streams));
            $write = null;
            $except = null;

            // Blockiert bis Aktivitaet; Timeout erlaubt kuenftige periodische Tasks.
            $ready = @stream_select($read, $write, $except, 30);
            if ($ready === false) {
                continue; // z. B. durch ein Signal unterbrochen
            }

            foreach ($read as $stream) {
                if ($stream === $this->listen) {
                    $this->accept();
                    continue;
                }

                try {
                    $this->onReadable($stream);
                } catch (\Throwable $e) {
                    // Verbindung isoliert schliessen, Server laeuft weiter.
                    $this->closeStream((int) $stream);
                }
            }
        }
    }

    private function accept(): void
    {
        $client = @stream_socket_accept($this->listen, 0);
        if ($client === false) {
            return;
        }
        stream_set_blocking($client, false);
        $id = (int) $client;
        $this->streams[$id] = $client;
        $this->pending[$id] = '';
    }

    /** @param resource $stream */
    private function onReadable($stream): void
    {
        $id = (int) $stream;
        $chunk = @fread($stream, 65535);

        if ($chunk === false || ($chunk === '' && feof($stream))) {
            $this->closeStream($id);
            return;
        }
        if ($chunk === '') {
            return;
        }

        if (isset($this->pending[$id])) {
            $this->handleHandshake($id, $stream, $chunk);
            return;
        }

        if (isset($this->conns[$id])) {
            $this->handleData($this->conns[$id], $chunk);
        }
    }

    /** @param resource $stream */
    private function handleHandshake(int $id, $stream, string $chunk): void
    {
        $this->pending[$id] .= $chunk;

        if (strlen($this->pending[$id]) > self::HANDSHAKE_MAX) {
            @fwrite($stream, "HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
            $this->closeStream($id);
            return;
        }

        if (!str_contains($this->pending[$id], "\r\n\r\n")) {
            return; // Header noch nicht vollstaendig
        }

        $request = $this->pending[$id];
        unset($this->pending[$id]);

        $result = Handshake::handle($request);
        @fwrite($stream, $result['response']);

        if (!$result['ok']) {
            $this->closeStream($id);
            return;
        }

        $conn = new Connection($id, static function (string $bytes) use ($stream): void {
            @fwrite($stream, $bytes);
        });
        $conn->stream = $stream;
        $conn->setIdentity((int) $result['userId'], (string) $result['username'], (int) $result['roomId']);

        $this->conns[$id] = $conn;
        $this->hub->join($conn);
    }

    private function handleData(Connection $conn, string $chunk): void
    {
        $conn->buffer .= $chunk;

        try {
            $decoded = Frame::decode($conn->buffer);
        } catch (FrameException) {
            $this->closeStream($conn->id);
            return;
        }
        $conn->buffer = $decoded['rest'];

        foreach ($decoded['frames'] as $frame) {
            $this->handleFrame($conn, $frame);
            if (!$conn->open) {
                return;
            }
        }
    }

    /** @param array{fin:bool,opcode:int,payload:string} $frame */
    private function handleFrame(Connection $conn, array $frame): void
    {
        switch ($frame['opcode']) {
            case Frame::CLOSE:
                $this->closeStream($conn->id);
                return;

            case Frame::PING:
                $conn->sendFrame($frame['payload'], Frame::PONG);
                return;

            case Frame::PONG:
                return;

            case Frame::TEXT:
                $conn->assembly = $frame['payload'];
                $conn->assemblyOpcode = Frame::TEXT;
                break;

            case Frame::CONTINUATION:
                $conn->assembly .= $frame['payload'];
                break;

            default:
                return; // unbekannter/ungenutzter Opcode (z. B. Binary)
        }

        if ($frame['fin']) {
            if ($conn->assemblyOpcode === Frame::TEXT) {
                $this->hub->onMessage($conn, $conn->assembly);
            }
            $conn->assembly = '';
            $conn->assemblyOpcode = null;
        }
    }

    private function closeStream(int $id): void
    {
        if (isset($this->conns[$id])) {
            $this->hub->leave($this->conns[$id]);
            unset($this->conns[$id]);
        }
        unset($this->pending[$id]);

        if (isset($this->streams[$id])) {
            @fclose($this->streams[$id]);
            unset($this->streams[$id]);
        }
    }
}
