# Design: PHP-WebSocket-Chat — Vollstaendige Ueberarbeitung

**Datum:** 2026-09-09
**Status:** Freigegeben (Architektur bestaetigt)

## Ziel

Der ueber fuenf Jahre alte PHP-WebSocket-Chat soll vollstaendig funktionsfaehig,
sicher und professionell werden. Kein bestehendes Feature geht verloren; ergaenzt
werden Emoji-Auswahl, Timestamps, Online-Userliste und echte Nachrichten-Persistenz.
Am Ende darf es keine der bekannten Sicherheitsluecken mehr geben und die App muss
lokal ohne Fremdsysteme startbar sein.

## Grundsatzentscheidungen (bestaetigt)

1. **WebSocket-Server:** reines PHP von Grund auf (RFC 6455), null Dependencies,
   kein Composer noetig.
2. **Datenbank:** SQLite via PDO — eine Datei, kein DB-Server, keine Credentials.
3. **Start:** ein `bin/start.sh`, das PHP-Built-in-Webserver + WS-Server startet.

## Ausgangslage (Ist-Zustand, kurz)

- Zwei fast identische Projekte: `PHPWebSocketChat/` (echte App) und
  `PHPWebSocketChatServer/` (alter Prototyp, Ballast).
- WS-Server via Ratchet 0.3.5, Frontend via jQuery 1.11.1 — beides veraltet.
- Kaputt: `register()` crasht (fehlende View), `jquery.js` fehlt in der Haupt-App,
  `home()` hat leeres SQL, Chat-Auswahl nicht funktional, Raeume serverseitig nicht
  getrennt, keine Persistenz.
- Sicherheitsluecken: XSS im Chat, WS ohne Auth/Origin-Check, hardcoded root-DB ohne
  Passwort, kein CSRF, mysqli+PDO gemischt, Fehler-Leaks, unverschluesseltes ws://,
  kein Rate-Limit/Laengen-Check.

## Zielarchitektur

Neue, saubere Verzeichnisstruktur im Repo-Root (das alte `PHPWebSocketChat/` und
`PHPWebSocketChatServer/` werden entfernt bzw. ersetzt):

```
/
├── bin/
│   ├── chat-server.php        # Einstiegspunkt WebSocket-Server (CLI)
│   └── start.sh               # startet Web + WS
├── public/                    # Document-Root des Webservers
│   ├── index.php              # Front-Controller
│   ├── assets/css/...
│   └── assets/js/...
├── src/
│   ├── Core/                  # Router, Request, Response, View, Csrf, Config
│   ├── Database/              # PDO-Verbindung, Migrationen, Repositories
│   ├── Auth/                  # Login/Register, Session, Token-Signierung
│   ├── Controllers/           # Auth-, Chat-Controller
│   └── WebSocket/             # Server, Connection, Frame, Handshake, Hub (Raeume)
├── views/                     # PHP-Templates (escaped)
├── data/                      # chat.sqlite (gitignored)
├── config/                    # config.php (aus Env/Defaults)
├── docs/
├── tests/
└── README.md
```

### Komponenten und Verantwortlichkeiten

- **Core\Router** — mappt Pfad -> Controller@Methode, nur registrierte Routen.
- **Core\View** — rendert Templates, escaped standardmaessig (`e()`), CSP-Header.
- **Core\Csrf** — Token generieren/validieren pro Session.
- **Core\Config** — liest Env-Variablen mit sicheren Defaults (Ports, App-Secret,
  erlaubte Origins, DB-Pfad).
- **Database\Connection** — einzelne PDO-SQLite-Verbindung, `ERRMODE_EXCEPTION`.
- **Database\Migrator** — legt Schema idempotent an (beim Web- und WS-Start).
- **Repositories** — `UserRepository`, `RoomRepository`, `MessageRepository`
  (ausschliesslich Prepared Statements).
- **Auth\AuthService** — Register (password_hash), Login (password_verify,
  session_regenerate_id), Logout.
- **Auth\WsToken** — HMAC-signiertes, kurzlebiges Token, das die Web-Session an eine
  WS-Verbindung bindet (Payload: userId, username, exp). Verhindert Identitaets-Spoofing.
- **WebSocket\Server** — `stream_socket_server`, non-blocking Event-Loop
  (`stream_select`), akzeptiert Verbindungen, delegiert an Handshake/Frame/Hub.
- **WebSocket\Handshake** — RFC-6455-Upgrade, prueft `Origin` gegen Allowlist,
  validiert WsToken aus der Query.
- **WebSocket\Frame** — De-/Encoding (Masking, Opcodes: text, close, ping, pong),
  Fragmentierung, Laengen-Limit.
- **WebSocket\Hub** — Verwaltung von Verbindungen, Raeumen, Mitgliedern; Broadcast
  pro Raum; Rate-Limit pro Verbindung; Persistiert Nachrichten via MessageRepository.

### Datenmodell (SQLite)

```
users(id, username UNIQUE, password_hash, created_at)
rooms(id, name UNIQUE, created_by, created_at)
messages(id, room_id FK, user_id FK, username_snapshot, body, created_at)
```

`messages` ersetzt die alte, leere `history`-Tabelle und modelliert echte Persistenz
inkl. Text und Timestamp.

### Datenfluss (Chat-Nachricht)

1. Nutzer meldet sich per HTTP an -> Session + kurzlebiges WsToken werden erzeugt.
2. Browser oeffnet `ws://host:port/?token=...&room=...`.
3. WS-Server: Handshake -> Origin-Check -> WsToken-Verify -> Hub ordnet Verbindung
   dem Raum zu, sendet Verlauf (letzte N Nachrichten) + Userliste.
4. Nutzer sendet Textnachricht (JSON). Server validiert Typ/Laenge/Rate, escaped den
   Inhalt beim Rendern (nicht im Speicher), persistiert und broadcastet an den Raum.
5. Alle Clients rendern die Nachricht als **Text** (`textContent`, kein innerHTML).

## Features

Bestehend (repariert/funktional): Registrierung, Login, Logout, Raumliste, Raum
erstellen, Raum beitreten, Nachrichten senden/empfangen.

Neu: Timestamps, Online-Userliste pro Raum, Emoji-Picker, Nachrichten-Historie beim
Betreten, Auto-Reconnect des WS-Clients, System-/Join-/Leave-Hinweise.

## Sicherheitsmassnahmen (Loesung der bekannten Luecken)

| Luecke | Loesung |
|--------|---------|
| XSS im Chat (rohes HTML) | Server speichert Rohtext; Client rendert per `textContent`; Views nutzen `e()`. CSP-Header. |
| XSS ueber Username/Chat-Name | Alle View-Ausgaben escaped; Eingabe-Whitelist. |
| WS ohne Auth | HMAC-signiertes WsToken beim Handshake, an Web-Session gebunden. |
| WS ohne Origin-Check | `Origin`-Allowlist im Handshake. |
| Identitaets-Spoofing (user_id vom Client) | Identitaet stammt ausschliesslich aus dem WsToken. |
| Hardcoded root-DB ohne Passwort | SQLite ohne Credentials; keine Secrets im Code. |
| Kein CSRF-Schutz | CSRF-Token in allen POST-Formularen. |
| Session-Fixation | `session_regenerate_id(true)` nach Login; sichere Cookie-Flags (HttpOnly, SameSite). |
| Fehler-/SQL-Leaks | Zentrales Error-Handling; keine internen Meldungen nach aussen. |
| Kein Rate-Limit/Laengen-Check | Pro-Verbindung-Rate-Limit + Laengenbegrenzung im Hub/Frame. |
| Gemischtes mysqli/PDO | Ausschliesslich ein PDO-Layer. |
| Veraltete Dependencies | Kein jQuery/Ratchet mehr; Vanilla-JS + eigener Server. |

`ws://` bleibt fuer lokale Entwicklung; der Pfad zu `wss://` (Reverse-Proxy/TLS) wird
im README dokumentiert.

## Start / Betrieb

- `bin/start.sh` startet parallel: `php -S 127.0.0.1:8000 -t public` und
  `php bin/chat-server.php`. Ports/Secret/Origins ueber Env konfigurierbar mit Defaults.
- Erststart legt SQLite-Datei + Schema automatisch an (Migrator).
- Keine manuellen Tests durch den Agenten — der Nutzer testet selbst.

## Tests

Automatisierte Unit-Tests (dependency-frei, eigener Mini-Runner oder PHP-Asserts) fuer
die reinen Logik-Teile ohne Netzwerk: Frame-De-/Encoding, WsToken-Signatur/Verify,
CSRF, Repositories gegen In-Memory-SQLite, Eingabe-Validierung. Keine End-to-End-
Netzwerktests (Nutzer testet die laufende App selbst).

## Dokumentation (Deliverables)

- **README.md**: Worum geht es, Features, Setup/Start, Architektur mit Mermaid-
  Diagrammen (Komponenten + Sequenz), Sicherheitshinweise, Konfiguration.
- **docs/RETROSPECTIVE.md**: Historischer Stand, was nicht funktioniert hat und warum,
  wie es geloest wurde, was effektiv umgesetzt wurde.

## Commit-Strategie

Auf `master`, viele kleine, thematisch abgegrenzte Commits (ein Commit pro
abgeschlossenem, sinnvollem Schritt). Groessere, riskante Umbauten optional in einem
kurzlebigen Branch, danach automatisch nach `master` gemergt.

## Ausdruecklich nicht im Scope

- Kein Multi-Server-/horizontales Skalieren (ein WS-Prozess).
- Kein Datei-/Bild-Upload im Chat.
- Keine Produktions-TLS-Terminierung (nur dokumentiert).
