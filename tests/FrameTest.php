<?php

declare(strict_types=1);

use App\WebSocket\Frame;
use App\WebSocket\FrameException;

test('Frame: encode->decode Roundtrip (kurze Nachricht)', function (): void {
    $encoded = Frame::encode('hallo', Frame::TEXT);
    $result = Frame::decode($encoded);

    assertEquals(1, count($result['frames']));
    assertEquals('', $result['rest']);
    assertEquals(Frame::TEXT, $result['frames'][0]['opcode']);
    assertTrue($result['frames'][0]['fin']);
    assertEquals('hallo', $result['frames'][0]['payload']);
});

test('Frame: Roundtrip mit 16-Bit-Laenge (>125 Bytes)', function (): void {
    $payload = str_repeat('A', 200);
    $result = Frame::decode(Frame::encode($payload));
    assertEquals($payload, $result['frames'][0]['payload']);
});

test('Frame: maskierter Client-Frame wird korrekt entmaskt', function (): void {
    $masked = Frame::encodeMasked('geheim', Frame::TEXT);
    $result = Frame::decode($masked);
    assertEquals('geheim', $result['frames'][0]['payload']);
});

test('Frame: unvollstaendiger Puffer bleibt als rest erhalten', function (): void {
    $full = Frame::encode('vollstaendig');
    $partial = substr($full, 0, 3); // absichtlich abgeschnitten
    $result = Frame::decode($partial);

    assertEquals(0, count($result['frames']));
    assertEquals($partial, $result['rest']);
});

test('Frame: mehrere Frames in einem Puffer', function (): void {
    $buf = Frame::encode('eins') . Frame::encodeMasked('zwei');
    $result = Frame::decode($buf);
    assertEquals(2, count($result['frames']));
    assertEquals('eins', $result['frames'][0]['payload']);
    assertEquals('zwei', $result['frames'][1]['payload']);
});

test('Frame: zu grosser Frame wird abgelehnt', function (): void {
    // Payload 200, aber Limit 100 -> FrameException
    $encoded = Frame::encode(str_repeat('B', 200));
    assertThrows(fn () => Frame::decode($encoded, 100), FrameException::class);
});

test('Frame: close/ping/pong Opcodes bleiben erhalten', function (): void {
    foreach ([Frame::CLOSE, Frame::PING, Frame::PONG] as $op) {
        $result = Frame::decode(Frame::encode('', $op));
        assertEquals($op, $result['frames'][0]['opcode']);
    }
});
