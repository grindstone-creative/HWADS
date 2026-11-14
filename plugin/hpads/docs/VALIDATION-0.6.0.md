# HPADS 0.6.0 validation

Isolated local WordPress test installation only; no live staging or physical Pi access.

- All 14 PHP source files parsed on PHP 8.0 and PHP 8.3.
- 58 migration/integration checks passed: native type registration, exact preservation and ID mapping, retained legacy rows, frozen versions and credentials, repeat migration, new native writes, stale editor protection, summer/winter timezone conversion, DST gap rejection, inline placement saves, Media Library selection, independent copies and scoped access.
- Browser checks passed for inherited seconds, editable date/time controls, card URL/QR/transition serialization, actual placement edit links, Media Library and existing-ad actions, and playback preview navigation/pause.
- Playlist editor screenshot inspected. Browser attachment selection uses a mocked WordPress media dialog; server-side existing-attachment authorization was exercised separately.
- Not tested: live hosting/MySQL conversion, actual Divi installation, physical player rendering, real QR scanning or engagement reporting. Divi/editor restructuring is intentionally not part of this checkpoint.
