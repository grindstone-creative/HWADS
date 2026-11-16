# Divi landing route rendering fix

November 16, 2026. Local changes only; not deployed.

The ?hpads_ad route renders a frozen placement body outside the normal singular Divi query. The adapter previously assumed Divi modules were registered and rendered the body after get_header/wp_head. The adapter now initializes Divi global settings/main elements only when Divi layout codes are present and section registration is missing. Landing content renders before the header so module assets/styles can be queued before wp_head. The saved/published body, authorization and publication lookup are unchanged.

Changed includes/divi.php and includes/landing-layout.php in updater and integrated. Added tests/divi-route.php to each. Rebuilt updater/dist/hwads-0.14.6.zip and integrated/dist/hwads-0.13.0.zip.

Validation: all 29 updater PHP source files parsed with PHP 8.3 using the local PHP WASM runtime. Standalone regression passed: ordinary content does not initialize modules; missing registration initializes before filtering; layout output renders; original post context returns; subsequent rendering does not initialize modules twice. This regression simulates Divi registration and does not contain the licensed theme. Actual installed Divi rendering/assets and the site's custom header/footer remain to be checked on staging. Do not treat this as verified against the live screenshots.
