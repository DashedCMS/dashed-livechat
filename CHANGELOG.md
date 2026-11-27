# Changelog

## v4.43.1

- Fix: the `X-Dashed-Livechat: off` opt-out now works with both middlewares. `InjectVisitorPresence` (inner) removed the header before `InjectChatWidget` (outer) saw it, so the widget was still injected. Neither middleware removes the header any more.

## v4.43.0 - 2026-11-27

### Added
- Een app kan één pagina uit de chatwidget en het bezoekerspresence-script halen door de responseheader `X-Dashed-Livechat: off` te zetten (constante `InjectChatWidget::SKIP_HEADER`, waarde `off`, hoofdletterongevoelig). Beide middlewares injecteren dan niets en verwijderen de header voor ze de response teruggeven.

## v4.42.0 - 2026-11-25

### Changed
- Nieuwe CMS-rechten `view_chat` en `manage_chat` op alle chatschermen; een migratie geeft ze aan elke bestaande rol. In de app is een medewerker ook begrensd door zijn CMS-chatrechten, en chatpushes gaan naar precies wie de chat van die site mag openen (via `toUsers()` van dashed-mobile-api als die er is). De medewerkerkeuze toont iedereen met toegang tot het CMS (vereist dashed-core ^4.124).

## v4.41.0 - 2026-11-19

### Added
- Tevredenheidsvraag onder de transcriptmail, AVG-provider voor gesprekken en bijlagen, en een sectie in het klantprofiel.

## v4.40.0 - 2026-11-16

### Added
- Het taakcentrum (het tabje Bezig) toont nu het indexeren van de chat-embeddings met voortgang.
