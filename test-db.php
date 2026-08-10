<?php
require_once 'wp-load.php';
global $wpdb;
$rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wtb_rows ORDER BY id DESC LIMIT 5", ARRAY_A);
print_r($rows);
e