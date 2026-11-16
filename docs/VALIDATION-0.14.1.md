# HWADS 0.14.1 / Pi 0.5.1 validation

November 15, 2026. Local candidate only; not installed on production or physical Pis.

- 71 Python player tests passed, including five new connection regressions and existing weighted mixing, browser, mpv, analytics and updater checks.
- 293 WordPress fixture assertions passed across native saves, embeds, dashboard, device health, update policy, analytics, thumbnails, player updates, venue approvals, visual settings, landing layouts/options, simulated Divi, scheduled updates, weighted mixing and eight new connection/migration checks.
- 27 PHP files parsed successfully on PHP 8.3; 11 first-party JavaScript runtime files parsed successfully.
- Headless Chrome verified Inactive/Scheduled/Expired grey/slash labels, mobile fit, editor visibility, and saved canonical Active state across playlist and individual placement editors. No page errors.
- New and previous player archive checksums validate; plugin ZIP matches current source. Checksums are in dist/release-checksums-0.14.1.json.

The standalone title-field.php check requires its separate browser naming precondition; it was not counted. Physical Pi decoding/desktop startup, actual Divi installation and live production behavior remain untested. Python tests retain non-failing logging ResourceWarnings.

Screenshots: screenshots/connections-inactive-desktop.png and screenshots/connections-expired-mobile.png.
