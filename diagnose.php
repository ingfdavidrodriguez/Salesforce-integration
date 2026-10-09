<?php
/**
 * Diagnostic script for 500d Salesforce Plugin
 * Usage: wp eval-file diagnose.php
 */

global $wpdb;
$table_name = $wpdb->prefix . 'sfnl_submissions';

echo "\n=== 500d Salesforce Plugin Diagnostics ===\n\n";

// Check if table exists
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME,
    $table_name
));

if (!$table_exists) {
    echo "❌ ERROR: Table {$table_name} does NOT exist!\n";
    echo "   Run: wp eval \"sfnl_create_tables();\"\n\n";
    exit(1);
}

echo "✅ Table {$table_name} exists\n\n";

// Get current columns
echo "Current columns in table:\n";
$columns = $wpdb->get_results("DESCRIBE {$table_name}");

$expected_columns = array(
    'id', 'form_type', 'email', 'first_name', 'last_name', 'phone',
    'topic', 'message', 'company_name', 'employees', 'country', 'cv_url',
    'page_title', 'referral_url', 'submission_date', 'salesforce_id',
    'status', 'error_message'
);

$existing_columns = array();
foreach ($columns as $col) {
    $existing_columns[] = $col->Field;
    echo "  - {$col->Field} ({$col->Type})\n";
}

echo "\nMissing columns check:\n";
$missing = array_diff($expected_columns, $existing_columns);

if (empty($missing)) {
    echo "✅ All required columns are present\n";
} else {
    echo "❌ MISSING COLUMNS: " . implode(', ', $missing) . "\n";
    echo "\n   To fix, run:\n";
    echo "   wp plugin deactivate 500d-salesforce && wp plugin activate 500d-salesforce\n\n";
    echo "   Or manually add missing columns:\n";
    foreach ($missing as $col) {
        switch($col) {
            case 'company_name':
                echo "   wp db query \"ALTER TABLE {$table_name} ADD company_name varchar(255);\"\n";
                break;
            case 'employees':
                echo "   wp db query \"ALTER TABLE {$table_name} ADD employees varchar(10);\"\n";
                break;
            case 'country':
                echo "   wp db query \"ALTER TABLE {$table_name} ADD country varchar(100);\"\n";
                break;
            case 'cv_url':
                echo "   wp db query \"ALTER TABLE {$table_name} ADD cv_url varchar(500);\"\n";
                break;
        }
    }
}

echo "\n=== End Diagnostics ===\n";
