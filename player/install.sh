#!/bin/bash
set -eu
SOURCE=$(cd -- "$(dirname -- "$0")" && pwd)
ROOT="${1:-$HOME/hpads}"
case "$ROOT" in /*) ;; *) echo 'Use an absolute installation directory.'; exit 1;; esac
command -v python3 >/dev/null
command -v chromium >/dev/null || command -v chromium-browser >/dev/null || { echo 'Install Chromium first.'; exit 1; }
command -v mpv >/dev/null || { echo 'Install mpv first.'; exit 1; }
python3 "$SOURCE/hwads_update.py" bootstrap --root "$ROOT" --source "$SOURCE"
python3 - "$ROOT" <<'PY'
import json,sys
from pathlib import Path
root=Path(sys.argv[1]);home=Path.home()
if any(c in str(root) for c in ('\n','\r','%')):raise SystemExit('Unsupported installation path')
units=home/'.config/systemd/user';units.mkdir(parents=True,exist_ok=True)
quoted=lambda p:json.dumps(str(p))
common='\nRestart=on-failure\nRestartSec=5\nUMask=0077\n\n[Install]\nWantedBy=default.target\n'
(units/'hwads-sync.service').write_text('[Unit]\nDescription=HWADS playlist sync\nAfter=network-online.target\n\n[Service]\nType=simple\nExecStart=/usr/bin/python3 '+quoted(root/'scripts/hwads_sync.py')+' --root '+quoted(root)+' --config '+quoted(root/'config/device.json')+common)
(units/'hwads-updater.service').write_text('[Unit]\nDescription=HWADS player updater\nAfter=network-online.target\n\n[Service]\nType=simple\nExecStart=/usr/bin/python3 '+quoted(root/'bootstrap/hwads_update.py')+' daemon --root '+quoted(root)+common)
autostart=home/'.config/autostart';autostart.mkdir(parents=True,exist_ok=True);desktop=autostart/'hpads.desktop'
if desktop.exists() and not desktop.with_suffix('.desktop.pre-hwads').exists():desktop.with_suffix('.desktop.pre-hwads').write_bytes(desktop.read_bytes())
desktop.write_text('[Desktop Entry]\nType=Application\nName=HWADS Signage Player\nExec='+quoted(root/'bootstrap/start-player.sh')+'\nTerminal=false\nX-GNOME-Autostart-enabled=true\n')
PY
systemctl --user daemon-reload
systemctl --user enable --now hwads-updater
if systemctl --user is-active --quiet hwads-sync; then systemctl --user restart hwads-sync; fi
printf '%s\n' 'Installed updater. Device settings and media preserved. Playback is DISABLED; enable browser playback after checking configuration.'
