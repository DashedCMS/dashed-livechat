# Changelog

## v4.42.0 - 2026-11-25

### Changed
- Nieuwe CMS-rechten `view_chat` en `manage_chat` op alle chatschermen; een migratie geeft ze aan elke bestaande rol. In de app is een medewerker ook begrensd door zijn CMS-chatrechten, en chatpushes gaan naar precies wie de chat van die site mag openen (via `toUsers()` van dashed-mobile-api als die er is). De medewerkerkeuze toont iedereen met toegang tot het CMS (vereist dashed-core ^4.124).

## v4.41.0 - 2026-11-19

### Added
- Tevredenheidsvraag onder de transcriptmail, AVG-provider voor gesprekken en bijlagen, en een sectie in het klantprofiel.

## v4.40.0 - 2026-11-16

### Added
- Het taakcentrum (het tabje Bezig) toont nu het indexeren van de chat-embeddings met voortgang.
