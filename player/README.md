# HWADS Pi sync/player 0.1.0

For Raspberry Pi OS with a desktop session, Python 3.9+ and mpv. Install once per device, as its normal desktop user (not root). Existing `~/hpads` media/placeholders remain usable. No WordPress username/password is needed; a device ID and its unique key are sufficient. The key only authorizes that device's API and heartbeat, not settings or wp-admin.

## Install and configure

Extract this package on the Pi, open a terminal in its directory, then:

```sh
sudo apt install mpv python3
bash install.sh
nano ~/hpads/config/device.json
```

Set the WordPress installation's final HTTPS URL, numeric device ID and key from HWADS. Keep `device.json` private (`chmod 600`). Rotating the key requires replacing it in this file. The installer backs up the old launcher/autostart file once, installs with playback **disabled**, and stops this installation's legacy launcher and its mpv child. Existing unrelated mpv sessions are not targeted.

Test one sync before starting background sync:

```sh
python3 ~/hpads/scripts/hwads_sync.py --once
cat ~/hpads/logs/sync.log
systemctl --user enable --now hwads-sync
python3 ~/hpads/scripts/hwads-control.py enable
```

The sync service starts in your user session. Desktop autostart starts playback at login only when enabled. No root system service or system-wide changes are installed. On a headless Pi, desktop playback needs an active graphical session. Optional OS autologin is configured outside this package.

## Stop the fullscreen player

```sh
python3 ~/hpads/scripts/hwads-control.py disable
```

This persists across reboots/logins, closes this player's mpv window, and leaves sync running. Resume with `enable`. `status` shows the enabled flag and last player status. To stop downloading too:

```sh
systemctl --user stop hwads-sync
```

## Behavior

- Polls with ETags at the server interval (default five minutes) plus up to 30 seconds jitter. Heartbeat at the same interval; status reflects the local player rather than claiming that a download means playback.
- Downloads media into content-addressed filenames; verifies declared size and SHA-256 before activating. Activation is one atomic replacement of `config/playlist.json`. Partial downloads and failures leave the previous complete playlist active. Credentials go only to the configured HTTPS API, never media/CDN requests. Redirects are refused: configure final HTTPS URLs.
- One persistent mpv window loads placements through local IPC instead of reopening fullscreen for each slide. Images use the published duration; videos play until completion. Schedules use UTC from WordPress; the Pi clock must be correct. Device orientation is checked again locally.
- Plays primary items, adds house items if configured in normal rotation, falls back to house when no primary is active, then local placeholder images. Rechecks the playlist between placements. A currently playing video finishes before the next version is picked up; reaching its scheduled end interrupts it.
- Old list-format local playlists still work before the first sync. Old content files are retained intentionally; automatic cache cleanup is deferred, so monitor disk space. Logs rotate at 1 MB with two backups. A second sync/player instance exits without duplicating work.
- Native mpv transitions/QR overlays, web/Divi slide rendering, proof-of-play and remote commands are **not** implemented. The included playback is images/videos only. Legacy Pi script does not understand the new object-format playlist after sync; keep these new sync and player scripts together.

## Rollback

Disable playback and stop/disable the sync service. Restore `scripts/start-player.sh.pre-hwads` and `~/.config/autostart/hpads.desktop.pre-hwads` if needed. Restore your old list-format `playlist.json` from `config/playlist.json.pre-hwads` (if it existed at install) or your own backup before using the old player; the installer does not overwrite it, but the first successful sync replaces it. No media is deleted.

## Verification

Automated tests cover complete/partial download activation, checksums, ETag polling, credential separation, orientation, schedules, fallback and offline retention. Linux/Raspberry Pi desktop playback and user-service behavior still require an on-device test; these have not been tested on your live Pi.
