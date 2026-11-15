#!/usr/bin/env python3
"""Launch the selected HWADS display engine; device credentials remain in sync only."""
import argparse
import json
import os
from pathlib import Path
import sys
p=argparse.ArgumentParser();p.add_argument('--root',default='~/hpads');args=p.parse_args()
root=Path(args.root).expanduser().resolve()
try:engine=json.loads((root/'config/player-engine.json').read_text())['engine']
except (OSError,ValueError,KeyError):engine='browser'
script='hwads_player.py' if engine=='mpv' else 'hwads_browser.py'
os.execv(sys.executable,[sys.executable,str(Path(__file__).parent/script),'--root',str(root)])
