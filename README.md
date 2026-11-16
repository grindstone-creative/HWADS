# HWADS

Current application source: **HWADS plugin 0.15.10** and **HWADS Box software 0.5.4**.

This repository consolidates the latest local application, playback, updater, analytics, public-page, migration and venue-navigation work. See [current release notes](docs/UPDATE-0.15.10.md), [validation status](docs/REPOSITORY-SNAPSHOT-2026-11-16.md), [venue navigation checks](docs/CONSOLIDATION-HANDOFF.md), and [remaining work](docs/PENDING-WORK.md). Local test checks do not establish a live deployment or physical HWADS Box validation.

The pending venue consolidation and sidebar/tab changes are included in source. Some local release-note files describe validation still in progress; this repository update does not promote those checks to passes.

Install the plugin package rather than GitHub's whole-repository ZIP. Build packages from this snapshot using `python3 tools/build_plugin.py` and `python3 tools/build_player.py`. These create the HWADS plugin 0.15.10 and HWADS Box 0.5.4 packages under `dist/`. Tracked historical packages may remain for reference; their presence does not make them the current release.

## Earlier implementation and maintenance notes

> **HWADS 0.14.4 / Pi 0.5.3:** hardware readings in Device health and the Engagement access fix. See [setup and validation](docs/DEVICE-HEALTH-0.14.4.md).

> Current Playback mix build: **HWADS 0.14.2 / Pi 0.5.2** adds time-weighted playlist shuffle, location addresses and role-aware navigation. See [Playback mix setup](docs/PLAYBACK-MIX-0.14.2.md).

> Connection fixes: **HWADS 0.14.1 / Pi 0.5.1**. See [UPDATE-0.14.1.md](docs/UPDATE-0.14.1.md) for fixed playback/health flows, placement-owned settings, editor availability marks and trimmed Pi API.

> Editor/scheduling update: **WordPress 0.13.1** adds one name field, the placement Active switch, and explicitly scheduled updates with offline catch-up. See [UPDATE-0.13.1.md](docs/UPDATE-0.13.1.md). Pi 0.4.2 remains compatible.

> Integrated candidate: WordPress **0.13.0** / Pi **0.4.2**. Includes screen analytics and native list thumbnails. See `docs/UPDATE-0.13.0.md` at the project root for installation and counting limits. Earlier version-specific instructions below describe the updater baseline.

# HWADS 0.12.0 — approvals and player updates testing build

Centralized WordPress management for venues, locations, devices, advertisers, ad templates, placements and playlists. Install once on the HWADS control site.

## Install

Download **[dist/hwads-0.12.0.zip](dist/hwads-0.12.0.zip)** and upload it under WordPress → Plugins → Add New → Upload Plugin. Replace the existing plugin when prompted. Use this install ZIP, not the repository's full source ZIP. Reload any open editor tabs after replacing it. Requires WordPress 6.4+, PHP 8.0+ and HTTPS.

A fresh installation uses native post storage immediately; it does not need a migration. Internal `hpads` folder/database/API identifiers are retained for compatibility. No existing data is deleted.

## Shortcodes and website embeds

Use `[hwads_playlist id="123"]` or `[hwads_placement id="456"]` on the central WordPress site. Each editor supplies its correct ID and ready-to-copy shortcode plus iframe code for other websites. Optional `ratio="9/16"` supports portrait content (also 16/9, 4/3 and 1/1). Templates cannot be embedded.

Public embeds serve the latest published playlist snapshot, honoring placement schedules and full-length video playback. Draft edits remain private until the playlist is republished. Playback refreshes publication metadata every five minutes while visible. Embeds display the published overlays and QR settings; engagement metrics are not implemented. Host security headers must permit external framing of the public embed route.

## Dashboard and Pi player

Dashboard defaults to My Ad Placements. The Show on my dashboard selector saves an individual preference, with Devices available only when selected. Existing scoped list permissions apply.

The separate `player/` package contains the Python Pi sync/player and installation instructions. It uses device-key authentication, verified atomic downloads, offline fallback, a local browser window with mpv fallback and persistent disable/enable controls. Install this on the Pi, not as a WordPress plugin. On-device testing is still required.

## Browser player 0.3.1 and device health

Separate Pi package: `dist/hwads-pi-0.3.1.zip` (see `player/README.md`). Local Chromium adds placement QR overlays and lightweight wipes while retaining verified downloads, offline playback, disable controls and mpv fallback. Install and test on the Pi; workstation tests do not establish hardware decoding or desktop startup behavior.

Device editor shows heartbeat health readings and a scoped, nonce-protected Purge unused media action. Cleanup is queued for the next heartbeat, protects current/previous/playing files, and only removes unused media older than 24 hours. Reports complete on the following heartbeat. These readings are not play/scan counts.

## Shared software update window

Locations and their devices show the same Location software update window setting. Authorized venue admins, scoped account managers and global admins can save it from either editor; ordinary venue users cannot. It is disabled by default and uses the WordPress site timezone. The dedicated save changes only this shared location policy. There is no device override.

The API exposes this 30-minute window and requires confirmed TV-off status for future automatic installation. Manual software installation is available in Player Updates. Scheduled automatic installation and TV-power detection are not implemented; saving the schedule causes no restarts and does not change playlist/media polling.

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

Tracking, billing/boost and full self-service remain pending. QR positioning and colors are available. The separate Pi package now implements local sync/browser rendering with transitions and QR overlays; Raspberry Pi hardware and desktop startup validation remain required. Future tracking is intended to exclude staff and preview traffic by default.

## Source and checks

`plugin/hpads/` contains the plugin. `tools/build_plugin.py` builds the install ZIP. `tests/native.php` exercises native saves and authorization in a disposable WordPress fixture; the browser test uses actual rendered WordPress screens and Media Library UI with fixture attachment/POST responses. Real site databases, media, credentials and the fixture are not included. See docs/VALIDATION-0.9.1.md for the previous native-editor validation. Version 0.11.0 adds workspace, overlay, thumbnail and role-preview checks. See docs/VALIDATION-0.11.0.md.

## Visual playlist editor (0.11.0)

A full-width workspace sits above the native WordPress panels. Select a thumbnail to edit Slide, QR, or Elements settings; reorder with drag or Move controls. On mobile the preview and thumbnails sit above a scrolling settings panel. Separate entrance/exit transitions are retained; matching Fade transitions crossfade rather than fade through black. Editing a setting pauses the editor preview; use a preview arrow to resume it.

QR controls include foreground/background colors, width, X/Y position, edge spacing and corner presets. Select a QR/element on the canvas to drag it; use its corner handle or numeric width to resize. Color warnings are guidance, not a scan guarantee—scan a sample on the target TV before use. Add up to eight plain text or Media Library image/logo elements per slide. Images must be local JPEG/PNG/WebP files up to 5 MB; no design library or remote fonts are loaded. References are copied into the published media snapshot, and the Pi caches them with verified downloads. Text fonts use system sans serif, serif or monospace.

Videos default to a thumbnail around one second (frame 30 at 30 fps). Choose thumbnail pauses the video for scrubbing; Use this frame stores the timestamp without changing playback start. Thumbnail extraction is local to the editor and may fall back to a labeled tile if the browser cannot decode the source.

New ad uploads/media selections must be at least 1920×1080 or 1080×1920. Existing unchanged media and copies of existing templates are grandfathered; no library-wide scan or aspect-ratio restriction is performed. Small logos remain allowed.

SuperAdmin has a View as toolbar menu. It is a read-only, session-scoped role simulation; select a venue/advertiser in the banner for scoped roles. Real account roles never change. Exit preview before making edits.

### Divi theme 4

Enable My Ad Templates and My Ad Placements under Divi → Theme Options → Builder → Post Type Integration. The separate Divi Builder plugin can remain disabled when using the Divi theme. On a saved ad, Edit Landing Page with Divi opens its private native preview. Both native editor and builder saves update the canonical landing-page body; playlist publication remains separate. Builder-enabled metadata is copied when duplicating an ad/template. Native editing URLs require permission; published public ad URLs still use the frozen playlist content. Divi 4.20.4 itself is not bundled in the local test fixture, so confirm its editor and styling on the test site.

Install the matching Pi 0.3.1 package for overlay placement/colors and text/logos on TV. Browser mode supports these features; mpv fallback continues media playback without overlays. See docs/VALIDATION-0.11.0.md for checks and remaining device tests.

## 0.11.0 editor and landing-layout additions

The same visual workspace now appears on template and placement editors. Single-ad pages offer media selection and a Preview transition control; the native landing-page body and Divi builder remain available below. Template visual defaults affect future copies, not existing placements.

Incoming preview/player/embed slides wait for overlay images before being revealed. QR/text/logos stay grouped under the slide transition. Decimal coordinates/sizes and thumbnail timestamps no longer fail browser step validation; empty inherited fields stay empty. Install Pi 0.3.1 for its overlay readiness fix.

Under HWADS → Settings choose Default landing-page layout. Add `[hwads_ad_content]` to a Text/Shortcode module on that base page for each ad's Landing page main text; optional `[hwads_ad_title]` and `[hwads_ad_media]` insert title and media. Published/private pages you can edit are selectable, excluding password-protected pages. Changing the shared page changes the shared design immediately; ad text stays frozen until playlist publication. An individual ad with its own Divi layout overrides the shared base. Landing page main text is a separate rich-text field above the layout editor. Show ad placement on page and Show page title apply to the landing page only, and are frozen on publication. The ad/title shortcodes honor those switches; manually inserted titles/media in a custom layout remain under your control.


## 0.12.0: venue approvals

HWADS → Users → Edit User sets a user's team role and venue/advertiser memberships. Permissions attached to each role are defined by the plugin; this release has no editable permission matrix. Venue admins can manage only their own venue teams, and cannot grant HWADS staff or SuperAdmin roles.

Venues → Edit Venue → Ad approvals provides Require approval for outside advertisers, Any one / Every selected reviewer, and an explicit reviewer selection. Review candidates are assigned venue staff or account managers, plus global HWADS administrators. Selecting a venue user grants review authority for that venue, not ad editing, user management or playlist publication. An existing venue with no configured policy defaults to requiring review by its assigned venue administrators; if none exist it requires setup. A new venue can be saved before its staff exist, but submissions wait until reviewers are configured.

HWADS → Approvals has Needs my review and All venue approvals tabs, venue/status/search filters and a paginated queue. A sidebar count and dashboard link show personal pending reviews. Placements have approval status and an approval-status filter. Review Ad opens the native placement editor for an editor, or a read-only review page for staff without edit access. The private slide preview uses the same media/QR/overlay player renderer. Review notes and approve/request-changes controls use nonce-protected, revision-checked forms. Approval is for the saved content revision and destination, with individual votes and a bounded audit history. Any rejection blocks that submission until its author resubmits. Changing the policy invalidates draft approvals and requires resubmission. Losing an assigned reviewer fails closed rather than silently reducing the required count.

Only a revision authored by SuperAdmin gets automatic approval, stamped with actor/revision/time. Merely viewing or publishing another author's ad as SuperAdmin does not approve it. An explicit Approve This Revision as SuperAdmin action records the same audited bypass for an existing saved revision. Outside edits remove that stamp. Unchanged saves retain existing approvals. Venue-owned ads at their own venue do not require outside-ad review. The global legacy setting controls location eligibility and old non-placement workflows, not placement approval.

Edits and approvals never replace the published snapshot. The prior TV media, web embed and landing-page content stay live until an approved revision is explicitly published in its playlist. Shared base-page design changes still apply immediately, as documented above. There is no email notification or engagement tracking in this release.

## 0.12.0: player updates

Owner-only HWADS → Devices → Player Updates accepts a validated, updater-ready ZIP and queues an individually signed install for selected devices. Devices must first receive the one-time Pi 0.4.0 bootstrap below; old players cannot install an updater they do not yet have. After that, upload the 0.4.1 ZIP through WordPress to test the first remote deployment. 0.4.1 has the same features as 0.4.0, with its reported runtime version advanced to verify deployment. Both include the same stable updater protocol.

Install restarts user services/player software at the next update poll; it does not reboot the Pi, change its OS, run the ZIP's installer, or overwrite configuration/media. Packages use a strict runtime allowlist, size limits and per-file SHA-256 checks. Commands bind the device, action, release/version, URL/hash/size and expiry. Code activates through a release symlink, keeps previous code, and rolls back if restarted sync/player health fails or an interrupted activation is recovered. Disabled playback remains disabled. The updater itself is bootstrapped as a separate stable user service; changing its protocol requires another manual bootstrap.

See [docs/UPDATE-0.12.0.md](docs/UPDATE-0.12.0.md) for install/testing steps and [docs/VALIDATION-0.12.0.md](docs/VALIDATION-0.12.0.md) for validation and hardware limitations.
