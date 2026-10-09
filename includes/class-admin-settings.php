<?php

if (!defined('ABSPATH')) {
    exit;
}

class SFNL_Admin_Settings {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('wp_ajax_sfnl_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_sfnl_export_settings', array($this, 'export_settings'));
        add_action('wp_ajax_sfnl_import_settings', array($this, 'import_settings'));
        add_action('wp_ajax_sfnl_get_submissions', array($this, 'ajax_get_submissions'));
        add_action('wp_ajax_sfnl_diagnose_table', array($this, 'ajax_diagnose_table'));
        add_action('wp_ajax_sfnl_fix_table', array($this, 'ajax_fix_table'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Salesforce', '500d-salesforce'),
            __('Salesforce', '500d-salesforce'),
            'manage_options',
            'sfnl-settings',
            array($this, 'settings_page'),
            'dashicons-cloud',
            30
        );

        add_submenu_page(
            'sfnl-settings',
            __('Settings', '500d-salesforce'),
            __('Settings', '500d-salesforce'),
            'manage_options',
            'sfnl-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'sfnl-settings',
            __('Documentation', '500d-salesforce'),
            __('Documentation', '500d-salesforce'),
            'manage_options',
            'sfnl-documentation',
            array($this, 'documentation_page')
        );

        add_submenu_page(
            'sfnl-settings',
            __('Submissions', '500d-salesforce'),
            __('Submissions', '500d-salesforce'),
            'manage_options',
            'sfnl-submissions',
            array($this, 'submissions_page')
        );

        add_submenu_page(
            'sfnl-settings',
            __('Diagnostics', '500d-salesforce'),
            __('Diagnostics', '500d-salesforce'),
            'manage_options',
            'sfnl-diagnostics',
            array($this, 'diagnostics_page')
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('sfnl_settings_group', 'sfnl_settings', array($this, 'sanitize_settings'));

        // Salesforce API Settings Section
        add_settings_section(
            'sfnl_sf_api_section',
            __('Salesforce API Configuration', '500d-salesforce'),
            array($this, 'sf_api_section_callback'),
            'sfnl-settings'
        );

        add_settings_field(
            'sf_environment',
            __('Environment', '500d-salesforce'),
            array($this, 'environment_field_callback'),
            'sfnl-settings',
            'sfnl_sf_api_section'
        );

        add_settings_field(
            'sf_client_id',
            __('Client ID (Consumer Key)', '500d-salesforce'),
            array($this, 'client_id_field_callback'),
            'sfnl-settings',
            'sfnl_sf_api_section'
        );

        add_settings_field(
            'sf_client_secret',
            __('Client Secret (Consumer Secret)', '500d-salesforce'),
            array($this, 'client_secret_field_callback'),
            'sfnl-settings',
            'sfnl_sf_api_section'
        );

        add_settings_field(
            'sf_username',
            __('Salesforce Username', '500d-salesforce'),
            array($this, 'username_field_callback'),
            'sfnl-settings',
            'sfnl_sf_api_section'
        );

        add_settings_field(
            'sf_password',
            __('Salesforce Password', '500d-salesforce'),
            array($this, 'password_field_callback'),
            'sfnl-settings',
            'sfnl_sf_api_section'
        );

        add_settings_field(
            'sf_security_token',
            __('Security Token', '500d-salesforce'),
            array($this, 'security_token_field_callback'),
            'sfnl-settings',
            'sfnl_sf_api_section'
        );

        // Form Settings Section
        add_settings_section(
            'sfnl_form_section',
            __('Form Configuration', '500d-salesforce'),
            array($this, 'form_section_callback'),
            'sfnl-settings'
        );

        add_settings_field(
            'form_title',
            __('Form Title', '500d-salesforce'),
            array($this, 'form_title_field_callback'),
            'sfnl-settings',
            'sfnl_form_section'
        );

        add_settings_field(
            'success_message',
            __('Success Message', '500d-salesforce'),
            array($this, 'success_message_field_callback'),
            'sfnl-settings',
            'sfnl_form_section'
        );

        add_settings_field(
            'error_message',
            __('Error Message', '500d-salesforce'),
            array($this, 'error_message_field_callback'),
            'sfnl-settings',
            'sfnl_form_section'
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'sfnl') === false) {
            return;
        }

        wp_enqueue_script(
            'sfnl-admin',
            SFNL_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            SFNL_VERSION,
            true
        );

        wp_localize_script('sfnl-admin', 'sfnl_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sfnl_admin_nonce')
        ));
    }

    /**
     * Settings page callback
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Salesforce Settings', '500d-salesforce'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('sfnl_settings_group');
                do_settings_sections('sfnl-settings');
                submit_button();
                ?>
            </form>

            <div id="sfnl-test-connection" style="margin-top: 20px;">
                <button type="button" class="button button-secondary" id="test-sf-connection">
                    <?php _e('Test Salesforce Connection', '500d-salesforce'); ?>
                </button>
                <div id="connection-result" style="margin-top: 10px;"></div>
            </div>

            <div style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h3 style="margin-top: 0;"><?php _e('Import / Export Settings', '500d-salesforce'); ?></h3>

                <div style="margin-bottom: 20px;">
                    <h4><?php _e('Export Settings', '500d-salesforce'); ?></h4>
                    <p><?php _e('Export all plugin settings including Salesforce credentials to a JSON file. This file can be used to import settings on another site.', '500d-salesforce'); ?></p>
                    <form method="post" id="sfnl-export-form" style="display: inline;">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('sfnl_admin_nonce')); ?>" />
                        <button type="submit" class="button button-primary" id="export-settings-btn">
                            <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                            <?php _e('Export Settings', '500d-salesforce'); ?>
                        </button>
                    </form>
                </div>

                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

                <div>
                    <h4><?php _e('Import Settings', '500d-salesforce'); ?></h4>
                    <p><?php _e('Import plugin settings from a previously exported JSON file. This will overwrite all current settings.', '500d-salesforce'); ?></p>
                    <form method="post" id="sfnl-import-form" enctype="multipart/form-data">
                        <input type="file" name="import_file" id="import-file-input" accept=".json" style="margin-bottom: 10px;" />
                        <br>
                        <button type="submit" class="button button-secondary" id="import-settings-btn" disabled>
                            <span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
                            <?php _e('Import Settings', '500d-salesforce'); ?>
                        </button>
                        <div id="import-result" style="margin-top: 10px;"></div>
                    </form>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Submissions page callback
     */
    public function submissions_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Form Submissions', '500d-salesforce'); ?></h1>

            <!-- Filters -->
            <div class="sfnl-filters" style="background: white; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="sfnl-search" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php _e('Search:', '500d-salesforce'); ?>
                        </label>
                        <input type="text" id="sfnl-search" placeholder="<?php esc_attr_e('Email, name, phone, SF ID...', '500d-salesforce'); ?>" style="width: 100%;" />
                    </div>

                    <div>
                        <label for="sfnl-filter-form-type" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php _e('Form Type:', '500d-salesforce'); ?>
                        </label>
                        <select id="sfnl-filter-form-type" style="width: 100%;">
                            <option value=""><?php _e('All Forms', '500d-salesforce'); ?></option>
                            <option value="newsletter"><?php _e('Newsletter', '500d-salesforce'); ?></option>
                            <option value="contact"><?php _e('Contact', '500d-salesforce'); ?></option>
                            <option value="leadgen"><?php _e('Lead Gen', '500d-salesforce'); ?></option>
                            <option value="job_application"><?php _e('Job Application', '500d-salesforce'); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="sfnl-filter-status" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php _e('Status:', '500d-salesforce'); ?>
                        </label>
                        <select id="sfnl-filter-status" style="width: 100%;">
                            <option value=""><?php _e('All Status', '500d-salesforce'); ?></option>
                            <option value="success"><?php _e('Success', '500d-salesforce'); ?></option>
                            <option value="pending"><?php _e('Pending', '500d-salesforce'); ?></option>
                            <option value="failed"><?php _e('Failed', '500d-salesforce'); ?></option>
                        </select>
                    </div>

                    <div>
                        <label for="sfnl-filter-date-from" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php _e('Date From:', '500d-salesforce'); ?>
                        </label>
                        <input type="date" id="sfnl-filter-date-from" style="width: 100%;" />
                    </div>

                    <div>
                        <label for="sfnl-filter-date-to" style="display: block; margin-bottom: 5px; font-weight: 600;">
                            <?php _e('Date To:', '500d-salesforce'); ?>
                        </label>
                        <input type="date" id="sfnl-filter-date-to" style="width: 100%;" />
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" id="sfnl-apply-filters" class="button button-primary">
                        <?php _e('Apply Filters', '500d-salesforce'); ?>
                    </button>
                    <button type="button" id="sfnl-reset-filters" class="button">
                        <?php _e('Reset', '500d-salesforce'); ?>
                    </button>
                    <div id="sfnl-loading" style="display: none; margin-left: 10px; align-self: center;">
                        <span class="spinner is-active" style="float: none; margin: 0;"></span>
                        <span style="margin-left: 5px;"><?php _e('Loading...', '500d-salesforce'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Results count and pagination -->
            <div id="sfnl-results-info" style="margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                <div id="sfnl-results-count"></div>
                <div id="sfnl-pagination-top"></div>
            </div>

            <style>
                .sfnl-submissions-table {
                    margin-top: 20px;
                }
                .sfnl-submissions-table th {
                    font-weight: 600;
                }
                .sfnl-submission-row {
                    cursor: pointer;
                }
                .sfnl-submission-row:hover {
                    background-color: #f5f5f5;
                }
                .sfnl-submission-details {
                    display: none;
                    background-color: #f9f9f9;
                    padding: 15px;
                    border-left: 3px solid #2271b1;
                }
                .sfnl-submission-details.active {
                    display: table-row;
                }
                .sfnl-details-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 15px;
                }
                .sfnl-detail-item {
                    padding: 8px 0;
                }
                .sfnl-detail-label {
                    font-weight: 600;
                    color: #333;
                    display: block;
                    margin-bottom: 4px;
                }
                .sfnl-detail-value {
                    color: #555;
                    word-break: break-word;
                }
                .sfnl-form-badge {
                    padding: 4px 10px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .sfnl-badge-newsletter {
                    background: #f3e5f5;
                    color: #7b1fa2;
                }
                .sfnl-badge-contact {
                    background: #e3f2fd;
                    color: #1976d2;
                }
                .sfnl-badge-leadgen {
                    background: #fff3e0;
                    color: #f57c00;
                }
                .sfnl-badge-job_application {
                    background: #e8f5e9;
                    color: #388e3c;
                }
                .sfnl-status-badge {
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                }
                .sfnl-status-success {
                    background: #d4edda;
                    color: #155724;
                }
                .sfnl-status-pending {
                    background: #fff3cd;
                    color: #856404;
                }
                .sfnl-status-failed {
                    background: #f8d7da;
                    color: #721c24;
                }
                .sfnl-toggle-icon {
                    margin-right: 5px;
                    transition: transform 0.2s;
                }
                .sfnl-toggle-icon.active {
                    transform: rotate(90deg);
                }
            </style>

            <script>
                jQuery(document).ready(function($) {
                    var currentPage = 1;

                    // Load submissions via AJAX
                    function loadSubmissions(page) {
                        page = page || 1;
                        currentPage = page;

                        $('#sfnl-loading').show();

                        $.ajax({
                            url: sfnl_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'sfnl_get_submissions',
                                nonce: sfnl_ajax.nonce,
                                page: page,
                                search: $('#sfnl-search').val(),
                                form_type: $('#sfnl-filter-form-type').val(),
                                status: $('#sfnl-filter-status').val(),
                                date_from: $('#sfnl-filter-date-from').val(),
                                date_to: $('#sfnl-filter-date-to').val()
                            },
                            success: function(response) {
                                $('#sfnl-loading').hide();
                                if (response.success) {
                                    renderSubmissions(response.data);
                                    renderPagination(response.data);
                                    updateResultsCount(response.data);
                                } else {
                                    alert('Error loading submissions');
                                }
                            },
                            error: function() {
                                $('#sfnl-loading').hide();
                                alert('Error loading submissions');
                            }
                        });
                    }

                    // Render submissions table
                    function renderSubmissions(data) {
                        var tbody = $('#sfnl-submissions-table tbody');
                        tbody.empty();

                        if (data.submissions.length === 0) {
                            tbody.append('<tr><td colspan="7"><?php _e('No submissions found.', '500d-salesforce'); ?></td></tr>');
                            return;
                        }

                        data.submissions.forEach(function(submission) {
                            var formTypeLabel = submission.form_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                            var nameOrCompany = '';
                            if (submission.first_name || submission.last_name) {
                                nameOrCompany = (submission.first_name + ' ' + submission.last_name).trim();
                            } else if (submission.company_name) {
                                nameOrCompany = submission.company_name;
                            } else {
                                nameOrCompany = '—';
                            }

                            var date = new Date(submission.submission_date);
                            var formattedDate = date.toLocaleDateString('en-US', {
                                month: 'short', day: 'numeric', year: 'numeric',
                                hour: 'numeric', minute: '2-digit', hour12: true
                            });

                            var sfId = submission.salesforce_id ? '<code>' + submission.salesforce_id.substring(0, 8) + '...</code>' : '—';

                            var row = '<tr class="sfnl-submission-row" data-id="' + submission.id + '">' +
                                '<td><span class="dashicons dashicons-arrow-right-alt2 sfnl-toggle-icon"></span></td>' +
                                '<td><span class="sfnl-form-badge sfnl-badge-' + submission.form_type + '">' + formTypeLabel + '</span></td>' +
                                '<td>' + escapeHtml(submission.email) + '</td>' +
                                '<td>' + escapeHtml(nameOrCompany) + '</td>' +
                                '<td>' + formattedDate + '</td>' +
                                '<td><span class="sfnl-status-badge sfnl-status-' + submission.status + '">' + submission.status.charAt(0).toUpperCase() + submission.status.slice(1) + '</span></td>' +
                                '<td>' + sfId + '</td>' +
                                '</tr>';

                            var details = '<tr class="sfnl-submission-details"><td colspan="7">' + renderDetails(submission) + '</td></tr>';

                            tbody.append(row + details);
                        });

                        // Bind click event
                        $('.sfnl-submission-row').on('click', function() {
                            var detailsRow = $(this).next('.sfnl-submission-details');
                            var icon = $(this).find('.sfnl-toggle-icon');
                            var isActive = detailsRow.hasClass('active');

                            // Close all other rows
                            $('.sfnl-submission-details').removeClass('active');
                            $('.sfnl-toggle-icon').removeClass('active');

                            // Toggle current row
                            if (!isActive) {
                                detailsRow.addClass('active');
                                icon.addClass('active');
                            }
                        });
                    }

                    // Render details panel
                    function renderDetails(s) {
                        var empRanges = {'1': '1-10', '11': '11-50', '51': '51-200', '201': '201-500', '501': '501-1000', '1001': '1001-5000', '5001': '5001+'};

                        var html = '<div class="sfnl-details-grid">';

                        if (s.first_name) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">First Name:</span><span class="sfnl-detail-value">' + escapeHtml(s.first_name) + '</span></div>';
                        if (s.last_name) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Last Name:</span><span class="sfnl-detail-value">' + escapeHtml(s.last_name) + '</span></div>';
                        if (s.phone) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Phone:</span><span class="sfnl-detail-value">' + escapeHtml(s.phone) + '</span></div>';
                        if (s.company_name) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Company Name:</span><span class="sfnl-detail-value">' + escapeHtml(s.company_name) + '</span></div>';
                        if (s.country) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Country:</span><span class="sfnl-detail-value">' + escapeHtml(s.country) + '</span></div>';
                        if (s.employees) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Employees:</span><span class="sfnl-detail-value">' + (empRanges[s.employees] || s.employees) + '</span></div>';
                        if (s.topic) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Topic:</span><span class="sfnl-detail-value">' + escapeHtml(s.topic) + '</span></div>';
                        if (s.salesforce_id) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Salesforce ID:</span><span class="sfnl-detail-value"><code>' + escapeHtml(s.salesforce_id) + '</code></span></div>';
                        if (s.page_title) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Page Title:</span><span class="sfnl-detail-value">' + escapeHtml(s.page_title) + '</span></div>';
                        if (s.referral_url) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">Referral URL:</span><span class="sfnl-detail-value"><a href="' + escapeHtml(s.referral_url) + '" target="_blank">' + escapeHtml(s.referral_url) + '</a></span></div>';
                        if (s.cv_url) html += '<div class="sfnl-detail-item"><span class="sfnl-detail-label">CV/Resume:</span><span class="sfnl-detail-value"><a href="' + escapeHtml(s.cv_url) + '" target="_blank">Download CV</a></span></div>';

                        html += '</div>';

                        if (s.message) html += '<div class="sfnl-detail-item" style="margin-top: 15px;"><span class="sfnl-detail-label">Message:</span><div class="sfnl-detail-value" style="padding: 10px; background: white; border-radius: 3px; margin-top: 5px;">' + escapeHtml(s.message).replace(/\n/g, '<br>') + '</div></div>';
                        if (s.error_message) html += '<div class="sfnl-detail-item" style="margin-top: 15px;"><span class="sfnl-detail-label" style="color: #d32f2f;">Error Message:</span><div class="sfnl-detail-value" style="padding: 10px; background: #fff3f3; border-left: 3px solid #d32f2f; border-radius: 3px; margin-top: 5px; color: #d32f2f;">' + escapeHtml(s.error_message) + '</div></div>';

                        html += '<div class="sfnl-detail-item" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;"><span class="sfnl-detail-label">Submission Date:</span><span class="sfnl-detail-value">' + s.submission_date + '</span></div>';

                        return html;
                    }

                    // Render pagination
                    function renderPagination(data) {
                        var html = '';

                        if (data.total_pages > 1) {
                            html += '<div class="tablenav-pages">';
                            html += '<span class="displaying-num">' + data.total_records + ' items</span>';
                            html += '<span class="pagination-links">';

                            // First page
                            if (data.current_page > 1) {
                                html += '<a class="first-page button" data-page="1"><span aria-hidden="true">«</span></a>';
                                html += '<a class="prev-page button" data-page="' + (data.current_page - 1) + '"><span aria-hidden="true">‹</span></a>';
                            } else {
                                html += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                                html += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                            }

                            html += '<span class="paging-input">';
                            html += '<label class="screen-reader-text">Current Page</label>';
                            html += '<input class="current-page" type="text" name="paged" value="' + data.current_page + '" size="1" aria-describedby="table-paging"> of <span class="total-pages">' + data.total_pages + '</span>';
                            html += '</span>';

                            // Last page
                            if (data.current_page < data.total_pages) {
                                html += '<a class="next-page button" data-page="' + (data.current_page + 1) + '"><span aria-hidden="true">›</span></a>';
                                html += '<a class="last-page button" data-page="' + data.total_pages + '"><span aria-hidden="true">»</span></a>';
                            } else {
                                html += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                                html += '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                            }

                            html += '</span>';
                            html += '</div>';
                        }

                        $('#sfnl-pagination-top').html(html);
                        $('#sfnl-pagination-bottom').html(html);

                        // Bind pagination click events
                        $('.first-page, .prev-page, .next-page, .last-page').on('click', function(e) {
                            e.preventDefault();
                            var page = $(this).data('page');
                            loadSubmissions(page);
                        });

                        // Handle direct page input
                        $('.current-page').on('keypress', function(e) {
                            if (e.which === 13) {
                                var page = parseInt($(this).val());
                                if (page >= 1 && page <= data.total_pages) {
                                    loadSubmissions(page);
                                }
                            }
                        });
                    }

                    // Update results count
                    function updateResultsCount(data) {
                        var start = ((data.current_page - 1) * data.per_page) + 1;
                        var end = Math.min(data.current_page * data.per_page, data.total_records);
                        var text = 'Showing ' + start + ' to ' + end + ' of ' + data.total_records + ' submissions';
                        $('#sfnl-results-count').html('<strong>' + text + '</strong>');
                    }

                    // Escape HTML
                    function escapeHtml(text) {
                        if (!text) return '';
                        var map = {
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#039;'
                        };
                        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
                    }

                    // Apply filters button
                    $('#sfnl-apply-filters').on('click', function() {
                        loadSubmissions(1);
                    });

                    // Reset filters button
                    $('#sfnl-reset-filters').on('click', function() {
                        $('#sfnl-search').val('');
                        $('#sfnl-filter-form-type').val('');
                        $('#sfnl-filter-status').val('');
                        $('#sfnl-filter-date-from').val('');
                        $('#sfnl-filter-date-to').val('');
                        loadSubmissions(1);
                    });

                    // Allow Enter key on search
                    $('#sfnl-search').on('keypress', function(e) {
                        if (e.which === 13) {
                            loadSubmissions(1);
                        }
                    });

                    // Load initial submissions
                    loadSubmissions(1);
                });
            </script>

            <table class="wp-list-table widefat fixed striped sfnl-submissions-table" id="sfnl-submissions-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th><?php _e('Form Type', '500d-salesforce'); ?></th>
                        <th><?php _e('Email', '500d-salesforce'); ?></th>
                        <th><?php _e('Name/Company', '500d-salesforce'); ?></th>
                        <th><?php _e('Date', '500d-salesforce'); ?></th>
                        <th><?php _e('Status', '500d-salesforce'); ?></th>
                        <th><?php _e('SF ID', '500d-salesforce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
                            <p><?php _e('Loading submissions...', '500d-salesforce'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Bottom pagination -->
            <div id="sfnl-pagination-bottom" style="margin-top: 10px;"></div>
        </div>
        <?php
    }

    /**
     * Documentation page callback
     */
    public function documentation_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Salesforce Plugin Documentation', '500d-salesforce'); ?></h1>

            <style>
                .sfnl-doc-section {
                    background: white;
                    padding: 20px;
                    margin: 20px 0;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                }
                .sfnl-doc-section h2 {
                    margin-top: 0;
                    border-bottom: 2px solid #2271b1;
                    padding-bottom: 10px;
                }
                .sfnl-doc-section h3 {
                    color: #2271b1;
                    margin-top: 20px;
                }
                .sfnl-code-block {
                    background: #f6f7f7;
                    border-left: 4px solid #2271b1;
                    padding: 15px;
                    margin: 10px 0;
                    font-family: monospace;
                    overflow-x: auto;
                }
                .sfnl-field-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                }
                .sfnl-field-table th,
                .sfnl-field-table td {
                    padding: 10px;
                    text-align: left;
                    border: 1px solid #ddd;
                }
                .sfnl-field-table th {
                    background: #f0f0f1;
                    font-weight: 600;
                }
                .sfnl-field-table code {
                    background: #f6f7f7;
                    padding: 2px 6px;
                    border-radius: 3px;
                    color: #d63638;
                }
                .sfnl-note {
                    background: #fff3cd;
                    border-left: 4px solid #ffc107;
                    padding: 12px;
                    margin: 15px 0;
                }
                .sfnl-success {
                    background: #d1e7dd;
                    border-left: 4px solid #198754;
                    padding: 12px;
                    margin: 15px 0;
                }
            </style>

            <!-- Overview -->
            <div class="sfnl-doc-section">
                <h2><?php _e('Plugin Overview', '500d-salesforce'); ?></h2>
                <p><?php _e('The Salesforce plugin provides seamless integration between WordPress and Salesforce CRM. It includes four different form types, each designed for specific use cases:', '500d-salesforce'); ?></p>
                <ul>
                    <li><strong><?php _e('Newsletter Form', '500d-salesforce'); ?></strong> - <?php _e('Simple email subscription', '500d-salesforce'); ?></li>
                    <li><strong><?php _e('Contact Form', '500d-salesforce'); ?></strong> - <?php _e('Full contact inquiry with message', '500d-salesforce'); ?></li>
                    <li><strong><?php _e('Lead Generation Form', '500d-salesforce'); ?></strong> - <?php _e('Business lead capture with modal display', '500d-salesforce'); ?></li>
                    <li><strong><?php _e('Job Application Form', '500d-salesforce'); ?></strong> - <?php _e('Job applications with CV upload', '500d-salesforce'); ?></li>
                </ul>
                <p><?php _e('All submissions are stored in WordPress and synced to Salesforce as Leads. You can view all submissions in the Submissions page.', '500d-salesforce'); ?></p>
            </div>

            <!-- Newsletter Form -->
            <div class="sfnl-doc-section">
                <h2><?php _e('1. Newsletter Form', '500d-salesforce'); ?></h2>

                <h3><?php _e('Basic Usage', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">[sfnl_form]</div>

                <h3><?php _e('With Attributes', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">[sfnl_form title="Subscribe to Our Newsletter" class="my-custom-class"]</div>

                <h3><?php _e('Available Attributes', '500d-salesforce'); ?></h3>
                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Attribute', '500d-salesforce'); ?></th>
                            <th><?php _e('Type', '500d-salesforce'); ?></th>
                            <th><?php _e('Default', '500d-salesforce'); ?></th>
                            <th><?php _e('Description', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>title</code></td>
                            <td>string</td>
                            <td><?php _e('From settings', '500d-salesforce'); ?></td>
                            <td><?php _e('Form heading/title', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>class</code></td>
                            <td>string</td>
                            <td>sfnl-form</td>
                            <td><?php _e('Custom CSS class for the form container', '500d-salesforce'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php _e('CSS Classes', '500d-salesforce'); ?></h3>
                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Element', '500d-salesforce'); ?></th>
                            <th><?php _e('CSS Class', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><?php _e('Form Container', '500d-salesforce'); ?></td><td><code>sfnl-form</code></td></tr>
                        <tr><td><?php _e('Form Title', '500d-salesforce'); ?></td><td><code>sfnl-form-title</code></td></tr>
                        <tr><td><?php _e('Form Element', '500d-salesforce'); ?></td><td><code>#sfnl-newsletter-form</code></td></tr>
                        <tr><td><?php _e('Form Group', '500d-salesforce'); ?></td><td><code>sfnl-form-group</code></td></tr>
                        <tr><td><?php _e('Label', '500d-salesforce'); ?></td><td><code>sfnl-label</code></td></tr>
                        <tr><td><?php _e('Email Input', '500d-salesforce'); ?></td><td><code>sfnl-input</code>, <code>sfnl-email-input</code></td></tr>
                        <tr><td><?php _e('Submit Button', '500d-salesforce'); ?></td><td><code>sfnl-submit-btn</code></td></tr>
                        <tr><td><?php _e('Button Text', '500d-salesforce'); ?></td><td><code>sfnl-btn-text</code></td></tr>
                        <tr><td><?php _e('Loading Text', '500d-salesforce'); ?></td><td><code>sfnl-btn-loading</code></td></tr>
                        <tr><td><?php _e('Messages Container', '500d-salesforce'); ?></td><td><code>sfnl-messages</code></td></tr>
                    </tbody>
                </table>

                <h3><?php _e('Form Fields', '500d-salesforce'); ?></h3>
                <ul>
                    <li><strong><?php _e('Email', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>) - <?php _e('Validated email address', '500d-salesforce'); ?></li>
                </ul>

                <div class="sfnl-note">
                    <strong><?php _e('Note:', '500d-salesforce'); ?></strong> <?php _e('Duplicate email prevention is enabled. The same email cannot subscribe twice.', '500d-salesforce'); ?>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="sfnl-doc-section">
                <h2><?php _e('2. Contact Form', '500d-salesforce'); ?></h2>

                <h3><?php _e('Basic Usage', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">[sfnl_contact_form]</div>

                <h3><?php _e('With Attributes', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">[sfnl_contact_form title="Get In Touch" class="my-contact-form"]</div>

                <h3><?php _e('Available Attributes', '500d-salesforce'); ?></h3>
                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Attribute', '500d-salesforce'); ?></th>
                            <th><?php _e('Type', '500d-salesforce'); ?></th>
                            <th><?php _e('Default', '500d-salesforce'); ?></th>
                            <th><?php _e('Description', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>title</code></td>
                            <td>string</td>
                            <td><?php _e('Empty', '500d-salesforce'); ?></td>
                            <td><?php _e('Form heading/title', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>class</code></td>
                            <td>string</td>
                            <td>sfnl-contact-form</td>
                            <td><?php _e('Custom CSS class for the form container', '500d-salesforce'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php _e('CSS Classes', '500d-salesforce'); ?></h3>
                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Element', '500d-salesforce'); ?></th>
                            <th><?php _e('CSS Class', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><?php _e('Form Container', '500d-salesforce'); ?></td><td><code>sfnl-contact-form</code></td></tr>
                        <tr><td><?php _e('Form Element', '500d-salesforce'); ?></td><td><code>#sfnl-contact-form</code></td></tr>
                        <tr><td><?php _e('Form Row', '500d-salesforce'); ?></td><td><code>sfnl-form-row</code></td></tr>
                        <tr><td><?php _e('Form Group', '500d-salesforce'); ?></td><td><code>sfnl-form-group</code></td></tr>
                        <tr><td><?php _e('Half Width Group', '500d-salesforce'); ?></td><td><code>sfnl-form-group sfnl-half</code></td></tr>
                        <tr><td><?php _e('All Inputs', '500d-salesforce'); ?></td><td><code>sfnl-input</code></td></tr>
                        <tr><td><?php _e('Select/Dropdown', '500d-salesforce'); ?></td><td><code>sfnl-input sfnl-select</code></td></tr>
                        <tr><td><?php _e('Textarea', '500d-salesforce'); ?></td><td><code>sfnl-input sfnl-textarea</code></td></tr>
                        <tr><td><?php _e('Checkbox Group', '500d-salesforce'); ?></td><td><code>sfnl-checkbox-group</code></td></tr>
                        <tr><td><?php _e('Checkbox', '500d-salesforce'); ?></td><td><code>sfnl-checkbox</code></td></tr>
                        <tr><td><?php _e('Submit Button', '500d-salesforce'); ?></td><td><code>sfnl-submit-btn</code>, <code>#sfnl-contact-submit-btn</code></td></tr>
                        <tr><td><?php _e('Messages Container', '500d-salesforce'); ?></td><td><code>#sfnl-contact-form-messages</code></td></tr>
                    </tbody>
                </table>

                <h3><?php _e('Form Fields', '500d-salesforce'); ?></h3>
                <ul>
                    <li><strong><?php _e('First Name', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Last Name', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Email', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Phone Number', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Country', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>) - <?php _e('Dropdown with 80+ countries', '500d-salesforce'); ?></li>
                    <li><strong><?php _e('Topic', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>) - <?php _e('Consulting, Implementation, Managed Services, Residency Services', '500d-salesforce'); ?></li>
                    <li><strong><?php _e('Message', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Terms of Use', '500d-salesforce'); ?></strong> (<?php _e('required checkbox', '500d-salesforce'); ?>)</li>
                </ul>

                <div class="sfnl-success">
                    <strong><?php _e('Success Behavior:', '500d-salesforce'); ?></strong> <?php _e('Redirects to the site homepage with', '500d-salesforce'); ?> <code>?form=success#form</code>
                </div>
            </div>

            <!-- Lead Generation Form -->
            <div class="sfnl-doc-section">
                <h2><?php _e('3. Lead Generation Form', '500d-salesforce'); ?></h2>

                <h3><?php _e('Basic Usage', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">[sfnl_leadgen_form]</div>

                <h3><?php _e('With Attributes', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">[sfnl_leadgen_form title="Download White Paper" target="blank" class="my-leadgen-form"]</div>

                <h3><?php _e('Available Attributes', '500d-salesforce'); ?></h3>
                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Attribute', '500d-salesforce'); ?></th>
                            <th><?php _e('Type', '500d-salesforce'); ?></th>
                            <th><?php _e('Default', '500d-salesforce'); ?></th>
                            <th><?php _e('Description', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>title</code></td>
                            <td>string</td>
                            <td><?php _e('Empty', '500d-salesforce'); ?></td>
                            <td><?php _e('Form heading/title', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>class</code></td>
                            <td>string</td>
                            <td>sfnl-leadgen-form</td>
                            <td><?php _e('Custom CSS class for the form container', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>target</code></td>
                            <td>string</td>
                            <td>self</td>
                            <td><?php _e('"blank" for new tab, "self" for same tab', '500d-salesforce'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php _e('CSS Classes', '500d-salesforce'); ?></h3>
                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Element', '500d-salesforce'); ?></th>
                            <th><?php _e('CSS Class', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><?php _e('Modal Overlay', '500d-salesforce'); ?></td><td><code>sfnl-modal</code>, <code>#sfnl-leadgen-modal</code></td></tr>
                        <tr><td><?php _e('Modal Content', '500d-salesforce'); ?></td><td><code>sfnl-modal-content</code></td></tr>
                        <tr><td><?php _e('Form Container', '500d-salesforce'); ?></td><td><code>sfnl-leadgen-form</code></td></tr>
                        <tr><td><?php _e('Form Element', '500d-salesforce'); ?></td><td><code>#sfnl-leadgen-form</code></td></tr>
                        <tr><td><?php _e('Form Row', '500d-salesforce'); ?></td><td><code>sfnl-form-row</code></td></tr>
                        <tr><td><?php _e('Half Width Group', '500d-salesforce'); ?></td><td><code>sfnl-form-group sfnl-half</code></td></tr>
                        <tr><td><?php _e('All Inputs', '500d-salesforce'); ?></td><td><code>sfnl-input</code></td></tr>
                        <tr><td><?php _e('Select/Dropdown', '500d-salesforce'); ?></td><td><code>sfnl-input sfnl-select</code></td></tr>
                        <tr><td><?php _e('Submit Button', '500d-salesforce'); ?></td><td><code>sfnl-submit-btn</code>, <code>#sfnl-leadgen-submit-btn</code></td></tr>
                        <tr><td><?php _e('Messages Container', '500d-salesforce'); ?></td><td><code>#sfnl-leadgen-form-messages</code></td></tr>
                    </tbody>
                </table>

                <h3><?php _e('Form Fields', '500d-salesforce'); ?></h3>
                <ul>
                    <li><strong><?php _e('Company Email', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Company Name', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('First Name', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Last Name', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Phone Number', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Number of Employees', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>) - <?php _e('Dropdown: 1-10, 11-50, 51-200, 201-500, 501-1000, 1001-5000, 5001+', '500d-salesforce'); ?></li>
                    <li><strong><?php _e('Country', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>) - <?php _e('Dropdown with 30+ countries', '500d-salesforce'); ?></li>
                </ul>

                <div class="sfnl-note">
                    <strong><?php _e('Important:', '500d-salesforce'); ?></strong> <?php _e('This form redirects to a white paper URL stored in the page/post custom field named "upload_white_paper_file". Make sure to set this custom field on the page where you use this shortcode.', '500d-salesforce'); ?>
                </div>

                <div class="sfnl-success">
                    <strong><?php _e('Display:', '500d-salesforce'); ?></strong> <?php _e('Opens in a modal overlay when triggered.', '500d-salesforce'); ?>
                </div>
            </div>

            <!-- Job Application Form -->
            <div class="sfnl-doc-section">
                <h2><?php _e('4. Job Application Form', '500d-salesforce'); ?></h2>

                <h3><?php _e('Basic Usage', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">[sfnl_job_application_form]</div>

                <h3><?php _e('With Attributes', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">[sfnl_job_application_form title="Apply Now" class="my-job-form"]</div>

                <h3><?php _e('Available Attributes', '500d-salesforce'); ?></h3>
                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Attribute', '500d-salesforce'); ?></th>
                            <th><?php _e('Type', '500d-salesforce'); ?></th>
                            <th><?php _e('Default', '500d-salesforce'); ?></th>
                            <th><?php _e('Description', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>title</code></td>
                            <td>string</td>
                            <td><?php _e('Empty', '500d-salesforce'); ?></td>
                            <td><?php _e('Form heading/title', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>class</code></td>
                            <td>string</td>
                            <td>sfnl-job-application-form</td>
                            <td><?php _e('Custom CSS class for the form container', '500d-salesforce'); ?></td>
                        </tr>
                    </tbody>
                </table>

                <h3><?php _e('CSS Classes', '500d-salesforce'); ?></h3>
                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Element', '500d-salesforce'); ?></th>
                            <th><?php _e('CSS Class', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><?php _e('Form Container', '500d-salesforce'); ?></td><td><code>sfnl-job-application-form</code></td></tr>
                        <tr><td><?php _e('Form Element', '500d-salesforce'); ?></td><td><code>#sfnl-job-application-form</code></td></tr>
                        <tr><td><?php _e('Form Group', '500d-salesforce'); ?></td><td><code>sfnl-form-group</code></td></tr>
                        <tr><td><?php _e('All Inputs', '500d-salesforce'); ?></td><td><code>sfnl-input</code></td></tr>
                        <tr><td><?php _e('File Input', '500d-salesforce'); ?></td><td><code>sfnl-input sfnl-file-input</code></td></tr>
                        <tr><td><?php _e('Textarea', '500d-salesforce'); ?></td><td><code>sfnl-input sfnl-textarea</code></td></tr>
                        <tr><td><?php _e('Help Text', '500d-salesforce'); ?></td><td><code>sfnl-help-text</code></td></tr>
                        <tr><td><?php _e('Checkbox Group', '500d-salesforce'); ?></td><td><code>sfnl-checkbox-group</code></td></tr>
                        <tr><td><?php _e('Submit Button', '500d-salesforce'); ?></td><td><code>sfnl-submit-btn</code>, <code>#sfnl-job-submit-btn</code></td></tr>
                        <tr><td><?php _e('Messages Container', '500d-salesforce'); ?></td><td><code>#sfnl-job-form-messages</code></td></tr>
                    </tbody>
                </table>

                <h3><?php _e('Form Fields', '500d-salesforce'); ?></h3>
                <ul>
                    <li><strong><?php _e('Name', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Email', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Phone Number', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('CV/Resume', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>) - <?php _e('PDF, DOC, or DOCX file, max 5MB', '500d-salesforce'); ?></li>
                    <li><strong><?php _e('Message', '500d-salesforce'); ?></strong> (<?php _e('required', '500d-salesforce'); ?>)</li>
                    <li><strong><?php _e('Terms of Use', '500d-salesforce'); ?></strong> (<?php _e('required checkbox', '500d-salesforce'); ?>)</li>
                </ul>

                <div class="sfnl-note">
                    <strong><?php _e('File Upload:', '500d-salesforce'); ?></strong> <?php _e('Files are uploaded to WordPress media library with a random prefix for security. The file URL is stored in Salesforce custom field CV__c.', '500d-salesforce'); ?>
                </div>
            </div>

            <!-- Salesforce Custom Fields -->
            <div class="sfnl-doc-section">
                <h2><?php _e('Salesforce Custom Fields', '500d-salesforce'); ?></h2>
                <p><?php _e('The plugin uses these custom fields on the Salesforce Lead object. Create them in Salesforce for full functionality:', '500d-salesforce'); ?></p>

                <table class="sfnl-field-table">
                    <thead>
                        <tr>
                            <th><?php _e('Field API Name', '500d-salesforce'); ?></th>
                            <th><?php _e('Type', '500d-salesforce'); ?></th>
                            <th><?php _e('Used By', '500d-salesforce'); ?></th>
                            <th><?php _e('Description', '500d-salesforce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>Topic__c</code></td>
                            <td>Text</td>
                            <td><?php _e('Contact Form', '500d-salesforce'); ?></td>
                            <td><?php _e('Selected topic/service interest', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>Page_Title__c</code></td>
                            <td>Text</td>
                            <td><?php _e('Contact, Lead Gen, Job Application', '500d-salesforce'); ?></td>
                            <td><?php _e('Page title where form was submitted', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>Referral_URL__c</code></td>
                            <td>URL</td>
                            <td><?php _e('Contact, Lead Gen, Job Application', '500d-salesforce'); ?></td>
                            <td><?php _e('Referrer URL for tracking', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>CV__c</code></td>
                            <td>URL</td>
                            <td><?php _e('Job Application', '500d-salesforce'); ?></td>
                            <td><?php _e('URL to uploaded CV/Resume file', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>NumberOfEmployees</code></td>
                            <td>Number</td>
                            <td><?php _e('Lead Gen', '500d-salesforce'); ?></td>
                            <td><?php _e('Standard Salesforce field', '500d-salesforce'); ?></td>
                        </tr>
                        <tr>
                            <td><code>Country</code></td>
                            <td>Text</td>
                            <td><?php _e('Contact, Lead Gen', '500d-salesforce'); ?></td>
                            <td><?php _e('Standard Salesforce field', '500d-salesforce'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Styling Guide -->
            <div class="sfnl-doc-section">
                <h2><?php _e('Custom Styling', '500d-salesforce'); ?></h2>
                <p><?php _e('All forms use consistent CSS classes that you can target in your theme CSS:', '500d-salesforce'); ?></p>

                <h3><?php _e('Example Custom CSS', '500d-salesforce'); ?></h3>
                <div class="sfnl-code-block">
/* Newsletter Form */
.sfnl-form {
    max-width: 500px;
    margin: 0 auto;
}

.sfnl-form .sfnl-submit-btn {
    background: #2271b1;
    color: white;
    padding: 12px 24px;
}

/* Contact Form */
.sfnl-contact-form .sfnl-input {
    border: 2px solid #ddd;
    border-radius: 4px;
}

/* Lead Gen Form Modal */
.sfnl-modal {
    background: rgba(0,0,0,0.8);
}

.sfnl-modal-content {
    max-width: 600px;
}

/* Job Application Form */
.sfnl-job-application-form .sfnl-file-input {
    border: 2px dashed #2271b1;
}
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Section callbacks
     */
    public function sf_api_section_callback() {
        echo '<p>' . __('Configure your Salesforce API connection settings. You need to create a Connected App in Salesforce to get the Client ID and Client Secret.', '500d-salesforce') . '</p>';
        echo '<p><strong>' . __('How to get these credentials:', '500d-salesforce') . '</strong></p>';
        echo '<ol>';
        echo '<li>' . __('Log in to your Salesforce org', '500d-salesforce') . '</li>';
        echo '<li>' . __('Go to Setup → App Manager → New Connected App', '500d-salesforce') . '</li>';
        echo '<li>' . __('Enable OAuth Settings and add these OAuth Scopes: "Full access (full)" and "Perform requests on your behalf at any time (refresh_token, offline_access)"', '500d-salesforce') . '</li>';
        echo '<li>' . __('Set Callback URL to: https://yoursite.com (can be any valid URL)', '500d-salesforce') . '</li>';
        echo '<li>' . __('Save and copy the Consumer Key (Client ID) and Consumer Secret (Client Secret)', '500d-salesforce') . '</li>';
        echo '<li>' . __('Go to your Profile → Settings → Reset My Security Token to get the Security Token', '500d-salesforce') . '</li>';
        echo '</ol>';
    }

    public function form_section_callback() {
        echo '<p>' . __('Customize the appearance and messages for your newsletter form.', '500d-salesforce') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function environment_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['sf_environment'] ?? 'production';
        ?>
        <select name="sfnl_settings[sf_environment]">
            <option value="production" <?php selected($value, 'production'); ?>><?php _e('Production', '500d-salesforce'); ?></option>
            <option value="sandbox" <?php selected($value, 'sandbox'); ?>><?php _e('Sandbox', '500d-salesforce'); ?></option>
        </select>
        <p class="description"><?php _e('Select your Salesforce environment.', '500d-salesforce'); ?></p>
        <?php
    }

    public function client_id_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['sf_client_id'] ?? '';
        ?>
        <input type="text" name="sfnl_settings[sf_client_id]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Consumer Key from your Salesforce Connected App.', '500d-salesforce'); ?></p>
        <?php
    }

    public function client_secret_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['sf_client_secret'] ?? '';
        ?>
        <div style="position: relative; display: inline-block;">
            <input type="password" id="sf_client_secret" name="sfnl_settings[sf_client_secret]" value="<?php echo esc_attr($value); ?>" class="regular-text sfnl-password-field" style="padding-right: 40px;" />
            <span class="dashicons dashicons-visibility sfnl-toggle-password" data-target="sf_client_secret" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #787c82;"></span>
        </div>
        <p class="description"><?php _e('Consumer Secret from your Salesforce Connected App.', '500d-salesforce'); ?></p>
        <?php
    }

    public function username_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['sf_username'] ?? '';
        ?>
        <input type="email" name="sfnl_settings[sf_username]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Your Salesforce username (email address).', '500d-salesforce'); ?></p>
        <?php
    }

    public function password_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['sf_password'] ?? '';
        ?>
        <div style="position: relative; display: inline-block;">
            <input type="password" id="sf_password" name="sfnl_settings[sf_password]" value="<?php echo esc_attr($value); ?>" class="regular-text sfnl-password-field" style="padding-right: 40px;" />
            <span class="dashicons dashicons-visibility sfnl-toggle-password" data-target="sf_password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #787c82;"></span>
        </div>
        <p class="description"><?php _e('Your Salesforce password.', '500d-salesforce'); ?></p>
        <?php
    }

    public function security_token_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['sf_security_token'] ?? '';
        ?>
        <div style="position: relative; display: inline-block;">
            <input type="password" id="sf_security_token" name="sfnl_settings[sf_security_token]" value="<?php echo esc_attr($value); ?>" class="regular-text sfnl-password-field" style="padding-right: 40px;" />
            <span class="dashicons dashicons-visibility sfnl-toggle-password" data-target="sf_security_token" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #787c82;"></span>
        </div>
        <p class="description"><?php _e('Your Salesforce security token. Get this from Profile → Settings → Reset My Security Token.', '500d-salesforce'); ?></p>
        <?php
    }

    public function form_title_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['form_title'] ?? 'Subscribe to Newsletter';
        ?>
        <input type="text" name="sfnl_settings[form_title]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('The title displayed above the form.', '500d-salesforce'); ?></p>
        <?php
    }

    public function success_message_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['success_message'] ?? 'Thank you for subscribing!';
        ?>
        <textarea name="sfnl_settings[success_message]" rows="3" class="regular-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php _e('Message shown when subscription is successful.', '500d-salesforce'); ?></p>
        <?php
    }

    public function error_message_field_callback() {
        $settings = get_option('sfnl_settings', array());
        $value = $settings['error_message'] ?? 'There was an error. Please try again.';
        ?>
        <textarea name="sfnl_settings[error_message]" rows="3" class="regular-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php _e('Message shown when there is an error.', '500d-salesforce'); ?></p>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['sf_environment'] = sanitize_text_field($input['sf_environment'] ?? 'production');
        $sanitized['sf_client_id'] = sanitize_text_field($input['sf_client_id'] ?? '');
        $sanitized['sf_client_secret'] = sanitize_text_field($input['sf_client_secret'] ?? '');
        $sanitized['sf_username'] = sanitize_email($input['sf_username'] ?? '');
        $sanitized['sf_password'] = sanitize_text_field($input['sf_password'] ?? '');
        $sanitized['sf_security_token'] = sanitize_text_field($input['sf_security_token'] ?? '');
        $sanitized['form_title'] = sanitize_text_field($input['form_title'] ?? 'Subscribe to Newsletter');
        $sanitized['success_message'] = sanitize_textarea_field($input['success_message'] ?? 'Thank you for subscribing!');
        $sanitized['error_message'] = sanitize_textarea_field($input['error_message'] ?? 'There was an error. Please try again.');

        // Clear cached token when settings change
        delete_transient('sfnl_sf_token');

        return $sanitized;
    }

    /**
     * Test Salesforce connection via AJAX
     */
    public function test_connection() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sfnl_admin_nonce')) {
            wp_die('Unauthorized');
        }

        $connector = new SFNL_Salesforce_Connector();
        $result = $connector->test_connection();

        wp_send_json($result);
    }

    /**
     * Export settings to JSON file
     */
    public function export_settings() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sfnl_admin_nonce')) {
            wp_die('Unauthorized');
        }

        $settings = get_option('sfnl_settings', array());

        // Create export data with timestamp
        $export_data = array(
            'plugin_version' => SFNL_VERSION,
            'export_date' => current_time('mysql'),
            'settings' => $settings
        );

        // Send JSON file as download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="sfnl-settings-' . date('Y-m-d-His') . '.json"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

        echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Import settings from JSON file
     */
    public function import_settings() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sfnl_admin_nonce')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', '500d-salesforce')));
        }

        // Check if file was uploaded
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('No file uploaded or upload error.', '500d-salesforce')));
        }

        // Verify file type
        $file_type = wp_check_filetype($_FILES['import_file']['name']);
        if ($file_type['ext'] !== 'json') {
            wp_send_json_error(array('message' => __('Invalid file type. Please upload a JSON file.', '500d-salesforce')));
        }

        // Read file contents
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $json_data = file_get_contents($_FILES['import_file']['tmp_name']);

        if (empty($json_data)) {
            wp_send_json_error(array('message' => __('File is empty or could not be read.', '500d-salesforce')));
        }

        // Decode JSON
        $import_data = json_decode($json_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid JSON format.', '500d-salesforce')));
        }

        // Validate import data structure
        if (!isset($import_data['settings']) || !is_array($import_data['settings'])) {
            wp_send_json_error(array('message' => __('Invalid settings file format.', '500d-salesforce')));
        }

        // Sanitize and validate settings
        $settings = $import_data['settings'];
        $sanitized_settings = array(
            'sf_environment' => isset($settings['sf_environment']) ? sanitize_text_field($settings['sf_environment']) : 'production',
            'sf_client_id' => isset($settings['sf_client_id']) ? sanitize_text_field($settings['sf_client_id']) : '',
            'sf_client_secret' => isset($settings['sf_client_secret']) ? sanitize_text_field($settings['sf_client_secret']) : '',
            'sf_username' => isset($settings['sf_username']) ? sanitize_email($settings['sf_username']) : '',
            'sf_password' => isset($settings['sf_password']) ? sanitize_text_field($settings['sf_password']) : '',
            'sf_security_token' => isset($settings['sf_security_token']) ? sanitize_text_field($settings['sf_security_token']) : '',
            'form_title' => isset($settings['form_title']) ? sanitize_text_field($settings['form_title']) : 'Subscribe to Newsletter',
            'success_message' => isset($settings['success_message']) ? sanitize_textarea_field($settings['success_message']) : 'Thank you for subscribing!',
            'error_message' => isset($settings['error_message']) ? sanitize_textarea_field($settings['error_message']) : 'There was an error. Please try again.'
        );

        // Update settings
        update_option('sfnl_settings', $sanitized_settings);

        // Clear cached token
        delete_transient('sfnl_sf_token');

        wp_send_json_success(array(
            'message' => __('Settings imported successfully!', '500d-salesforce'),
            'imported_date' => isset($import_data['export_date']) ? $import_data['export_date'] : 'Unknown'
        ));
    }

    /**
     * AJAX handler to get submissions with filters and pagination
     */
    public function ajax_get_submissions() {
        if (!current_user_can('manage_options') || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sfnl_admin_nonce')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        // Get pagination parameters
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = 30;
        $offset = ($page - 1) * $per_page;

        // Get filter parameters
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $form_type = isset($_POST['form_type']) ? sanitize_text_field(wp_unslash($_POST['form_type'])) : '';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';

        // Build WHERE clause
        $where_clauses = array();
        $where_values = array();

        if (!empty($search)) {
            $where_clauses[] = "(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR company_name LIKE %s OR phone LIKE %s OR salesforce_id LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        if (!empty($form_type)) {
            $where_clauses[] = "form_type = %s";
            $where_values[] = $form_type;
        }

        if (!empty($status)) {
            $where_clauses[] = "status = %s";
            $where_values[] = $status;
        }

        if (!empty($date_from)) {
            $where_clauses[] = "DATE(submission_date) >= %s";
            $where_values[] = $date_from;
        }

        if (!empty($date_to)) {
            $where_clauses[] = "DATE(submission_date) <= %s";
            $where_values[] = $date_to;
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table_name $where_sql";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total_records = $wpdb->get_var($count_query);
        $total_pages = ceil($total_records / $per_page);

        // Get submissions
        $query = "SELECT * FROM $table_name $where_sql ORDER BY submission_date DESC LIMIT %d OFFSET %d";
        $query_values = array_merge($where_values, array($per_page, $offset));
        $submissions = $wpdb->get_results($wpdb->prepare($query, $query_values));

        wp_send_json_success(array(
            'submissions' => $submissions,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        ));
    }

    /**
     * Diagnostics page
     */
    public function diagnostics_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Database Diagnostics', '500d-salesforce'); ?></h1>

            <div class="notice notice-info">
                <p><?php echo esc_html__('Use this tool to check and repair the database table structure.', '500d-salesforce'); ?></p>
            </div>

            <div class="card" style="max-width: 800px;">
                <h2><?php echo esc_html__('Table Status', '500d-salesforce'); ?></h2>
                <div id="sfnl-diagnostic-results" style="margin: 20px 0;">
                    <p><?php echo esc_html__('Click "Run Diagnostics" to check the database table.', '500d-salesforce'); ?></p>
                </div>

                <p>
                    <button type="button" id="sfnl-run-diagnostics" class="button button-secondary">
                        <?php echo esc_html__('Run Diagnostics', '500d-salesforce'); ?>
                    </button>
                    <button type="button" id="sfnl-fix-table" class="button button-primary" style="display:none;">
                        <?php echo esc_html__('Fix Table', '500d-salesforce'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </p>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2><?php echo esc_html__('Manual Commands', '500d-salesforce'); ?></h2>
                <p><?php echo esc_html__('If the automatic fix does not work, you can run these commands manually via WP-CLI:', '500d-salesforce'); ?></p>
                <pre style="background: #f5f5f5; padding: 15px; border-left: 4px solid #0073aa;">cd <?php echo esc_html(ABSPATH); ?>
wp eval-file wp-content/plugins/500d-salesforce/diagnose.php
wp eval-file wp-content/plugins/500d-salesforce/fix-table.php</pre>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var $results = $('#sfnl-diagnostic-results');
            var $fixButton = $('#sfnl-fix-table');
            var $spinner = $('.spinner');

            $('#sfnl-run-diagnostics').on('click', function() {
                $spinner.addClass('is-active');
                $fixButton.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sfnl_diagnose_table',
                        nonce: '<?php echo esc_js(wp_create_nonce('sfnl_diagnose_nonce')); ?>'
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        if (response.success) {
                            $results.html(response.data.html);
                            if (response.data.needs_fix) {
                                $fixButton.show();
                            }
                        } else {
                            $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $spinner.removeClass('is-active');
                        $results.html('<div class="notice notice-error"><p>AJAX error. Please try again.</p></div>');
                    }
                });
            });

            $('#sfnl-fix-table').on('click', function() {
                if (!confirm('<?php echo esc_js(__('Are you sure you want to fix the database table?', '500d-salesforce')); ?>')) {
                    return;
                }

                $spinner.addClass('is-active');
                $fixButton.prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sfnl_fix_table',
                        nonce: '<?php echo esc_js(wp_create_nonce('sfnl_fix_nonce')); ?>'
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        $fixButton.prop('disabled', false);
                        if (response.success) {
                            $results.html(response.data.html);
                            $fixButton.hide();
                        } else {
                            $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $spinner.removeClass('is-active');
                        $fixButton.prop('disabled', false);
                        $results.html('<div class="notice notice-error"><p>AJAX error. Please try again.</p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for table diagnostics
     */
    public function ajax_diagnose_table() {
        check_ajax_referer('sfnl_diagnose_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', '500d-salesforce')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));

        if (!$table_exists) {
            wp_send_json_error(array(
                'message' => sprintf(__('Table %s does not exist!', '500d-salesforce'), $table_name)
            ));
        }

        $columns = $wpdb->get_results("DESCRIBE {$table_name}");

        $expected_columns = array(
            'id', 'form_type', 'email', 'first_name', 'last_name', 'phone',
            'topic', 'message', 'company_name', 'employees', 'country', 'cv_url',
            'page_title', 'referral_url', 'submission_date', 'salesforce_id',
            'status', 'error_message'
        );

        $existing_columns = array();
        $column_html = '<ul style="font-family: monospace; font-size: 12px;">';
        foreach ($columns as $col) {
            $existing_columns[] = $col->Field;
            $column_html .= '<li>' . esc_html($col->Field) . ' (' . esc_html($col->Type) . ')</li>';
        }
        $column_html .= '</ul>';

        $missing = array_diff($expected_columns, $existing_columns);

        $html = '<div class="notice notice-success"><p><strong>' . esc_html__('Table exists:', '500d-salesforce') . '</strong> ' . esc_html($table_name) . '</p></div>';
        $html .= '<h3>' . esc_html__('Current Columns:', '500d-salesforce') . '</h3>' . $column_html;

        $needs_fix = false;
        if (!empty($missing)) {
            $needs_fix = true;
            $html .= '<div class="notice notice-warning"><p><strong>' . esc_html__('Missing columns:', '500d-salesforce') . '</strong> ' . esc_html(implode(', ', $missing)) . '</p></div>';
        } else {
            $html .= '<div class="notice notice-success"><p><strong>' . esc_html__('All required columns are present!', '500d-salesforce') . '</strong></p></div>';
        }

        wp_send_json_success(array(
            'html' => $html,
            'needs_fix' => $needs_fix
        ));
    }

    /**
     * AJAX handler for fixing table
     */
    public function ajax_fix_table() {
        check_ajax_referer('sfnl_fix_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', '500d-salesforce')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));

        if (!$table_exists) {
            sfnl_create_tables();
            wp_send_json_success(array(
                'html' => '<div class="notice notice-success"><p><strong>' . esc_html__('Table created successfully!', '500d-salesforce') . '</strong></p></div>'
            ));
            return;
        }

        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        $existing_columns = array();
        foreach ($columns as $col) {
            $existing_columns[] = $col->Field;
        }

        $columns_to_add = array(
            'company_name' => "ALTER TABLE {$table_name} ADD COLUMN company_name varchar(255) AFTER message",
            'employees' => "ALTER TABLE {$table_name} ADD COLUMN employees varchar(10) AFTER company_name",
            'country' => "ALTER TABLE {$table_name} ADD COLUMN country varchar(100) AFTER employees",
            'cv_url' => "ALTER TABLE {$table_name} ADD COLUMN cv_url varchar(500) AFTER country"
        );

        $html = '<h3>' . esc_html__('Fix Results:', '500d-salesforce') . '</h3>';
        $changes_made = false;

        foreach ($columns_to_add as $column => $sql) {
            if (!in_array($column, $existing_columns)) {
                $result = $wpdb->query($sql);
                if ($result === false) {
                    $html .= '<div class="notice notice-error"><p>' . esc_html(sprintf(__('Failed to add column %s: %s', '500d-salesforce'), $column, $wpdb->last_error)) . '</p></div>';
                } else {
                    $html .= '<div class="notice notice-success"><p>' . esc_html(sprintf(__('Added column: %s', '500d-salesforce'), $column)) . '</p></div>';
                    $changes_made = true;
                }
            }
        }

        if (!$changes_made) {
            $html .= '<div class="notice notice-info"><p>' . esc_html__('No changes needed - all columns already exist.', '500d-salesforce') . '</p></div>';
        } else {
            $html .= '<div class="notice notice-success"><p><strong>' . esc_html__('Table structure updated successfully!', '500d-salesforce') . '</strong></p></div>';
        }

        wp_send_json_success(array('html' => $html));
    }
}