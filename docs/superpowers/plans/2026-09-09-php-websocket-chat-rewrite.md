# PHP-WebSocket-Chat Rewrite — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to
> implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Einen voll funktionsfaehigen, sicheren PHP-WebSocket-Livechat bauen —
reiner PHP-WS-Server (RFC 6455, null Dependencies), SQLite-Persistenz, modernes
Vanilla-JS-Frontend mit Raeumen, Userliste, Emoji, Timestamps.

**Architecture:** Klassisches PHP-MVC fuer die Web-App (Front-Controller + Router +
Controller + Views + Repositories auf PDO/SQLite) und ein separater, selbst
geschriebener WebSocket-Server-Prozess (stream_socket_server + stream_select
Event-Loop) mit Handshake/Framing/Hub. Beide teilen sich `src/` (Config, DB,
Repositories, Auth-Token).

**Tech Stack:** PHP 8.5 (sockets/pcntl/openssl/pdo_sqlite/mbstring), SQLite,
Vanilla JS + CSS. Keine Composer-/npm-Dependencies.

**Spec:** `docs/superpowers/specs/2026-09-09-php-websocket-chat-rewrite-design.md`

## Global Constraints

- Sprache aller Doku/Kommentare/UI: Deutsch, kein "ß" (immer "ss").
- Keine externen Dependencies (kein Composer, kein npm, kein CDN-Script/CSS).
- Alle DB-Zugriffe ausschliesslich ueber PDO Prepared Statements.
- Alle HTML-Ausgaben escaped; Chat-Nachrichten im Client nur via `textContent`.
- Identitaet im WS ausschliesslich aus signiertem WsToken, nie aus Client-Feldern.
- Viele kleine Commits auf `master`, ein Commit pro Task.
- Keine manuellen/E2E-Netzwerktests durch den Agenten; Unit-Tests dependency-frei.

## File Structure

```
bin/chat-server.php            # CLI-Einstieg WS-Server
bin/start.sh                   # startet Web (php -S) + WS
config/config.php              # Config aus Env + Defaults
public/index.php               # Front-Controller
public/assets/css/app.css      # gesamtes Styling
public/assets/js/chat.js       # WS-Client + UI-Logik
public/assets/js/emoji.js      # Emoji-Picker-Daten/Logik
src/Core/Config.php
src/Core/Router.php
src/Core/Request.php
src/Core/View.php               # Rendering + e()
src/Core/Csrf.php
src/Core/Session.php
src/Database/Connection.php      # PDO-SQLite-Factory
src/Database/Migrator.php        # idempotentes Schema
src/Repository/UserRepository.php
src/Repository/RoomRepository.php
src/Repository/MessageRepository.php
src/Auth/AuthService.php
src/Auth/WsToken.php             # HMAC sign/verify
src/Controller/AuthController.php
src/Controller/ChatController.php
src/WebSocket/Handshake.php
src/Websocket/Frame.php          # de/encode
src/WebSocket/Connection.php     # Puffer + Zustand pro Client
src/WebSocket/Hub.php            # Raeume, Broadcast, Rate-Limit, Persistenz
src/WebSocket/Server.php         # Event-Loop
views/layout.php
views/auth/login.php
views/auth/register.php
views/chat/rooms.php
views/chat/room.php
tests/run.php                    # Mini-Test-Runner
tests/*Test.php
README.md
docs/RETROSPECTIVE.md
.gitignore
```

---

## Task 1: Projekt-Grundgeruest und Aufraeumen

**Files:** `.gitignore` (create), altes `PHPWebSocketChat/`, `PHPWebSocketChatServer/`,
Root-Ballast (remove), `config/config.php` (create), `src/Core/Config.php` (create).

**Deliverable:** Sauberes Repo, alter Ballast entfernt, Config-Layer steht.

- [ ] `.gitignore` (data/, *.sqlite, .DS_Store, /vendor).
- [ ] Altprojekte `PHPWebSocketChat/`, `PHPWebSocketChatServer/` und leere Mockups
  entfernen (Inhalt ist im Explore-Bericht dokumentiert; Retrospektive haelt Ist-Stand fest).
- [ ] `src/Core/Config.php`: statische `get(key, default)` liest Env (`APP_SECRET`,
  `WEB_HOST/PORT`, `WS_HOST/PORT`, `ALLOWED_ORIGINS`, `DB_PATH`) mit Defaults.
- [ ] `config/config.php` bootstrapt Autoload (einfacher spl_autoload fuer `src/`) + Session-Settings.
- [ ] Commit: "chore: Repo aufraeumen, Config- und Autoload-Grundgeruest".

## Task 2: DB-Verbindung, Migrator, Schema

**Files:** `src/Database/Connection.php`, `src/Database/Migrator.php`, `tests/run.php`,
`tests/MigratorTest.php`.

**Interfaces:** Produces `Connection::pdo(?string $path): PDO` (SQLite, ERRMODE_EXCEPTION,
FK on), `Migrator::migrate(PDO): void` (idempotent, legt users/rooms/messages an).

- [ ] Mini-Test-Runner `tests/run.php` (findet *Test.php, ruft `test_*`-Funktionen, zaehlt Asserts).
- [ ] Test: Migrator gegen In-Memory-SQLite -> Tabellen existieren, zweiter Lauf wirft nicht.
- [ ] Implementieren, Test gruen.
- [ ] Commit: "feat: SQLite-Verbindung + idempotenter Migrator (users/rooms/messages)".

## Task 3: Repositories

**Files:** `src/Repository/{User,Room,Message}Repository.php`, `tests/RepositoryTest.php`.

**Interfaces:** Produces
`UserRepository::create(username,hash):int`, `findByUsername(string):?array`, `findById(int):?array`;
`RoomRepository::create(name,userId):int`, `all():array`, `findByName(string):?array`, `findById(int):?array`;
`MessageRepository::add(roomId,userId,username,body):int`, `recent(roomId,limit):array`.
Alle Prepared Statements.

- [ ] Tests gegen In-Memory-DB: create/find Roundtrips, unique-Constraint, `recent` Reihenfolge/Limit.
- [ ] Implementieren, Tests gruen.
- [ ] Commit: "feat: User-/Room-/Message-Repositories (PDO Prepared Statements)".

## Task 4: WsToken (HMAC sign/verify)

**Files:** `src/Auth/WsToken.php`, `tests/WsTokenTest.php`.

**Interfaces:** Produces `WsToken::issue(userId,username,ttl):string`,
`WsToken::verify(token):?array` (null bei falscher Signatur/abgelaufen). Format:
base64url(payload).hmac, HMAC-SHA256 mit `APP_SECRET`.

- [ ] Tests: gueltiges Token verifiziert; manipuliertes/abgelaufenes -> null; Payload korrekt.
- [ ] Implementieren, Tests gruen.
- [ ] Commit: "feat: signiertes, kurzlebiges WsToken (HMAC-SHA256)".

## Task 5: CSRF + Session + View-Escaping

**Files:** `src/Core/Csrf.php`, `src/Core/Session.php`, `src/Core/View.php`,
`tests/CsrfTest.php`.

**Interfaces:** Produces `Csrf::token():string`, `Csrf::check(?string):bool`;
`Session::start()` (sichere Cookie-Flags), `View::e(string):string`,
`View::render(template, data):string`.

- [ ] Test: CSRF token/check (gueltig vs falsch); `View::e` escaped `<script>`.
- [ ] Implementieren (Session mit HttpOnly/SameSite=Lax/regenerate-Helper), Tests gruen.
- [ ] Commit: "feat: CSRF, sichere Session und escapendes View-Layer".

## Task 6: Router, Request, Front-Controller

**Files:** `src/Core/Router.php`, `src/Core/Request.php`, `public/index.php`,
`tests/RouterTest.php`.

**Interfaces:** Produces `Router::add(method,path,handler)`, `Router::dispatch(Request):mixed`;
`Request::method()`, `Request::path()`, `Request::post(key)`, `Request::query(key)`.

- [ ] Test: Routen-Matching (GET/POST, unbekannt -> 404-Callback).
- [ ] Implementieren; `public/index.php` verdrahtet Routen (login/register/logout/rooms/room).
- [ ] Commit: "feat: Router, Request und Front-Controller".

## Task 7: AuthService + Auth-Controller + Views

**Files:** `src/Auth/AuthService.php`, `src/Controller/AuthController.php`,
`views/layout.php`, `views/auth/login.php`, `views/auth/register.php`,
`tests/AuthServiceTest.php`.

**Interfaces:** Produces `AuthService::register(username,pw,pw2):array{ok,errors}`,
`AuthService::login(username,pw):?array`, nutzt `password_hash/verify`,
`session_regenerate_id(true)` nach Login.

- [ ] Test: Register-Validierung (Whitelist username, PW-Match, Mindestlaenge, Duplikat),
  Login-Erfolg/Fehlschlag gegen In-Memory-DB.
- [ ] Implementieren inkl. Login/Register-Views (CSRF-Feld, escaped, kein jQuery).
- [ ] Commit: "feat: Registrierung/Login (password_hash, CSRF, Session-Regenerate)".

## Task 8: Chat-Controller + Raum-Views (HTTP-Teil)

**Files:** `src/Controller/ChatController.php`, `views/chat/rooms.php`,
`views/chat/room.php`.

**Interfaces:** `rooms()` listet/erstellt Raeume (POST mit CSRF, Name-Whitelist),
`room(id)` rendert Chat-UI + injiziert frisches WsToken + WS-URL + room-Metadaten.
Auth-Guard aktiv (Redirect zu Login ohne Session).

- [ ] `rooms.php`: funktionale Raumliste (echte IDs, Beitreten-Links) + Erstell-Formular.
- [ ] `room.php`: Chat-Layout (Nachrichten, Eingabe, Userliste, Emoji-Button),
  laedt `chat.js`/`emoji.js`, uebergibt Token/URL via data-Attribute (nicht inline-JS).
- [ ] Commit: "feat: Raumliste + Chat-Seite (HTTP), Auth-Guard, WsToken-Injektion".

## Task 9: WebSocket Frame de/encode

**Files:** `src/WebSocket/Frame.php`, `tests/FrameTest.php`.

**Interfaces:** Produces `Frame::decode(buffer):array{frames,rest}` (unmask, opcode,
payload, Laengen-Limit), `Frame::encode(payload,opcode):string`. Opcodes: text(0x1),
close(0x8), ping(0x9), pong(0xA).

- [ ] Tests: encode->decode Roundtrip (kurz/126/masked), Server-Frame ohne Maske,
  ueberlanges Frame abgelehnt.
- [ ] Implementieren, Tests gruen.
- [ ] Commit: "feat: RFC-6455 Frame-De-/Encoding mit Laengen-Limit".

## Task 10: Handshake (Upgrade + Origin + Token)

**Files:** `src/WebSocket/Handshake.php`, `tests/HandshakeTest.php`.

**Interfaces:** Produces `Handshake::response(requestHeaders):array{ok,headers,userId,username,roomId,error}`
— berechnet `Sec-WebSocket-Accept`, prueft `Origin` gegen Allowlist, verifiziert WsToken
aus Query, liest room aus Query.

- [ ] Tests: korrekter Accept-Key (bekanntes RFC-Beispiel), fremder Origin -> abgelehnt,
  ungueltiges Token -> abgelehnt.
- [ ] Implementieren, Tests gruen.
- [ ] Commit: "feat: WS-Handshake mit Origin-Allowlist und WsToken-Verifikation".

## Task 11: Connection + Hub (Raeume, Broadcast, Rate-Limit, Persistenz)

**Files:** `src/WebSocket/Connection.php`, `src/WebSocket/Hub.php`, `tests/HubTest.php`.

**Interfaces:** `Connection` haelt Socket, Lesepuffer, Identitaet, roomId, Zeitfenster
fuer Rate-Limit. `Hub::join(conn)`, `Hub::onMessage(conn, json)`, `Hub::leave(conn)`,
`Hub::roster(roomId):array`. Persistiert via MessageRepository; broadcastet Rohtext-Payload
(Client escaped). Rate-Limit (z. B. max N msg / Zeitfenster) + Body-Laengen-Limit.

- [ ] Tests (ohne echte Sockets, mit Fake-Sink): join sendet Historie+Roster,
  Nachricht wird persistiert + an Raum verteilt, Rate-Limit greift, Ueberlaenge abgewiesen.
- [ ] Implementieren, Tests gruen.
- [ ] Commit: "feat: Hub mit Raeumen, Broadcast, Rate-Limit und Persistenz".

## Task 12: Server-Event-Loop + CLI-Einstieg

**Files:** `src/WebSocket/Server.php`, `bin/chat-server.php`.

**Interfaces:** `Server::run()` — `stream_socket_server`, `stream_select`-Loop,
Accept -> Handshake -> Hub.join; liest Frames, ruft Hub.onMessage; behandelt
close/ping/pong; Fehler pro Verbindung isoliert (kein Server-Crash).

- [ ] Implementieren; `bin/chat-server.php` laedt Config, migriert DB, startet Server.
- [ ] Manueller Smoke nur durch Nutzer (kein Agenten-Netzwerktest). Syntax-Check `php -l`.
- [ ] Commit: "feat: WebSocket-Server-Event-Loop und CLI-Einstiegspunkt".

## Task 13: Frontend — WS-Client + Chat-UI

**Files:** `public/assets/js/chat.js`, `public/assets/css/app.css`.

**Interfaces:** liest Token/URL/room aus data-Attributen, oeffnet WS, rendert Nachrichten
(Name, Timestamp, Text via `textContent`), Userliste, System-Hinweise, Auto-Reconnect,
Enter-zu-Senden.

- [ ] Implementieren; sauberes, responsives CSS (Dark-taugliches, professionelles Styling).
- [ ] Commit: "feat: Vanilla-JS WS-Client mit Userliste, Timestamps, Auto-Reconnect".

## Task 14: Emoji-Picker

**Files:** `public/assets/js/emoji.js`, Integration in `chat.js`/`app.css`.

**Interfaces:** kuratierte Emoji-Liste nach Kategorien, Popover-Picker, fuegt Emoji in
Eingabefeld ein.

- [ ] Implementieren, in Chat-UI einbinden.
- [ ] Commit: "feat: Emoji-Picker im Chat".

## Task 15: Start-Skript

**Files:** `bin/start.sh`.

- [ ] Startet `php -S` auf public/ + `php bin/chat-server.php` parallel, sauberes
  Trap/Shutdown, druckt URLs. Ausfuehrbar (chmod +x).
- [ ] Commit: "feat: Start-Skript fuer Web- und WS-Server".

## Task 16: README mit Mermaid

**Files:** `README.md`.

- [ ] Worum-geht-es, Features, Voraussetzungen, Setup/Start, Konfiguration (Env),
  Sicherheit, Architektur mit **zwei Mermaid-Diagrammen** (Komponenten + Sequenz Login->Chat),
  wss/TLS-Hinweis.
- [ ] Commit: "docs: README mit Anleitung und Mermaid-Architekturdiagrammen".

## Task 17: Retrospektive-Doku

**Files:** `docs/RETROSPECTIVE.md`.

- [ ] Historischer Stand (aus Explore-Bericht), was nicht funktioniert hat und warum,
  wie geloest, was effektiv umgesetzt wurde.
- [ ] Commit: "docs: Retrospektive zum Alt-Stand und zur Loesung".

## Task 18: Abschluss-Verifikation

- [ ] `php -l` auf allen PHP-Dateien; `php tests/run.php` alle gruen.
- [ ] Security-Review-Skill ueber den Diff.
- [ ] Fixes committen; Abschlussbericht an Nutzer (Start-Anleitung).

## Self-Review

- Spec-Coverage: WS-Server (T9-12), SQLite/Persistenz (T2,3,11), Auth/WsToken (T4,7),
  XSS-Fix (T5,8,11,13), CSRF (T5,7,8), Origin/Rate-Limit (T10,11), Emoji/Userliste/
  Timestamps (T11,13,14), Start (T15), README+Mermaid (T16), Retrospektive (T17). Vollstaendig.
- Typkonsistenz der Interfaces oben geprueft (Repository-/Hub-/Token-Signaturen konsistent
  verwendet).
