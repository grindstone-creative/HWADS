# Combined venue dashboard and editor — 0.15.7

Saved venues now open with the Venue Dashboard above their native WordPress edit form. The dashboard has an All locations / one location filter and Playlists, Templates, Devices, Locations and Settings navigation. Settings scrolls to Venue Details below the list. Venue Details, approval rules, name, Save Venue controls and the Locations box remain native WordPress controls.

The lists use WP_Posts_List_Table and existing HWADS scope/column/row-action hooks. Device status cards remain available in the Devices tab. Templates reuse the combined Ads library, including its subfilters and copy action. Existing location workspaces and read-only venue-list routes continue working. Venue dashboard links for authorized venue editors now lead to the combined editor.

Dashboard filter/search forms are rendered outside the native POST save form. List rendering isolates and restores the WordPress query, post and screen globals before the editor is rendered. Invalid venue/location combinations fail closed. Unsupported bulk-edit controls are omitted from the embedded lists; individual native edit and copy actions remain available.

Validation: 17 existing dashboard checks, 6 editor-state/authorization checks, browser checks for the combined layout, native post identity, location filtering, template search, scoped devices/software, native venue saving with valid approval settings and mobile layout. PHP syntax check passed for 33 plugin files. No production site or HWADS Box was updated.
