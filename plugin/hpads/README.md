# HWADS — native editor testing build

Centralized WordPress management for venues, locations, devices, ad templates, placements and playlists. Install once on the HWADS control site.

## Install and test 0.7.0

Download **[dist/hwads-0.7.0.zip](https://github.com/grindstone-creative/HWADS/raw/main/dist/hwads-0.7.0.zip)** and upload it through WordPress → Plugins → Add New → Upload Plugin. Use the install ZIP, not GitHub's full repository ZIP. Requires WordPress 6.4+, PHP 8.0+ and HTTPS.

This is the focused fresh-install checkpoint. Post storage starts immediately on activation; a new site does not need a data migration. The internal folder/classes/database/API identifiers retain `hpads` for compatibility; displayed branding is HWADS.

1. Activate HWADS as the WordPress administrator.
2. Create a venue and location. Set its default image duration.
3. Open **HWADS → My Ad Templates → Add New**. This opens the real WordPress editor with native title, Visual/Code content editor, media/details card and right-hand Save box. Choose/upload media through the WordPress Media Library, save and reopen.
4. Create a playlist at that location. Its real WordPress edit screen contains the title, Slidestone-style bucket and save sidebar. A playlist deliberately has no web-content editor.
5. Add uploads, existing Media Library files or copies of existing ads/templates to the playlist. Each copy creates an independent placement. Save Draft to keep membership and order.
6. Open a placement. Its large media preview appears below the title, with its details card beneath. **Edit Web Content** reveals the native content editor. Save and reopen to verify media, duration, dates, external URL, QR flag and transitions.
7. **Preview Playlist** plays the saved draft privately. **Save & Publish to Players** creates the immutable version; ordinary Save Draft does not publish players.

Dates use the WordPress site timezone and are converted to UTC for playback manifests. Images use placement duration or the location default; videos play in full. Published placement landing content remains frozen until playlist republishing.

## Scope and limits

This checkpoint fixes actual native post-editor screens and fresh activation. Existing company-wide role/scoping rules remain; the unfinished per-location membership work is not included. Other entity editing screens still use their existing HWADS forms.

Divi is not bundled, enabled or locally tested. The native Ad Template/Placement editor is now available for builder integration. Divi settings/version compatibility and complete builder rendering need their own staging test. The native Visual/Code editor works without Divi.

QR and transition settings are saved. Browser playlist preview supports transitions; the current Pi/MPV script still needs synchronization and transition/QR rendering updates. Proof-of-play, engagement counters, tracked redirects, recurring billing and boosts are not implemented. When tracking is added, staff/preview views are intended to be excluded by default.

For an existing installation, back up its database/uploads before replacing a plugin. This release does not delete or automatically import legacy records. Previously converted 0.6.x records keep their stable IDs and open in the native editors.

## Source and verification

- `plugin/hpads/`: installable plugin source.
- `tools/build_plugin.py`: builds the install ZIP using Python's standard library.
- `tests/native.php`: fresh activation, real native save hooks, independent records, publication and authorization checks.
- `tests/native-browser.mjs`: actual WordPress editor/Media Library and playlist/placement layout checks.
- `docs/VALIDATION-0.7.0.md`: verified scope and limitations.

The browser/integration tests require a disposable WordPress/PHP WebAssembly fixture; site databases, uploads, credentials and the local fixture are not included. Never run fixture-writing tests on a live site.
