# HWADS 0.8.1 / Pi 0.1.0 validation

59 WordPress integration assertions passed, including per-user dashboard defaults/preferences and existing published snapshots/permissions. Actual native screens render. Pi suite: 12 automated tests passed for download activation/failure, credentials, checksums, offline retention, schedules/orientation, heartbeat, persistent IPC playback and disable/restart behavior. IPC test uses a fake mpv process; no actual Pi, desktop, systemd user service or live API has been tested. Installer passes bash syntax check.

Sync uses the existing hpads/v1 API and downloads static images/videos. QR overlays, proof-of-play, remote control, web slide rendering and automatic media cache cleanup are deferred.
