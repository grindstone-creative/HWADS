> **0.15.3:** Open HWADS → Ads for All, Templates and Placements tabs. Existing ads retain their native editors and playlist assignments. No device update required.

> **HWADS 0.14.4 / Pi 0.5.3:** hardware readings in Device health and the Engagement access fix. See [setup and validation](docs/DEVICE-HEALTH-0.14.4.md).

> Current Playback mix build: **HWADS 0.14.2 / Pi 0.5.2** adds time-weighted playlist shuffle, location addresses and role-aware navigation. See [Playback mix setup](docs/PLAYBACK-MIX-0.14.2.md).

> Current candidate: **HWADS 0.14.1 / Pi 0.5.1**. See the project release notes in `docs/UPDATE-0.14.1.md` for connection fixes and control ownership.

> Editor/scheduling update: **WordPress 0.13.1** adds one name field, the placement Active switch, and explicitly scheduled updates with offline catch-up. See [UPDATE-0.13.1.md](docs/UPDATE-0.13.1.md). Pi 0.4.2 remains compatible.

> Integrated candidate: WordPress **0.13.0** / Pi **0.4.2**. Includes screen analytics and native list thumbnails. See `docs/UPDATE-0.13.0.md` at the project root for installation and counting limits. Earlier version-specific instructions below describe the updater baseline.

# HWADS 0.12.0 testing build

Manage venues, staff, ad templates, placements and playlists from native WordPress editors. Requires WordPress 6.4+, PHP 8.0+ and HTTPS.

Upload the supplied hwads-0.12.0.zip in WordPress Plugins → Add New → Upload Plugin and replace HWADS. Reload open editor tabs. Existing data and published snapshots are preserved.

- HWADS → Users assigns team roles and venue/advertiser memberships. Permissions are defined in the plugin.
- Venues → Edit Venue → Ad approvals sets any/every selected reviewer for outside ads.
- HWADS → Approvals lists personal and venue review work. Reviews apply to saved revisions; publishing remains a separate playlist action.
- SuperAdmin-authored revisions have an audited bypass; another user's edits remove it. Pending edits never replace the published media/embed/landing snapshot.
- HWADS → Devices → Player Updates uploads and deploys updater-ready Pi packages. Each Pi needs the one-time 0.4.0 bootstrap before receiving updates. The supplied 0.4.1 package verifies the first remote deployment.
- Classic Divi backend editing remains available, with the promotional upgrade banner hidden on HWADS screens. Custom layouts remain intact.

Read [the update instructions](docs/UPDATE-0.12.0.md) for setup and device testing. Divi 4.20.4 and actual Pi/systemd recovery still require testing on the user's installation. This build does not implement engagement tracking, automated TV-off scheduling, or an editable role-permission matrix.

## Venue and location workspace

Open a venue to find its locations, or use **Locations → Open workspace**. Each location groups Playlists, Templates, Devices and Settings. **Copy to location** creates an independent draft playlist with independent placements and shared media. Template availability can be set to a venue, one location or My templates. See [the workflow guide](docs/VENUE-WORKSPACE-0.15.0.md).

## Dashboards (0.15.4)

HWADS → Dashboard is the main overview. Open a venue dashboard for all its locations or one selected location. HWADS → Devices shows fleet status above the existing WordPress device list. Workspace tabs reuse native lists, including the existing Ads library for templates. Existing venue editors include a Locations box. See [the dashboard guide](docs/DASHBOARDS-0.15.4.md).

## Consolidated venue navigation

Venue dashboard links and location playlist links open the same venue dashboard/editor, retaining the selected tab and location. Searches, status links and sorting stay on that page. Obsolete custom workspace routes and their duplicate header have been removed, with no compatibility redirects. Authorized read-only viewers use the same dashboard renderer without gaining venue-edit permission.
