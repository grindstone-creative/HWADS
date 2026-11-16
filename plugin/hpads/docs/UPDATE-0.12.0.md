# HWADS 0.12.0 test update

## WordPress

Upload `dist/hwads-0.12.0.zip` under Plugins → Add New → Upload Plugin and replace the installed HWADS plugin. Reload open editor tabs. This is a test build; no live installation has been performed by this chat.

- **Users and permissions:** HWADS → Users → Edit User sets the team role and venue/advertiser memberships. Role permissions are defined in the plugin; there is no editable permission matrix.
- **Venue approvals:** Venues → Edit Venue → Ad approvals. Choose whether outside advertisers require approval, select Any one or Every selected reviewer, then choose the actual reviewers. Users must already belong to this venue, unless they are global HWADS administrators. Save Venue.
- **Review work:** HWADS → Approvals → Needs my review. The sidebar count and dashboard link lead here. All venue approvals offers approved, rejected and resubmission views. Ad Placements also has an approval-status filter.
- **Review Ad:** opens the placement editor if you can edit it; otherwise a read-only review page with the same slide renderer, landing preview, notes and approval controls. Review authority does not grant content editing or publishing.
- **Publish behavior:** editing resets approvals when content changes; unchanged saves preserve them. The prior published media/embed/landing remains live through submission and approval. Publish the updated playlist to activate approved content. Venue policy changes require draft resubmission. Already published snapshots continue.
- **SuperAdmin:** authored revisions have a recorded bypass. For existing saved content, Approve This Revision as SuperAdmin is an explicit audited action. Account managers and ordinary admins do not receive this bypass. Role preview remains read-only.
- **Divi:** keep the classic backend builder if preferred. The HWADS editor hides the legacy upgrade promotion and initializes a valid starter layout only for empty/default ads. Custom layouts are preserved. Actual Divi 4.20.4 popup behavior still needs checking on the test site.

## One-time Pi bootstrap

Download `dist/hwads-pi-0.4.0.zip` to the Pi's Downloads folder. Run in a terminal from the Pi desktop as the usual player user:

```sh
cd ~/Downloads
unzip -o hwads-pi-0.4.0.zip
bash hwads-pi/install.sh
systemctl --user enable --now hwads-sync
python3 ~/hpads/scripts/hwads_sync.py --once
python3 ~/hpads/scripts/hwads-control.py enable --engine browser
```

If already unzipped, skip the unzip command and run the extracted installer. Existing device settings and cached media are preserved. The installer briefly stops the player and leaves it disabled until enabled above. It installs a separate stable updater service and launcher. It requires existing Chromium, mpv and Python3; it does not install OS packages or reboot.

On a fresh Pi, enter its HTTPS site URL, device ID and private device key in `~/hpads/config/device.json` before enabling sync. Do not copy this configuration into a release ZIP. The player needs to run from the desktop once so the updater knows the display environment.

## First remote update test

1. WordPress → HWADS → Devices → Player Updates. Wait for the Pi to show installed **0.4.0** and a ready updater check-in.
2. Upload `dist/hwads-pi-0.4.1.zip` using **Upload Player ZIP**. This release has the same features, with a higher reported runtime version to verify the deployment path.
3. Choose 0.4.1, select the Pi, then **Install on Selected Devices**. The player briefly restarts at the next update poll. Polling is separate from the normal media-sync interval; typically about 30 seconds when idle.
4. Refresh status until **completed** and installed **0.4.1** appear. Confirm actual playback, overlays and arrow navigation on the Pi. Check that device configuration and cached media remain present.
5. Optionally use **Roll back to 0.4.0**, wait for completion, and confirm playback again. Rollback is only to the previous updater-ready release.

Do not upload the WordPress plugin ZIP or an installed `hpads` folder as a player release. The upload accepts only the bounded runtime package format. Only SuperAdmin can upload/deploy executable code. Requests are device-authenticated and individually signed; package sizes, paths and file hashes are checked on both ends. Old players cannot remote-install this updater until the bootstrap is installed once.

A failed startup attempts to restore the previous code, and interrupted activation is recovered by the stable updater. Configuration/media are outside release directories. Hardware, desktop startup, systemd and actual power-loss recovery remain to be verified on your Pi. See `~/hpads/logs/updater.log` and `systemctl --user status hwads-updater hwads-sync` if a device needs attention.

The automatic update window and TV-off detection are not active. This release deploys only when SuperAdmin explicitly queues an update. Updating the stable manager protocol or OS dependencies requires a future manual bootstrap. Engagement tracking and placement Insights are not part of this package.
