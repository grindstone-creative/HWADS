# HWADS 0.7.2 validation

Isolated local WordPress/PHP WebAssembly fixture; no live staging or actual Divi installation.

- PHP source parsed on PHP 8.0 and 8.3.
- 43 integration assertions passed: native creation/updates, independent identity/media/content, frozen publication, standard Publish semantics, venue active/save behavior, native location/device/advertiser saves, device-key preservation/rotation, ownership changes/copies, inline placement renaming, foreign-owner rejection and restricted account navigation.
- All seven actual native Edit screens and seven Add New screens rendered. The embedded playlist playback endpoint rendered saved items.
- Browser checks passed for actual Visual/Code editing, Media Library selection, serialized forms, embedded player, placement media/editor/details layout, native device sidebar, venue controls, standard Save Draft/Publish before publication and Update afterward, unified ad editors and placement navigation under Playlists.
- Specifically checked the placement Visual editor initializes at a usable toolbar width while visible; inspected its screenshot and the device editor screenshot.
- Fixture attachment-query and POST responses are mocked; server-side native save hooks and selected-attachment authorization are exercised separately. Chrome may report a cross-document ViewTransition abort on the stubbed POST navigation; that exact fixture warning is excluded, but other application errors fail the test.
- ZIP integrity verified when building.

Not tested: live hosting/MySQL, Divi, physical Pi synchronization/playback, real QR scanning. No engagement tracker is implemented. Per-location membership work remains separate.
