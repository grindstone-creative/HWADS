# HWADS 0.9.1 — WordPress editor testing build

Centralized WordPress management for venues, locations, devices, advertisers, ad templates, placements and playlists. Install once on the HWADS control site.

## Install

Download **[dist/hwads-0.9.1.zip](dist/hwads-0.9.1.zip)** and upload it under WordPress → Plugins → Add New → Upload Plugin. Replace the existing plugin when prompted. Use this install ZIP, not the repository's full source ZIP. Reload any open editor tabs after replacing it. Requires WordPress 6.4+, PHP 8.0+ and HTTPS.

A fresh installation uses native post storage immediately; it does not need a migration. Internal `hpads` folder/database/API identifiers are retained for compatibility. No existing data is deleted.

## Shortcodes and website embeds

Use `[hwads_playlist id="123"]` or `[hwads_placement id="456"]` on the central WordPress site. Each editor supplies its correct ID and ready-to-copy shortcode plus iframe code for other websites. Optional `ratio="9/16"` supports portrait content (also 16/9, 4/3 and 1/1). Templates cannot be embedded.

Public embeds serve the latest published playlist snapshot, honoring placement schedules and full-length video playback. Draft edits remain private until the playlist is republished. Playback refreshes publication metadata every five minutes while visible. These embeds do not yet collect engagement metrics or display QR overlays. Host security headers must permit external framing of the public embed route.

## Dashboard and Pi player

Dashboard defaults to My Ad Placements. The Show on my dashboard selector saves an individual preference, with Devices available only when selected. Existing scoped list permissions apply.

The separate `player/` package contains the Python Pi sync/player and installation instructions. It uses device-key authentication, verified atomic downloads, offline fallback, a local browser window with mpv fallback and persistent disable/enable controls. Install this on the Pi, not as a WordPress plugin. On-device testing is still required.

## Browser player 0.2.0 and device health

Separate Pi package: `dist/hwads-pi-0.2.0.zip` (see `player/README.md`). Local Chromium adds placement QR overlays and lightweight wipes while retaining verified downloads, offline playback, disable controls and mpv fallback. Install and test on the Pi; workstation tests do not establish hardware decoding or desktop startup behavior.

Device editor shows heartbeat health readings and a scoped, nonce-protected Purge unused media action. Cleanup is queued for the next heartbeat, protects current/previous/playing files, and only removes unused media older than 24 hours. Reports complete on the following heartbeat. These readings are not play/scan counts.

## Shared software update window

Locations and their devices show the same Location software update window setting. Authorized venue admins, scoped account managers and global admins can save it from either editor; ordinary venue users cannot. It is disabled by default and uses the WordPress site timezone. The dedicated save changes only this shared location policy. There is no device override.

The API exposes this 30-minute window and requires confirmed TV-off status for future automatic installation. Software installation and TV-power detection are not implemented; saving this setting causes no restarts and does not change playlist/media polling.

## What to test

- **Venues:** the actual native editor uses Save Venue and an explicit Venue active checkbox. Saving makes it available immediately; venue records do not require approval. Active/inactive is administrative status, not a command to stop device playback.
- **Locations, devices and advertisers:** native WordPress title/details/save-sidebar screens. Devices have Save Device, status, a protected settings QR and one-time device-key display/rotation. Location/device parents remain fixed after creation.
- **My Ad Templates / placement records:** native WordPress editing with media/details cards. Templates and placements use the same preview-first editor and details card. The landing-page Visual/Code editor is always visible. The old Edit Web Content toggle has been removed. The large media preview remains above the editor.
- **Owner:** authorized HWADS managers can change the owner of an existing template, placement or duplicate. Both owner access and destination eligibility are checked. Copies stay independent; changing a copy does not change its source.
- **Placement workflow:** create new placements from uploads/Media Library or a template directly on the playlist, then edit names and playback options on the cards. Individual edit links use the same ad editor. The separate placement menu is replaced by All Ad Placements in the playlist list; records/history are retained even when removed from a playlist.
- **Playlists:** Location and Purpose are in the right sidebar. The main area has the title, embedded saved-draft player and Slidestone-style placement bucket. No unrelated web-content editor. Save Draft and Publish are the standard native controls before publication; Update replaces them after publication. Publish/Update saves an immutable player version. There are no extra custom sidebar preview or Save & Publish buttons.
- **Media and cards:** drop/upload files, use the WordPress Media Library or copy existing ads/templates. Each selection creates an independent placement. Save the playlist to keep its order. Card schedules, external URL, QR flag, transitions and duration overrides remain editable.
- **Preview:** save changes before Refresh Preview. Template and placement previews are private; published placement landing pages remain public and read frozen published content. Draft edits do not change that published content until republishing the playlist.
- **Users/settings:** WordPress-style details/save-sidebar layouts. Ordinary advertiser/venue users see HWADS navigation only and can still edit their own profile. Global HWADS staff and WordPress administrators retain normal menus. Existing role/scope rules remain in effect.

Dates use the WordPress site timezone and UTC in manifests. Videos play in full. Images inherit location duration unless overridden. Native WordPress UI is the standing project standard; see AGENTS.md.

## Scope and limitations

This is a staging testing build. The new per-location membership model is not included yet; existing company-wide assignments remain. Divi is not bundled or verified here; the native Visual/Code editor is available, but builder-specific integration/rendering still needs a test with the installed Divi version.

Tracking, configurable QR positioning, billing/boost and full self-service remain pending. The separate Pi package now implements local sync/browser rendering with transitions and QR overlays; Raspberry Pi hardware and desktop startup validation remain required. Future tracking is intended to exclude staff and preview traffic by default.

## Source and checks

`plugin/hpads/` contains the plugin. `tools/build_plugin.py` builds the install ZIP. `tests/native.php` exercises native saves and authorization in a disposable WordPress fixture; the browser test uses actual rendered WordPress screens and Media Library UI with fixture attachment/POST responses. Real site databases, media, credentials and the fixture are not included. See docs/VALIDATION-0.9.1.md.
