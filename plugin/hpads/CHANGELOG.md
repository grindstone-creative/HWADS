## 0.15.10 — 2026-11-16
- Grant native plugin upload/update/activation access to the current HWADS Admin role (hpads_team_admin), in addition to the previously supported legacy role.
- Label the focused HWADS submenu Add New Plugin. Preserve the read-only role-preview restriction and existing permissions for other HWADS roles.

## 0.15.9 — 2026-11-16
- Remove the redundant standalone Playlists menu for venue-access roles; playlists are reached through the venue dashboard.
- Consolidate venue and location navigation onto the venue dashboard/editor with the selected tab and location. Remove the old workspace handler and duplicate standalone venue header; no bookmark redirects or compatibility layer.
- Keep native search, status, sort and copy links on the consolidated flow. Render the same dashboard for authorized read-only venue users without granting venue-edit permissions.

- Move Venue Details below Save in the native right sidebar; keep the venue's tabbed lists in the main column.
- Switch venue tabs, location filters, search, sorting and pagination in place while retaining unsaved venue edits. Browser Back restores the previous section.
- Reinitialize template upload controls after loading the Templates section; keep list GET forms separate from the native venue save form.
- Add the shared public HWADS Box page, site-logo login branding, and optional WordPress/Divi page override; expected playback follows each Box's effective published configuration without exposing credentials.
- Add preview-first, create-only content import/export with media/overlay/relationship remapping, repeat-import handling, failure rollback and a demo seed.
- Add acknowledged pending queue purge for exact snapshot checkpoints, paired with HWADS Box 0.5.4; reported history and playback remain intact.
- Add local/remote/unknown website embed impression reporting with immutable publication/revision attribution, deduplication, bounded ingestion and retention. Configured-playback previews do not count as impressions or Box plays.

## 0.15.8 — 2026-11-16
- Remove the redundant Locations metabox from the venue editor; location navigation and creation remain in the dashboard's Locations tab.
- Replace the overlapping native title hint with a browser placeholder while retaining its accessible label on HWADS editors.
- Move Devices Dashboard Venue/Location filters below the status cards, directly above the native list.
- Add Plugin Upload for the legacy hpads_admin role through WordPress's native uploader, including replacement/update and activation permissions. Other HWADS roles keep their existing plugin permissions.

## 0.15.4 — 2026-11-16
- Implement Main HWADS, Venue and Devices dashboards. Show device status summaries above the native device list.
- Replace simplified workspace tables with the existing native WordPress playlist, template/Ads and device lists. Preserve Date, search, sorting, status filters, pagination and copy actions.
- Add All locations within a venue and venue/location device filters; apply scope to rows and counts without expanding permissions.
- Add a native Locations metabox to venue editors and visible venue dashboard links. Preserve existing associations and published snapshots.
- Show reported player software and authorized remote-access links on the device list. No Pi update is needed for dashboard changes.

## 0.15.5 — 2026-11-16
- Render authenticated landing previews through the front-end WordPress lifecycle instead of an admin request. Existing admin preview links forward after authorization. Preserve private/no-store/noindex responses and deny anonymous access.

## 0.15.2 — 2026-11-16
- Combine template and placement navigation into Ads, with All, Templates and Placements tabs in the native WordPress list. Add a Type column and separate creation actions. Keep existing records, editors, permissions, approvals and playlist publications.

## 0.15.1 — 2026-11-16
- Make location workspaces visible directly in the Venues list and dashboard venue/location tables. Show linked locations and Add location without opening a venue editor or hovering over a row.

## 0.15.0 — 2026-11-16
- Add venue/location workspaces with Playlists, Templates, Devices and Settings tabs while retaining native editors.
- Copy saved playlists across authorized locations with independent placements, shared media, retained settings and destination approval checks. Source publications and engagement remain untouched.
- Add venue, location and personal template scopes, searchable thumbnail picker, and template copying across authorized venues/locations.
- Label device playback as Playlist revision and link authorized viewers to the stored read-only publication snapshot, including publication date, publisher, placement order, duration and schedule.
- Display dates and times using WordPress General settings across device status, health, placement schedules, history, approvals and engagement; timezone labels follow the configured zone and daylight saving.

- Add on-demand pending-playback snapshots from the Pi, with permission-checked diagnostic views and a read-only `--analytics-pending` CLI. No queue records or playback are changed.

- Add optional per-device Remote shell URL and Screen share URL fields, with saved-link buttons. Preserve older forms and existing device-management permissions; no Pi code change is required.

- Wire the native Devices list’s HWADS status column to the latest heartbeat: device enabled/disabled, online/stale, last contact in the WordPress timezone, reported playback state, active version and reported error. Show Never seen before the first heartbeat.

## 0.14.4 — 2026-11-15

- Fix Engagement registration order so the native menu/direct report link opens for authorized users. Preserve scoped report access.
- Add bounded hardware telemetry to the existing heartbeat and device-health panel: RAM, CPU usage/load/cores, CPU temperature and uptime. Missing readings remain unknown. Pi 0.5.3 collects Linux readings without new dependencies or extra polling.

## 0.14.3 — 2026-11-15

- Put Player Updates above the native Devices list beside its standard views, including empty lists; retain an obvious header action on legacy device lists. Keep SuperAdmin-only access and role-preview restrictions.

## 0.14.2 — 2026-11-15

- Finalize and verify weighted Playback mix, per-location addresses, effective-role menus and offline/fallback recovery.
- Package the current combined source as a distinct upgrade, retaining earlier test archives.

## 0.14.1 — 2026-11-15

- Add location Playback mix with whole-number time shares, inherited devices and preserved legacy overrides.
- Add player time-deficit balancing, shuffled bags, fallback/decoding recovery and persisted offline state while preserving original metrics attribution.
- Add multiline location address and effective-role navigation/dashboard filtering, backed by direct section authorization.
- Include the current shared configuration-connection fixes.

## 0.14.1

- Placement owns Active, image duration and schedule; playlists store ordered references. Upgrade removes duplicate draft fields without changing publications.
- Editor keeps all slides visible, with greyscale/slash labels for inactive, scheduled and expired placements; inherited duration display follows the location.
- Trim Pi manifests and response hints; retain old-version health reports as assignment diagnostics. Clarify device API-access checkbox and expose the Divi layout link.
- Pair with Pi 0.5.1 for legacy fallback/order and playing-file protection.

## 0.13.1 — 2026-11-15

- Keep one native placement/template name, with consistent preview, playlist, published manifest and landing heading values.
- Rename Slide settings to Placement settings; move Active into the heading as an accessible on/off switch. Templates use Template settings and have no playback toggle.
- Add explicit scheduled player deployment, WordPress timezone due times, offline catch-up at the next ready check-in, and cancellation before delivery. TV-off status is not required; no Pi reboot is sent.
- Serialize per-device update queue/check-in/cancel changes to prevent lost commands.

## 0.13.0 — 2026-11-15

- Use one native placement/template name field; remove duplicate single-slide naming controls and keep previews, playlists and published landing titles tied to the canonical name.

- Integrate screen playback starts, completions and cumulative active play time with offline queue, duplicate protection, historical device/location attribution and native engagement reports.
- Add image/poster previews to native Templates and Ad Placements lists, with safe media fallbacks.
- Preserve updater protocol 1 and existing runtime allowlist; Pi 0.4.2 carries analytics in the existing sync runtime.

# Changelog

## 0.12.0 — 2026-11-15

- Add venue-specific outside-ad approval policy with any/all selected reviewers, per-user votes, review notes, immutable revision checks and an audit history.
- Add Approvals queue, personal counts, venue/status/search filters, native placement status filter and private review-only preview.
- Restrict automatic approval to stamped SuperAdmin-authored revisions; preserve approvals on unchanged saves and keep published snapshots live until explicit publication.
- Add SuperAdmin-only player ZIP upload/deployment, authenticated signed device polling, one-time Pi updater bootstrap, staged activation and rollback.
- Hide the classic Divi upgrade promotion only on HWADS editors, initialize empty/default Divi layouts and remove the redundant landing-content edit link.

## 0.11.0 — 2026-11-15

- Add separate Landing page main text rich-text field and landing-only show-ad/show-title checkboxes; keep Divi/native layout editing below.
- Add default shared WordPress/Divi layout page selection and content/title/media shortcodes; support individual layout overrides and frozen published text/flags.

- Reuse the Slide/QR/Elements workspace on native template and placement editors, including media selection and independent template defaults.
- Keep the native landing-page editor and Divi option below the workspace.

## 0.10.1 — 2026-11-15

- Stage incoming preview media and overlays together until all assets load; animate the complete slide, including on arrow navigation.
- Allow decimal overlay/QR coordinates and sizes, including values produced by dragging.
- Accept arbitrary video thumbnail timestamps rather than fixed frame-time increments.
- Keep empty numeric fields empty rather than converting them to zero.

## 0.10.0 — 2026-11-15

- Add responsive full-width slide workspace with thumbnails and Slide/QR/Elements tabs.
- Add QR size/position/drag/resize, colors, spacing and presets; text and Media Library logo overlays.
- Share the small renderer across editing, public embeds and offline Pi playback; freeze and verify overlay assets.
- Add video thumbnail timestamp selection, matching Fade crossfades, and separate entrance/exit controls.
- Add read-only, session-scoped SuperAdmin role preview with team selection.
- Make ad post types discoverable to Divi, protect native editing URLs, synchronize builder post-body saves and copy builder activation metadata.
- Require Full HD dimensions for new media selections/uploads; preserve existing media.
- Includes the unreleased playlist-add and player keyboard/keyring changes below.

## 0.9.2 — 2026-11-15

- Add Placement opens template/media choices instead of silently reusing an existing placement.
- Template choices contain templates only; copies remain independent of their source.
- Placement cards keep their identity; Duplicate is explicit and removed saved placements can be restored without copying.
- Default uploads to the playlist venue when available, preserve scoped permissions and approvals.
- Remove redundant calendar buttons and clarify native playlist Save/Publish/Update wording.

## 0.9.1 — 2026-11-14

- Added one shared location software-update window, editable from either location or device with a separate nonce-protected save. Uses WordPress timezone and defaults disabled.
- Assigned venue admins/account managers and global admins can change it; no device-wide management rights are granted to venue staff.
- API exposes the policy for future updater use, with confirmed TV-off requirement. Automatic installation and TV-power detection remain unimplemented; this release does not schedule any restarts.

## 0.9.0 — 2026-11-14

- Added directional/diagonal wipes and circular/split reveals to transition choices and browser playback surfaces.
- Added device-health telemetry display and authenticated, nonce-protected queued cache cleanup with device acknowledgements.
- Separate Pi 0.2.0 browser package supports offline image/video playback, placement QR overlays, transitions, bounded crash recovery, persistent disable and mpv fallback. Pi hardware validation remains required.

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

## 0.14.5

- Match Upload new ad templates to the playlist Add Placement panel, with shared panel/drop-area styles, clear owner/orientation labels and responsive fields. Move the template uploader above the native list controls instead of embedding it in the filter toolbar.

## 0.14.6

- Cap each playlist at 100 placements and add its own editable Maximum active placements setting. Defaults: Ads 15, Menus 5, Community Events 5, House Ads/Fallback 15. Count Active switches regardless of dates; location mixes impose no additional cap.
- Enforce limits in playlist saves, individual placement saves, upload/copy capacity checks and publication, with per-playlist save locking and live counts. New entries can start inactive when slots are full.
- Keep inactive artwork full color and editable in the large canvas, with a red border and Inactive tab. Only thumbnails retain greyscale/slash marks; scheduled slides use an amber frame.
