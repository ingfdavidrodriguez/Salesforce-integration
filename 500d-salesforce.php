<?php
/**
 * Plugin Name: Salesforce
 * Plugin URI: https://500designs.com
 * Description: Complete Salesforce integration with multiple form types: Newsletter, Contact, Lead Generation, and Job Application forms. Connects WordPress to Salesforce for seamless lead management.
 * Version: 1.0.4
 * Author: 500 Designs
 * License: GPL v2 or later
 * Text Domain: 500d-salesforce
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SFNL_VERSION', '1.0.4');
define('SFNL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SFNL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SFNL_PLUGIN_FILE', __FILE__);

// Integrate plugin update checker for GitHub updates
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/org500designs/500d-salesforce',
    __FILE__,
    '500d-salesforce'
);

// Set the branch that contains the stable release
$myUpdateChecker->setBranch('main');

// Authentication for the private repository is provided via a constant
// defined outside of version control (e.g. wp-config.php), not hardcoded here:
// define('SFNL_GITHUB_TOKEN', 'ghp_...');
if (defined('SFNL_GITHUB_TOKEN')) {
    $myUpdateChecker->setAuthentication(SFNL_GITHUB_TOKEN);
}

// Activation hook
register_activation_hook(__FILE__, 'sfnl_activate');
function sfnl_activate() {
    // Create database tables if needed
    sfnl_create_tables();

    // Set default options
    add_option('sfnl_version', SFNL_VERSION);
    add_option('sfnl_settings', array(
        'sf_client_id' => '',
        'sf_client_secret' => '',
        'sf_username' => '',
        'sf_password' => '',
        'sf_security_token' => '',
        'sf_environment' => 'production',
        'form_title' => 'Subscribe to Newsletter',
        'success_message' => 'Thank you for subscribing!',
        'error_message' => 'There was an error. Please try again.'
    ));

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'sfnl_deactivate');
function sfnl_deactivate() {
    // Cleanup temporary data
    delete_transient('sfnl_sf_token');

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Create database tables
function sfnl_create_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'sfnl_submissions';

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        form_type varchar(20) DEFAULT 'newsletter',
        email varchar(255) NOT NULL,
        first_name varchar(100),
        last_name varchar(100),
        phone varchar(50),
        topic varchar(100),
        message text,
        company_name varchar(255),
        employees varchar(10),
        country varchar(100),
        cv_url varchar(500),
        page_title varchar(255),
        referral_url varchar(500),
        submission_date datetime DEFAULT CURRENT_TIMESTAMP,
        salesforce_id varchar(50),
        status varchar(20) DEFAULT 'pending',
        error_message text,
        PRIMARY KEY  (id),
        KEY email (email),
        KEY submission_date (submission_date),
        KEY status (status),
        KEY form_type (form_type)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Load plugin classes
require_once SFNL_PLUGIN_DIR . 'includes/class-salesforce-connector.php';
require_once SFNL_PLUGIN_DIR . 'includes/class-form-handler.php';
require_once SFNL_PLUGIN_DIR . 'includes/class-admin-settings.php';

// Initialize plugin
function sfnl_init() {
    // Load text domain for translations
    load_plugin_textdomain('500d-salesforce', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialize main classes
    new SFNL_Admin_Settings();
    new SFNL_Form_Handler();
}
add_action('plugins_loaded', 'sfnl_init');

// Add newsletter shortcode
function sfnl_newsletter_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => '',
        'class' => 'sfnl-form'
    ), $atts, 'sfnl_form');

    $form_handler = new SFNL_Form_Handler();
    return $form_handler->render_form($atts);
}
add_shortcode('sfnl_form', 'sfnl_newsletter_form_shortcode');

// Add contact form shortcode
function sfnl_contact_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => '',
        'class' => 'sfnl-contact-form'
    ), $atts, 'sfnl_contact_form');

    $form_handler = new SFNL_Form_Handler();
    return $form_handler->render_contact_form($atts);
}
add_shortcode('sfnl_contact_form', 'sfnl_contact_form_shortcode');

// Add lead generation form shortcode
function sfnl_leadgen_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => '',
        'description' => '',
        'class' => 'sfnl-leadgen-form',
        'target' => 'self',
        'align' => 'center'
    ), $atts, 'sfnl_leadgen_form');

    $form_handler = new SFNL_Form_Handler();
    return $form_handler->render_leadgen_form($atts);
}
add_shortcode('sfnl_leadgen_form', 'sfnl_leadgen_form_shortcode');

// Add job application form shortcode
function sfnl_job_application_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => '',
        'class' => 'sfnl-job-application-form'
    ), $atts, 'sfnl_job_application_form');

    $form_handler = new SFNL_Form_Handler();
    return $form_handler->render_job_application_form($atts);
}
add_shortcode('sfnl_job_application_form', 'sfnl_job_application_form_shortcode');
