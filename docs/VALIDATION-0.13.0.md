# Integrated 0.13.0 / Pi 0.4.2 validation

2026-11-15. Local candidate; not deployed to a live WordPress site or Raspberry Pi.

Passed:

- Disposable WordPress 6.8.12 / PHP 8.3.36, using a socket-free in-memory Playground fixture: native editor/publication plus embeds/health/policy assertions; 38 analytics assertions; 11 thumbnail assertions; 16 player-update assertions; 45 approval assertions; 27 visual assertions; nine shared-layout assertions; 11 landing-options assertions; five simulated-Divi starter assertions.
- The new thumbnail column is tested on both Templates and Placements, with uncropped linked image rendering, missing-media fallback, unauthorized-access exclusion, video-icon fallback and existing video-poster support.
- 42 Python tests: 12 analytics/observed-playback cases, 13 sync regressions and 17 updater tests. Tests exercise queue retention, retries, conditional acknowledgement, bounded storage, exclusion, partial/natural/error outcomes, authenticated packages, atomic release switching and rollback. A new test preserves the analytics database and its WAL/SHM paths across update and rollback.
- Seven JavaScript playback-accounting scenarios plus existing arrow-navigation regression checks.
- The UNMODIFIED 0.4.0 stable updater validates the real 0.4.2 archive, exact original runtime file set, manifest version and all SHA-256 hashes. The unchanged PHP updater accepts/imports/queues the same real ZIP in WordPress. Installer and stable updater code are byte-for-byte unchanged from 0.12.0.
- All loaded PHP modules parse in the integration fixture. Python compilation, JavaScript syntax, shell installer syntax, ZIP CRC checks and final package checksums pass.

Implementation integration:

Analytics recorder, queue and delivery definitions are embedded in the already-permitted `hwads_sync.py`. Players import the recorder there. No `hwads_analytics.py` is shipped, no new runtime allowlist entry is required, and protocol 1 remains unchanged. Queue files remain in config outside releases. The analytics status CLI is `hwads_sync.py --analytics-status`.

Remaining:

- Real loopback HTTP/browser rendering tests cannot run under this session's socket-bind sandbox restrictions. The earlier server/real-IPC tests are not counted as passes; deterministic JavaScript and in-memory IPC tests exercise the accounting logic instead.
- Actual Pi, Chromium/mpv decoding, SD write overhead, user systemd services, power loss, clock recovery, sustained offline catch-up and real update/rollback need testing on the device.
- SQLite translation in Playground does not establish MySQL/MariaDB/InnoDB concurrent-lock behavior. Validate the integrated build on the staging hosting account before production use.
- Actual proprietary Divi theme behavior remains outside the disposable fixture.

No live deployment, GitHub push or remote device command was issued. The prior test packages are not overwritten.
