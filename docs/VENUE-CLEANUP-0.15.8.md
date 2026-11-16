# Venue editor cleanup — 0.15.8

The redundant Locations metabox has been removed from the venue editor. The dashboard's Locations tab retains the venue's scoped location list and authorized Add New Location action. No location records or associations are removed.

The embedded native list's column IDs are now namespaced. Its title heading previously duplicated the native editor title input's ID, causing WordPress's title-hint handling to target the heading. HWADS editors now use browser placeholders for title hints and keep the native label visually hidden for assistive technology. Existing names, blank fields and mobile layouts are covered by browser checks.

This combined release also includes the two changes pending after 0.15.7: Devices Dashboard filters moved below status cards, and native Plugin Upload access for the legacy hpads_admin role. That role receives install/upload/update/activate capabilities; other HWADS roles receive no new plugin privileges. WordPress administrator navigation is unchanged.

Validation: dashboard integration (17 checks), combined editor state/permissions and identifier isolation (7 checks), Plugin Upload access (19 checks), browser verification of populated/blank venue names, removed metabox, Locations tab and creation link, and mobile title layout. PHP/JavaScript syntax checks passed. Plugin-only update; not installed on the live site.
