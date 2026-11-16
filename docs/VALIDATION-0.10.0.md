# HWADS 0.10.0 / Pi 0.3.0 validation

Built 2026-11-15. Local source and ZIP packages; not deployed to the live WordPress site or Pi.

## Passed

- PHP syntax checks for 22 plugin files using WordPress Playground PHP 8.3.
- Disposable WordPress 6.8.12: 76 existing native editor, embeds, authorization, device health and update-policy assertions.
- 27 additional WordPress assertions: visual schema and sanitization, image access/size/count limits, QR colors/geometry, frozen overlay assets, draft isolation, canonical builder-body saves and metadata copying, role capability simulation and read-only REST protection, Full HD checks and legacy-media exemption, WordPress timezone input/display conversions.
- Actual headless Chrome/WordPress editor: desktop workspace placement above native panels; phone layout without horizontal overflow; QR size/position/colors/selection; text settings; independent entrance/exit controls; template creation; real Update/reload persistence; private native editing URL returns 404 for logged-out visitors; toolbar role preview and return to SuperAdmin.
- Actual WebM video: default thumbnail extraction, scrubbing/selecting a timestamp, Media Library logo selection, and persistence after real WordPress Update.
- Actual Chromium offline loopback player: text/logo/QR rendering, cached image serving, no external requests, arrow navigation, and matching Fade crossfade with fully opaque incoming slide.
- 20 Python player tests: sync/checksums/offline retention, atomic activation including overlay downloads, cache cleanup protection, scoped local serving, heartbeat, media byte ranges and mpv process behavior.
- Focused JavaScript checks: add-placement choice flow, creation failure recovery, player arrow wrapping/ignored repeat keys/video interruption.
- ZIP integrity, matching shared editor/player renderer assets, shell installer syntax, and diff whitespace checks.

## What remains on the test site / device

- Divi theme 4.20.4 is proprietary and not installed in the disposable fixture. Verify the custom post types appear in Divi Post Type Integration, enable them, then verify backend and front-end builder editing and published landing-page styling. Core post-body synchronization and draft isolation were tested independently. Divi/theme CSS and global theme assets remain theme-controlled.
- Install both packages. The Pi installer preserves config and media, but deliberately leaves playback disabled. Sync once and explicitly enable browser mode afterward.
- Verify H.264/Canva video decoding, sustained overlay playback, display scaling, QR scan reliability and keyring behavior on the actual Raspberry Pi 4 and TV. Workstation Chrome checks do not establish hardware performance.
- Color contrast guidance is a warning, not a QR scan guarantee. Keep strong contrast and test on the target display.
- Native role preview is read-only and session-scoped across tabs. Select a team in its banner for scoped roles; return to SuperAdmin before saving.

## Test files

`tests/native.php`, `tests/embeds.php`, `tests/visuals.php` run against a disposable WordPress fixture. `tests/workspace-browser.mjs` and `tests/video-thumbnail-browser.mjs` target that local fixture; `tests/video-fixture.php` registers the synthetic test video first. `tests/player-visuals-browser.mjs` creates its own temporary local-player fixture. No real site media, passwords or device keys are packaged.
