<?php
// Simple CLI helper to render cmsPrintSection for given section IDs
// Usage: php tools/dump_cms_section.php 22 7

// Bootstrap DB and function
require_once __DIR__ . '/../database/db_connection.php';
require_once __DIR__ . '/../website_print_functions/cms_print_functions.php';
require_once __DIR__ . '/../website_print_functions/table_print_functions.php';

// Defaults
$websiteId = 1;
$siteID = 1; // only used when creating missing site/section
$TurnierID = 0;
$edit_content_mode = 0; // disable edit UI
$gameEditMode = 0;
$expertenmodus = 0;
$test_turnier_id = 0;

$sections = array_slice($argv, 1);
if (empty($sections)) {
    // default to the two known sections
    $sections = [22, 7];
}

foreach ($sections as $sec) {
    $sec = (int)$sec;
    ob_start();
    cmsPrintSection($websiteId, $siteID, $TurnierID, $sec, $conn, $edit_content_mode, $gameEditMode, $expertenmodus, $test_turnier_id);
    $html = ob_get_clean();
    echo "===== SECTION:$sec START =====\n";
    echo $html, "\n";
    echo "===== SECTION:$sec END =====\n";
}
