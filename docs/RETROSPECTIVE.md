# Retrospektive: Vom Prototyp zur funktionierenden App

Diese Datei dokumentiert den **Ausgangszustand** des ueber fuenf Jahre alten
Projekts, **warum es nicht (vollstaendig) funktionierte**, **wie die Probleme
geloest wurden** und **was am Ende effektiv umgesetzt** wurde.

---

## 1. Ausgangslage (der alte Stand)

Das Repository enthielt **zwei fast identische Projekte** nebeneinander:

- `PHPWebSocketChat/` – die eigentliche App mit Login/Registrierung, Router,
  MVC-Ansatz, Datenbank und mehreren Views.
- `PHPWebSocketChatServer/` – ein aelterer, einfacherer Prototyp (anonymer Chat
  mit Zufalls-ID, kein Login). Weitgehend ein Duplikat.

Technisch basierte der WebSocket-Server auf **Ratchet 0.3.5** (ca. 2015) und das
Frontend auf **jQuery 1.11.1** (2014). Die Idee stimmte, aber die Umsetzung war
an vielen Stellen unfertig und unsicher.

Die Grundidee – ein Livechat mit PHP-WebSockets – war goldrichtig und
tatsaechlich nah dran. Es fehlte an vielen kleinen, aber entscheidenden Stellen
die Vollstaendigkeit, und das Sicherheitsmodell war praktisch nicht vorhanden.

---

## 2. Was nicht funktionierte – und warum

### Harte Fehler (die App lief nicht durch)

| Problem | Ursache |
|--------|---------|
| Registrierung brach ab | `register()` lud `app/Views/register.view.php`, **diese Datei existierte nicht** (nur `loginRegister.view.php`) → Fatal Error. |
| Der gesamte Chat-JS lief nicht | Die Views luden `js/jquery.js`, aber in der Haupt-App **fehlte jQuery** (lag nur im Prototyp-Ordner). |
| `home()` warf einen Fehler | `PDO->prepare('')` mit **leerem SQL-String**. |
| Chat-Auswahl fuehrte ins Leere | Alle Dropdown-Optionen hatten `value='volvo'`, der „Join"-Button hatte keinen Handler. |
| Zwei Datenbank-Layer parallel | Login/Register nutzten **mysqli** (DB `websocket`), der Rest **PDO** (DB `WebSocket`). Auf case-sensitiven Systemen sind das zwei verschiedene Datenbanken. |
| Kaputter Helfer | `core/helpers.php::db()` griff auf eine undefinierte Variable zu und gab nichts zurueck. |

### Konzeptionelle Luecken

- **Keine Persistenz**: Die `history`-Tabelle hatte nicht einmal eine Spalte fuer
  den Nachrichtentext oder einen Zeitstempel. Nachrichten existierten nur
  fluechtig im Speicher.
- **Keine Raumtrennung**: Der WS-Server broadcastete an *alle* Verbindungen; die
  User-/Raum-Verwaltung war auskommentiert.
- **Keine Userliste, keine Timestamps, keine Emojis.**

### Sicherheitsluecken (14+)

1. **Stored/Reflected XSS** im Chat: Nutzer-Eingaben wurden ungefiltert als HTML
   gebroadcastet und per `$('#chat_output').append(json.msg)` als **rohes HTML**
   gerendert – beliebiges JavaScript lief bei allen Mitlesenden.
2. **XSS ueber Benutzernamen und Chat-Namen** in den Views (kein Escaping).
3. **WebSocket ohne Authentifizierung**: jede Verbindung wurde akzeptiert.
4. **Kein Origin-Check** → Cross-Site WebSocket Hijacking moeglich.
5. **Identitaets-Spoofing**: Die `user_id` kam ungeprueft vom Client.
6. **Hartcodierte DB-Zugangsdaten**, `root` **ohne Passwort**, an drei Stellen.
7. **Kein CSRF-Schutz** in den Formularen.
8. **Session-Fixation**: kein `session_regenerate_id()` nach dem Login,
   `session_start()` doppelt aufgerufen.
9. **Info-Leaks**: SQL-/Fehlermeldungen wurden direkt ausgegeben
   (`... or die(mysqli_error())`, `echo $e->getMessage()`).
10. **Kein Rate-Limit, keine Laengenbegrenzung** im WS-Handler.
11. **`json_decode` ohne Validierung** → Fehler/Notices bei ungueltigem Input.
12. **Unverschluesseltes `ws://`**, Port hartcodiert.
13. **Veraltete Abhaengigkeiten** (Ratchet 0.3.5, jQuery 1.11.1) mit bekannten
    Alt-Lasten, lauffaehig nur auf sehr alten PHP-Versionen.
14. **Toter/uneinheitlicher Code** (ungenutzte mysqli-Wrapper-Klasse mit leeren
    Credentials, doppeltes Projekt), der die Angriffsflaeche und Verwirrung erhoehte.

### Warum es „fast" funktionierte

Der Kern – Ratchet nimmt Verbindungen an, empfaengt JSON, broadcastet – lief im
Prinzip. Es scheiterte an einer Kette kleiner Unfertigkeiten (fehlende Dateien,
leeres SQL, fehlendes jQuery, nicht verdrahtete Auswahl) und daran, dass die
eigentlich noetigen Bausteine (Persistenz, Raeume, Auth, Escaping) nie fertig
gebaut wurden.

---

## 3. Wie es geloest wurde

Statt die alten, veralteten Abhaengigkeiten zu flicken, wurde bewusst auf eine
**dependency-freie Neufassung** gesetzt (bestaetigt mit dem Auftraggeber):

- **WebSocket-Server von Grund auf** nach RFC 6455 (`stream_socket_server` +
  `stream_select`). Kein Ratchet, kein Composer.
- **SQLite via PDO** statt MySQL: kein Datenbankserver, keine Zugangsdaten,
  sofort lauffaehig. Ein **einziges** DB-Layer.
- **Modernes Vanilla-JS** statt jQuery.
- Aufgeraeumte Struktur (`src/` mit klar getrennten Verantwortlichkeiten),
  das Duplikat-Projekt und der tote Code wurden entfernt (die Historie bleibt in
  git erhalten).

Die Sicherheitsluecken wurden gezielt geschlossen:

| Alte Luecke | Loesung in dieser Fassung |
|-------------|---------------------------|
| XSS im Chat | Rohtext speichern, Client rendert nur `textContent`; Views escapen via `e()`; strikte CSP. |
| WS ohne Auth | HMAC-signiertes, kurzlebiges `WsToken`, an die Web-Session gebunden. |
| Kein Origin-Check | Origin-Allowlist im Handshake. |
| Identitaets-Spoofing | Identitaet stammt ausschliesslich aus dem verifizierten Token. |
| Hartcodierte root-DB | SQLite ohne Credentials; Token-Secret zufaellig und ausserhalb des Repos. |
| Kein CSRF | Synchronizer-Token in allen Formularen. |
| Session-Fixation | `session_regenerate_id(true)` nach Login; HttpOnly/SameSite-Cookies. |
| Info-Leaks | Zentrales Fehler-Handling, keine internen Meldungen nach aussen. |
| Kein Rate-Limit/Laenge | Rate-Limit pro Verbindung + Laengenbegrenzung im Hub/Frame. |
| mysqli+PDO gemischt | Nur noch PDO, ausschliesslich Prepared Statements. |
| Veraltete Dependencies | Vollstaendig entfernt. |

---

## 4. Was effektiv umgesetzt wurde

**Funktional**

- Registrierung, Login, Logout (sichere Passwort-Hashes, CSRF, Session-Handling).
- Raeume erstellen und betreten – jetzt tatsaechlich funktional und serverseitig
  getrennt.
- Echtzeit-Nachrichten pro Raum ueber einen selbstgebauten WebSocket-Server.
- **Persistenz** in SQLite (echte `messages`-Tabelle mit Text und Zeitstempel)
  inkl. Verlauf beim Betreten.
- **Online-Userliste** (Presence) und **Live-Status**-Anzeige.
- **Emoji-Picker** mit Kategorien.
- Zeitstempel, Nachrichten-Gruppierung, aus dem Namen abgeleitete Avatar-Farben,
  automatischer Reconnect, helles/dunkles responsives Design.

**Qualitaet & Betrieb**

- Dependency-freier Unit-Test-Runner mit Tests fuer die gesamte Kernlogik
  (Framing, Handshake, Token, CSRF, Repositories, Hub, Auth, Routing).
- Ein Start-Skript (`bin/start.sh`) startet beide Prozesse; die Datenbank wird
  beim ersten Start automatisch angelegt.
- Dokumentation: dieses Dokument, ein ausfuehrliches README mit
  Architektur-Diagrammen, sowie Design-Spec und Implementierungsplan unter
  `docs/superpowers/`.

**Bewusst nicht umgesetzt** (ausserhalb des Rahmens): horizontale Skalierung
ueber mehrere WS-Prozesse, Datei-/Bild-Uploads im Chat, produktive
TLS-Terminierung (nur im README als Weg beschrieben).

---

## 5. Fazit

Der alte Stand war naeher an „fertig" als am Code sichtbar – die Idee und der
grobe Aufbau stimmten. Was fehlte, war die konsequente Fertigstellung der
letzten Meter (Persistenz, Raeume, Auth, Escaping) und ein tragfaehiges
Sicherheitsmodell. Durch die dependency-freie Neufassung laeuft die App nun ohne
Fremdsysteme, ist deutlich einfacher zu starten und schliesst die bekannten
Schwachstellen.
