# Venue sidebar and in-page sections — unreleased

Venue Details now uses the native WordPress right sidebar below Save. The venue-name input remains part of the native save form and sits inside Venue Details. The tabbed dashboard is in the main column. On narrow screens, the sidebar stacks below the dashboard.

JavaScript progressively moves the separate dashboard/list GET forms and native POST save form into a two-column layout without nesting forms. Tabs, location filters, native search, status links, sorting and pagination fetch the authorized venue editor response and replace only the dashboard section. Unsaved venue fields are retained. The address bar supports direct links and browser Back. Settings focuses the Venue Details box. Opening an individual placement, playlist, location or device still opens its native editor.

Template upload controls are initialized when the Templates section loads. Section navigation is blocked during uploads, and failed/unauthorized panel loads show an error while retaining the current venue form. No publication or permission rules change. Without JavaScript, existing server-rendered links remain available.

Validation: 17 dashboard integration checks, 8 editor-state/permission/sidebar checks, and real-browser verification of main/sidebar placement, separate forms, in-page tab/filter/search switching, template uploader initialization, unsaved name/email retention, browser Back, native venue saving afterward and mobile layout. PHP syntax (33 files) and JavaScript syntax (13 runtime files) passed. Not packaged or version-bumped; held for the user's next release request.
