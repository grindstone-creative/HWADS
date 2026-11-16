> **HWADS Box 0.5.4 / plugin 0.15.9:** supports acknowledged pending-analytics checkpoint purge. Update the plugin and Box runtime to use purge; older runtimes ignore that command. No updater protocol, runtime filenames or dependency changes. Playback and reported history are preserved. Snapshot purges can discard unreported metrics; active plays can enqueue fresh checkpoints.

> **Pi 0.5.3** reports RAM, CPU usage/load/cores, CPU temperature and uptime with the existing heartbeat. Pair with HWADS 0.14.4. No new dependencies or updater protocol changes. Missing Linux probes remain unknown.

> **Pi 0.5.2:** supports location Playback mix with HWADS 0.14.2, using observed playback time, offline shuffle state, fallback and original playlist analytics. Existing updater-ready devices can install this ZIP through Devices → Player Updates. No new dependencies or runtime filenames are needed. Older version-specific bootstrap steps below remain a reference; use the matching current ZIP for a fresh install.

> Pi **0.5.1** fixes legacy house fallback/order, reports playing files for cleanup protection and preserves truthful disabled/error heartbeat states. Chromium remains the default; mpv is an explicitly selectable alternative.

> Integrated candidate: WordPress **0.13.0** / Pi **0.4.2**. Includes screen analytics and native list thumbnails. See `docs/UPDATE-0.13.0.md` at the project root for installation and counting limits. Earlier version-specific instructions below describe the updater baseline.

# HWADS Pi player 0.4.0 / 0.4.1

Local Chromium kiosk playback with transitions and placement QR overlays. Existing verified/offline sync remains in Python; WordPress is not loaded on the screen. Requires Raspberry Pi OS Desktop, Python 3.9+, Chromium and mpv (fallback). Run installation as the desktop user, not root.

## One-time updater installation

Install the WordPress 0.12.0 plugin first. Download hwads-pi-0.4.0.zip to the Pi's Downloads folder. In a terminal on the Pi desktop (not a root shell):

```sh
cd ~/Downloads
unzip -o hwads-pi-0.4.0.zip
bash hwads-pi/install.sh
systemctl --user enable --now hwads-sync
python3 ~/hpads/scripts/hwads_sync.py --once
python3 ~/hpads/scripts/hwads-control.py enable --engine browser
```

If already extracted, run the installer from the extracted hwads-pi directory. Chromium, mpv and Python3 must already be installed. Installation preserves device.json, cached media and placeholders, installs stable user updater/launcher services, and leaves playback disabled until the enable command. On a fresh device, edit ~/hpads/config/device.json with your HTTPS site URL, device ID and device key before enabling sync. Never put that key into an update ZIP or chat.

Once the player has run from the desktop, its display environment is recorded for remote restarts. Under WordPress → HWADS → Devices → Player Updates, wait for the device to report updater readiness and installed version 0.4.0. Upload hwads-pi-0.4.1.zip there, select the Pi, and install. 0.4.1 intentionally has the same functionality and a newer reported runtime version so the first remote update can be verified. A startup failure restores the previous code. Roll back is an explicit action after a successful remote update. User configuration and media are outside releases and are preserved.

Updates are manual, next-check-in deployments. The shared automatic update window / TV-off scheduling is not active. Updating the stable updater protocol or OS dependencies still requires a manual install. Check logs/updater.log and WordPress status if anything fails. Hardware/systemd/Chromium recovery must be verified on the actual Pi; automated tests simulate services and rollback.

## Stop or switch players

```sh
python3 ~/hpads/scripts/hwads-control.py disable
python3 ~/hpads/scripts/hwads-control.py enable --engine mpv
python3 ~/hpads/scripts/hwads-control.py enable --engine browser
python3 ~/hpads/scripts/hwads-control.py status
```

Disable persists across reboot and overrides browser recovery. Desktop autostart uses the selected engine. mpv remains available for video troubleshooting; it does not render QR overlays or transitions. Stop sync separately with `systemctl --user stop hwads-sync`.

## Playback and recovery

- Files download with declared size/SHA-256 verification; only a complete manifest is activated atomically. Polling uses ETags at the server interval (default five minutes) plus jitter. Wi-Fi loss retains the last working playlist. A previous complete manifest is retained for safe cleanup.
- One browser window plays local media via a small loopback server. The server binds only `127.0.0.1`, uses a random route token and exposes no device configuration/API key. Video byte ranges are supported. No WordPress/theme/Divi page, CDN, QR service or internet dependency is needed for playback.
- Images use published durations; videos run to completion, subject to scheduled end. Media load/decoding errors and stalled videos advance to another placement. Empty assignments clear old ads. Primary/house scheduling and local placeholder fallback are retained.
- Independent entrance/exit animations: fade, slide, zoom, directional wipes, diagonal wipe, circular and split reveals. They use browser animation effects; no extra video assets or animation library. Actual smoothness depends on Pi GPU/browser setup and requires a device test.
- Placement QR is generated locally with the same bundled encoder as WordPress. It has a white quiet zone and appears bottom right at a responsive size. It links to the published placement landing page, tagged `hwads_source=qr`. Device-settings QR is separate and never overlaid here. Changing QR position/size in the editor and engagement tracking are deferred; this channel marker does not itself count scans.
- Videos autoplay muted in this initial browser build. H.264 MP4/AAC is the intended upload profile after validation on the Pi’s Chromium build. WebM also depends on browser decoding support. No automatic transcoding is provided. Test your actual Canva files; fallback to mpv if needed.
- Browser has its own profile, kiosk/autoplay startup flags and no first-run flow. An unresponsive page is restarted after 120 seconds; three rapid browser failures persistently disable playback instead of trapping you in a fullscreen restart loop. Check `logs/browser.log`; enable resumes it. OS update prompts and compositor screen blanking are outside the player: disable screen blanking in Raspberry Pi’s display settings and test reboot behavior before unattended use. GPU acceleration is not forcibly disabled.

## Device health and cleanup (WordPress 0.9.0+)

Heartbeat carries player/sync version, browser/mpv engine, current placement/version, last successful sync and free disk space. Device page readings are snapshots at the configured heartbeat interval; refresh the page for new values. No proof-of-play or engagement counters are collected.

Purge unused media queues a command for the next heartbeat. Only unused direct files in `media/` older than 24 hours are deleted. Current/previous playlist media and reported playing files are protected; config, placeholders, subdirectories and symlinks are untouched. Cleanup completion is acknowledged on the following heartbeat. Disabled playback may continue syncing/cleaning. Only users with device-management access can queue cleanup, and unsaved editor changes are not saved by that button.

## Validation and limits

Automated Python tests cover verified activation, offline retention, cache cleanup safety, heartbeat, loopback access controls, video ranges and persistent mpv fallback. Local Chromium tests exercise actual image/video playback, wipes, QR toggling and empty playlists with no external requests. These are workstation tests: Raspberry Pi hardware acceleration, H.264 support, desktop autostart, screen blanking, recovery under OS failure and sustained operation still need on-device testing. Do not treat this as a completed Pi deployment.

Rollback without uninstalling: disable, then `enable --engine mpv`. To restore the original legacy shell player, stop/disable sync and restore the `.pre-hwads` launcher/autostart/playlist backups. Legacy list-only playback cannot read the new object playlist. No media is deleted by installation.

## Keyboard controls

In browser mode, with the player focused, Right Arrow skips to the next placement and Left Arrow returns to the previous placement, wrapping at either end. Videos can be skipped manually; automatic playback resumes with the new placement. Held keys do not repeatedly skip. The mpv fallback uses its own controls.

The dedicated kiosk profile uses `--password-store=basic` to avoid desktop keyring prompts. Do not save website passwords in this profile; its password store is unencrypted.

## 0.3.1 visual overlays

Text, Media Library logos and custom QR geometry/colors use plain HTML/CSS/SVG in the dedicated Chromium player. All image assets are downloaded and verified before atomic playlist activation, retained offline, and protected from cleanup while active/previous/in playback. No design library, remote font or QR service is loaded. Matching fade-out/fade-in uses an opaque incoming slide under the fading outgoing slide to avoid a black dip.

Install this player update together with plugin 0.10.0. The installer preserves device configuration and media, stops the old player, and leaves playback disabled. Sync once, then enable browser mode. Confirm video performance and QR scanning on the actual Pi/TV before unattended use.
