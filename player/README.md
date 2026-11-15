# HWADS Pi player 0.2.0

Local Chromium kiosk playback with transitions and placement QR overlays. Existing verified/offline sync remains in Python; WordPress is not loaded on the screen. Requires Raspberry Pi OS Desktop, Python 3.9+, Chromium and mpv (fallback). Run installation as the desktop user, not root.

## Upgrade the working Pi

```sh
sudo apt install chromium mpv python3 unzip
mkdir -p ~/hwads-setup
cd ~/hwads-setup
curl -fL https://github.com/grindstone-creative/HWADS/raw/main/dist/hwads-pi-0.2.0.zip -o hwads-pi-0.2.0.zip
unzip -o hwads-pi-0.2.0.zip
bash hwads-pi/install.sh
python3 ~/hpads/scripts/hwads_sync.py --once
python3 ~/hpads/scripts/hwads-control.py enable --engine browser
```

Installer preserves `config/device.json`, media and placeholders. It stops this installation’s old player and installs with playback disabled. It selects browser mode, backs up the legacy launcher/playlist/autostart once, and restarts an already-running user sync service to load the new client. On first installation, edit `~/hpads/config/device.json` with the site URL, device ID and 43-character device key, then `systemctl --user enable --now hwads-sync`. Keep config private (`chmod 600`). Never paste the key into chat or a public URL.

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
