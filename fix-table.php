<?php
/**
 * Fix script for 500d Salesforce Plugin table
 * Adds missing columns to wp_sfnl_submissions
 * Usage: wp eval-file fix-table.php
 */

global $wpdb;
$table_name = $wpdb->prefix . 'sfnl_submissions';

echo "\n=== Fixing 500d Salesforce Plugin Table ===\n\n";

// Check if table exists
$table_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME,
    $table_name
));

if (!$table_exists) {
    echo "❌ ERROR: Table {$table_name} does NOT exist!\n";
    echo "   Creating table...\n";
    sfnl_create_tables();
    echo "✅ Table created successfully\n\n";
    exit(0);
}

// Get current columns
$columns = $wpdb->get_results("DESCRIBE {$table_name}");
$existing_columns = array();
foreach ($columns as $col) {
    $existing_columns[] = $col->Field;
}

// Define columns to add
$columns_to_add = array(
    'company_name' => "ALTER TABLE {$table_name} ADD COLUMN company_name varchar(255) AFTER message",
    'employees' => "ALTER TABLE {$table_name} ADD COLUMN employees varchar(10) AFTER company_name",
    'country' => "ALTER TABLE {$table_name} ADD COLUMN country varchar(100) AFTER employees",
    'cv_url' => "ALTER TABLE {$table_name} ADD COLUMN cv_url varchar(500) AFTER country"
);

$changes_made = false;

foreach ($columns_to_add as $column => $sql) {
    if (!in_array($column, $existing_columns)) {
        echo "Adding column: {$column}... ";
        $result = $wpdb->query($sql);
        if ($result === false) {
            echo "❌ FAILED\n";
            echo "Error: {$wpdb->last_error}\n";
        } else {
            echo "✅ SUCCESS\n";
            $changes_made = true;
        }
    } else {
        echo "Column {$column} already exists ✓\n";
    }
}

if ($changes_made) {
    echo "\n✅ Table structure updated successfully!\n";
} else {
    echo "\n✓ No changes needed - all columns exist\n";
}

echo "\n=== Done ===\n";
