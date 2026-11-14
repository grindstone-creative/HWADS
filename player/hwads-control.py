#!/usr/bin/env python3
"""Enable/disable HWADS playback without interrupting media sync."""
import argparse
import json
import os
from pathlib import Path
import signal
import subprocess
import sys

def stop_players(root):
    # Match only this installation's legacy launcher or new Python player.
    # Snapshot descendants before stopping the launcher so mpv cannot respawn.
    targets, parents = [], {}
    for p in Path('/proc').glob('[0-9]*'):
        try:
            argv = [s.decode() for s in (p / 'cmdline').read_bytes().split(b'\0') if s]
            stat = (p / 'stat').read_text().rsplit(')', 1)[1].split()
            pid = int(p.name)
            parents[pid] = int(stat[1])
            scripts = {str(root / 'scripts/start-player.sh'), str(root / 'scripts/hwads_player.py')}
            if pid != os.getpid() and any(a in scripts for a in argv[:3]):
                targets.append(pid)
        except (OSError, ValueError, UnicodeError):
            continue
    descendants = set(targets)
    while True:
        extra = {pid for pid, parent in parents.items() if parent in descendants}
        if extra <= descendants:
            break
        descendants |= extra
    for pid in targets + sorted(descendants - set(targets)):
        try:
            os.kill(pid, signal.SIGTERM)
        except ProcessLookupError:
            pass

def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('command', choices=['disable', 'enable', 'status'])
    p.add_argument('--root', default='~/hpads')
    args = p.parse_args()
    root = Path(args.root).expanduser().resolve()
    (root / 'config').mkdir(parents=True, exist_ok=True)
    flag = root / 'config/player.disabled'
    if args.command == 'disable':
        flag.touch(mode=0o600)
        stop_players(root)
        print('Playback disabled. Media sync can continue. Use enable to resume.')
    elif args.command == 'enable':
        flag.unlink(missing_ok=True)
        (root / 'logs').mkdir(exist_ok=True)
        with (root / 'logs/launcher.log').open('ab') as log:
            subprocess.Popen([sys.executable, str(root / 'scripts/hwads_player.py'), '--root', str(root)], stdout=log, stderr=log, start_new_session=True)
        print('Playback enabled. See logs/player.log if the display does not start.')
    else:
        print('Playback disabled' if flag.exists() else 'Playback enabled (check player-state.json for actual activity)')
        try:
            print((root / 'config/player-state.json').read_text())
        except OSError:
            pass

if __name__ == '__main__':
    main()
