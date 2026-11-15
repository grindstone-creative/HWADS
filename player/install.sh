#!/bin/bash
set -eu
SOURCE=$(cd -- "$(dirname -- "$0")" && pwd)
ROOT="${1:-$HOME/hpads}"
case "$ROOT" in /*) ;; *) echo 'Use an absolute installation directory.'; exit 1;; esac
command -v python3 >/dev/null
command -v chromium >/dev/null || command -v chromium-browser >/dev/null || { echo "Install Chromium first: sudo apt install chromium"; exit 1; }
command -v mpv >/dev/null || { echo 'Install mpv first: sudo apt install mpv'; exit 1; }
mkdir -p "$ROOT/scripts" "$ROOT/config" "$ROOT/logs" "$ROOT/media" "$ROOT/placeholders" "$HOME/.config/autostart" "$HOME/.config/systemd/user"
# Install disabled: replacing the player must never seize the screen during setup.
touch "$ROOT/config/player.disabled"
for script in hwads_sync.py hwads_player.py hwads_browser.py hwads_launch.py hwads-control.py; do
    install -m 755 "$SOURCE/$script" "$ROOT/scripts/$script"
done
mkdir -p "$ROOT/scripts/browser/vendor"
install -m 644 "$SOURCE/browser/"*.html "$SOURCE/browser/"*.js "$SOURCE/browser/"*.css "$ROOT/scripts/browser/"
install -m 644 "$SOURCE/browser/vendor/qrcodegen.js" "$ROOT/scripts/browser/vendor/"
python3 "$ROOT/scripts/hwads-control.py" disable --root "$ROOT"
if [ -f "$ROOT/scripts/start-player.sh" ] && [ ! -f "$ROOT/scripts/start-player.sh.pre-hwads" ]; then
    cp "$ROOT/scripts/start-player.sh" "$ROOT/scripts/start-player.sh.pre-hwads"
fi
if [ -f "$ROOT/config/playlist.json" ] && [ ! -f "$ROOT/config/playlist.json.pre-hwads" ]; then
    cp "$ROOT/config/playlist.json" "$ROOT/config/playlist.json.pre-hwads"
fi
# JSON/shell quoting is generated, never interpolate configuration as shell code.
python3 - "$ROOT" <<'PY'
import json, os, shlex, sys
from pathlib import Path
r=Path(sys.argv[1]);home=Path.home()
(r/'config/player-engine.json').write_text(json.dumps({'engine':'browser'}))
(r/'scripts/start-player.sh').write_text('#!/bin/sh\nexec python3 '+shlex.quote(str(r/'scripts/hwads_launch.py'))+' --root '+shlex.quote(str(r))+'\n')
os.chmod(r/'scripts/start-player.sh',0o755)
desktop=home/'.config/autostart/hpads.desktop'
if desktop.exists() and not desktop.with_suffix('.desktop.pre-hwads').exists():
    desktop.with_suffix('.desktop.pre-hwads').write_bytes(desktop.read_bytes())
# Restrict install paths in desktop/systemd Exec: escaped through their own quoting format.
if any(ch in str(r) for ch in ('\n','\r','%')):
    raise SystemExit('Installation path cannot contain newlines or %')
quoted=lambda p: json.dumps(str(p))
desktop.write_text('[Desktop Entry]\nType=Application\nName=HWADS Signage Player\nExec='+quoted(r/'scripts/start-player.sh')+'\nTerminal=false\nX-GNOME-Autostart-enabled=true\n')
unit='[Unit]\nDescription=HWADS playlist sync\nAfter=network-online.target\n\n[Service]\nType=simple\nExecStart=/usr/bin/python3 '+quoted(r/'scripts/hwads_sync.py')+' --root '+quoted(r)+' --config '+quoted(r/'config/device.json')+'\nRestart=on-failure\nRestartSec=60\nUMask=0077\n\n[Install]\nWantedBy=default.target\n'
(home/'.config/systemd/user/hwads-sync.service').write_text(unit)
PY
if [ ! -f "$ROOT/config/device.json" ]; then
    install -m 600 "$SOURCE/device.example.json" "$ROOT/config/device.json"
fi
chmod 600 "$ROOT/config/device.json"
systemctl --user daemon-reload
# The running Python sync process must pick up the new metadata/heartbeat fields.
if systemctl --user is-active --quiet hwads-sync; then systemctl --user restart hwads-sync; fi
printf '%s\n' "Installed with playback DISABLED. Edit $ROOT/config/device.json, then follow README.md."
