# Changelog

## 0.8.3 — 2026-11-14

- Recognize the native WordPress Update submit action and publish a new immutable playlist version in one save. Published playlists retain Update.
- Preserve native publication status on templates and placements instead of resetting every save to draft.

## 0.8.2 — 2026-11-14

- Moved playlist card Remove directly next to Duplicate. Removal behavior is unchanged; Update publishes the revised playlist.

## 0.8.1 — 2026-11-14

- Dashboard defaults to My Ad Placements; each user can save their own list preference. Devices are optional. Preferences use a WordPress nonce and retain scoped access.
- Added a separate Pi sync/player 0.1.0 package: device-key API access, ETag polling, checksum-verified complete activation, offline fallback, schedules, orientation, heartbeat and persistent playback/disable controls. Requires on-device verification.

## 0.8.0 — 2026-11-14

- Added playlist/placement shortcodes and standalone public iframe embeds, with copy-ready codes and IDs in their editors. Templates and unpublished drafts are excluded.
- Embeds read immutable published snapshots, honor schedules, play videos in full and refresh publication metadata every five minutes. No tracking is added in this release.

- Moved playlist Location and Purpose into a native Playlist Settings sidebar box. Preview and placement cards remain in the main editor, with the same save validation and field names.

## 0.7.2 — 2026-11-14

- Unified template/placement preview-first editing; moved placement navigation under Playlists with an All Ad Placements history link. Added editable placement names to playlist cards and clearer inline creation actions.

- All seven entity types now use actual native WordPress editors, including devices, venues, locations and advertisers. Users/settings use the same details/save-sidebar presentation.
- Venue save is immediately usable, with Save Venue and an active/inactive flag; no approval or separate publication step is required. Added native device key generation, display/rotation and status/QR support.
- Placement landing-page editor is always visible; removed the hidden-editor toggle that caused toolbar sizing issues.
- Playlists use standard Save Draft/Publish, then Update. Removed duplicate sidebar preview/custom publication buttons while keeping the embedded player.
- Authorized HWADS managers can change template/placement ownership, including duplicates, subject to owner and playlist access checks.
- Restricted ordinary advertiser/venue admin menus and direct page access to HWADS and own profile; global staff/WordPress admins retain normal navigation.
- Recorded WordPress-native UI as the project standard in AGENTS.md.

## 0.7.1 — 2026-11-14

- Embedded the private saved-draft playlist player above the placement bucket, with Refresh Preview.
- Added Preview Ad Template to the native save sidebar. It opens the last saved template privately, without needing a placement or playlist.

## 0.7.0 — 2026-11-14 — native editor testing checkpoint

- Ad Templates, Ad Placements and Playlists now open in their actual native WordPress post editors, including Add New. Native content and details save back to the canonical records without duplicate posts.
- Fresh activation uses post storage immediately, without an empty-site migration step.
- Ad templates have native Visual/Code content and a media/details card below it. Placements show a large preview first, details underneath and an optional native web-content editor. Playlists have their title and Slidestone-style bucket, without a web-content editor.
- Added native sidebar save, playlist preview/publication and Save as New Template actions. Ordinary Save Draft remains separate from player publication.
- Preserved secure owner/playlist validation, nonces, scoped native-post capabilities, stable record IDs and immutable published landing/media content.
- Displayed branding is HWADS; compatibility identifiers remain hpads.
- Per-location membership, general UI overhaul, Divi integration and physical-player updates are intentionally outside this focused checkpoint.
