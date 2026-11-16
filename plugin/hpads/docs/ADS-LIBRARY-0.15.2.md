# Ads library — 0.15.2

Open **HWADS → Ads**. All, Templates and Placements tabs use the native WordPress list, with search, sorting, pagination, previews, owner/status columns and a Type column. Add Template and Add Placement open the existing native editors. Inactive and expired ads stay in the library. Approval filtering applies to placements; templates have no placement approvals.

Templates remain reusable source designs; placements remain independent ads assigned to playlists. No records are converted or merged, and no publication, media, approval or reporting IDs change. Existing editor URLs remain valid. Old list links redirect to the appropriate library tab. Venue/location template pickers remain available.

Changed: includes/ad-library.php (new), includes/post-types.php, includes/admin.php, hpads.php; tests/ad-library.php and the dashboard label expectations in tests/embeds.php. Includes the existing 0.15.1 workspace and prior fixes. No HWADS Box software update is required. Not deployed.

Validation: PHP 8.3 parsed all 31 plugin PHP files. Disposable WordPress checks passed: 14 new Ads-library assertions and existing native-save, embed, dashboard, device-health, update-policy, venue-workspace, approval and landing-layout checks (204 assertions total, including 37 sharing/Designer checks). No live-site deployment.

Release coordination: the Ads-library work is included in the consolidated 0.15.3 package alongside Designer/sharing support. Browser checks passed for mixed rows, both tabs, tab-preserving search, empty results, legacy links, native Add Template and a single Ads menu entry.
