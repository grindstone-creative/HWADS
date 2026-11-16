# HWADS 0.15.10 — HWADS Admin plugin upload

Corrects the role mismatch in 0.15.8/0.15.9: plugin maintenance was granted only to the legacy hpads_admin role, whose current display label is a scoped legacy account manager. The role actually displayed as HWADS Admin is hpads_team_admin.

Current HWADS Admin accounts now receive WordPress native install/upload/update/activate capabilities. HWADS → Add New Plugin opens the ZIP uploader and supports installation, activation and replacement updates. The focused HWADS navigation remains in effect; unrelated WordPress admin pages are not added. Existing legacy upload access is retained. Account Manager, Venue Admin, Advertiser Admin and Designer receive no new plugin privileges.

Existing accounts receive the role capability update automatically on plugin load. Use a real HWADS Admin login to install plugins. View As remains read-only and hides the installation menu; it is not a way to exercise plugin installation authority.

Validation: 20 role/menu/route checks passed, plus real native ZIP upload, activation and replacement in disposable WordPress using the current HWADS Admin role. PHP syntax passed for all 36 plugin files. No live installation was performed.

Plugin-only correction. HWADS Box 0.5.4 remains the matching current Box runtime; no additional Box update is required for this menu fix.
