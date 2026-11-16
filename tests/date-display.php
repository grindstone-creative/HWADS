<?php
require '/wordpress/wp-load.php';
update_option('timezone_string','America/Chicago');update_option('date_format','F j, Y');update_option('time_format','g:i a');
function check_date_display($actual,$expected){if($actual!==$expected)throw new Exception("Expected $expected; got $actual");echo "PASS: $actual\n";}
check_date_display(HPADS_Core::display_datetime('2026-11-16 13:45:15',true),'November 16, 2026 7:45 am CST');
check_date_display(HPADS_Core::display_datetime('2026-07-16 13:45:15',true),'July 16, 2026 8:45 am CDT');
update_option('date_format','d/m/Y');update_option('time_format','H:i');
check_date_display(HPADS_Core::display_datetime('2026-11-16 13:45:15'),'16/11/2026 07:45');
update_option('time_format','g:i a T');check_date_display(HPADS_Core::display_datetime('2026-11-16 13:45:15',true),'16/11/2026 7:45 am CST');
echo "Date display checks passed.\n";
