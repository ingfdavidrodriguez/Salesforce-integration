<?php

if (!defined('ABSPATH')) {
    exit;
}

class SFNL_Form_Handler {

    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_sfnl_submit_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_sfnl_submit_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_sfnl_submit_contact_form', array($this, 'handle_contact_form_submission'));
        add_action('wp_ajax_nopriv_sfnl_submit_contact_form', array($this, 'handle_contact_form_submission'));
        add_action('wp_ajax_sfnl_submit_leadgen_form', array($this, 'handle_leadgen_form_submission'));
        add_action('wp_ajax_nopriv_sfnl_submit_leadgen_form', array($this, 'handle_leadgen_form_submission'));
        add_action('wp_ajax_sfnl_submit_job_application', array($this, 'handle_job_application_submission'));
        add_action('wp_ajax_nopriv_sfnl_submit_job_application', array($this, 'handle_job_application_submission'));
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('jquery');

        wp_enqueue_style(
            'sfnl-frontend',
            SFNL_PLUGIN_URL . 'public/css/frontend.css',
            array(),
            SFNL_VERSION
        );

        // Add inline CSS with correct SVG path
        $svg_url = SFNL_PLUGIN_URL . 'public/images/IMPEX-Glyph.svg';
        $custom_css = "
        /* High specificity selectors for dropdown icon */
        select.sfnl-input.sfnl-select,
        .sfnl-contact-form select.sfnl-select,
        .sfnl-leadgen-form select.sfnl-select,
        #sfnl-topic.sfnl-select,
        #sfnl-leadgen-employees.sfnl-select,
        #sfnl-leadgen-country.sfnl-select {
            background-image: url('" . esc_url($svg_url) . "') !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            background-size: 20px !important;
        }
        ";
        wp_add_inline_style('sfnl-frontend', $custom_css);

        wp_enqueue_script(
            'sfnl-frontend',
            SFNL_PLUGIN_URL . 'public/js/frontend.js',
            array('jquery'),
            time(),
            true
        );

        wp_localize_script('sfnl-frontend', 'sfnl_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sfnl_form_nonce'),
            'contact_nonce' => wp_create_nonce('sfnl_contact_form_nonce'),
            'leadgen_nonce' => wp_create_nonce('sfnl_leadgen_form_nonce'),
            'job_application_nonce' => wp_create_nonce('sfnl_job_application_nonce')
        ));
    }

    /**
     * Render the newsletter form
     */
    public function render_form($atts = array()) {
        $settings = get_option('sfnl_settings', array());

        $title = !empty($atts['title']) ? $atts['title'] : ($settings['form_title'] ?? 'Subscribe to Newsletter');
        $class = !empty($atts['class']) ? $atts['class'] : 'sfnl-form';

        // Check if Salesforce is configured
        $connector = new SFNL_Salesforce_Connector();
        $is_configured = $connector->is_configured();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>" id="sfnl-form-container">
            <?php if (!empty($title)): ?>
                <h3 class="sfnl-form-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>


            <?php if (!$is_configured && current_user_can('manage_options')): ?>
                <div class="sfnl-notice sfnl-notice-warning">
                    <p><?php _e('Salesforce integration is not configured.', '500d-salesforce'); ?>
                    <a href="<?php echo admin_url('admin.php?page=sfnl-settings'); ?>"><?php _e('Configure now', '500d-salesforce'); ?></a></p>
                </div>
            <?php endif; ?>

            <form id="sfnl-newsletter-form" method="post">
                <div class="sfnl-form-group">
                    <label for="sfnl-email" class="sfnl-label">
                        <?php _e('Email Address', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <input
                        type="email"
                        id="sfnl-email"
                        name="email"
                        class="sfnl-input sfnl-email-input"
                        required
                        placeholder="<?php esc_attr_e('Enter your email address', '500d-salesforce'); ?>"
                    />
                </div>

                <div class="sfnl-form-group">
                    <button type="submit" class="btn-effect fade-left-right" id="sfnl-submit-btn">
                        <span class="sfnl-btn-text"><?php _e('Subscribe', '500d-salesforce'); ?></span>
                        <span class="sfnl-btn-loading" style="display: none;"><?php _e('Subscribing...', '500d-salesforce'); ?></span>
                        <svg>
                            <defs>
                                <linearGradient id="borderGradient-newsletter" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="rgba(243, 108, 33, 0.70)" />
                                    <stop offset="52.51%" stop-color="rgba(243, 108, 33, 0.07)" />
                                    <stop offset="100.99%" stop-color="rgba(243, 108, 33, 0.35)" />
                                </linearGradient>
                            </defs>
                            <rect rx="0" ry="0" />
                        </svg>
                    </button>
                </div>

                <div id="sfnl-form-messages" class="sfnl-messages"></div>

                <?php wp_nonce_field('sfnl_form_nonce', 'sfnl_nonce'); ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle form submission via AJAX
     */
    public function handle_form_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sfnl_form_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', '500d-salesforce')));
        }

        // Validate email
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (empty($email)) {
            wp_send_json_error(array('message' => __('Please enter an email address.', '500d-salesforce')));
        }

        // Validate email with domain check
        if (!$this->validate_email_domain($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address with a valid domain.', '500d-salesforce')));
        }

        // Check if email already exists
        if ($this->email_already_submitted($email)) {
            wp_send_json_error(array('message' => __('This email address is already subscribed.', '500d-salesforce')));
        }

        // Store submission in database
        $submission_id = $this->store_submission($email);
        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Database error. Please try again.', '500d-salesforce')));
        }

        // Submit to Salesforce
        $connector = new SFNL_Salesforce_Connector();
        $sf_result = $connector->create_lead($email);

        $settings = get_option('sfnl_settings', array());

        if ($sf_result['success']) {
            // Update submission with Salesforce ID
            $this->update_submission($submission_id, 'success', $sf_result['id'], '');

            wp_send_json_success(array(
                'message' => $settings['success_message'] ?? __('Thank you for subscribing!', '500d-salesforce')
            ));
        } else {
            // Update submission with error
            $error_message = $sf_result['error'] ?? __('Unknown error', '500d-salesforce');
            $this->update_submission($submission_id, 'failed', '', $error_message);

            // Log the error
            error_log('SFNL: Failed to create lead for ' . $email . ': ' . $error_message);

            wp_send_json_error(array(
                'message' => $settings['error_message'] ?? __('There was an error. Please try again.', '500d-salesforce')
            ));
        }
    }

    /**
     * Check if email already submitted
     */
    private function email_already_submitted($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE email = %s AND status = 'success'",
            $email
        ));

        return $count > 0;
    }

    /**
     * Store submission in database
     */
    private function store_submission($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        $result = $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'submission_date' => current_time('mysql'),
                'status' => 'pending'
            ),
            array('%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update submission status
     */
    private function update_submission($id, $status, $salesforce_id = '', $error_message = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        $wpdb->update(
            $table_name,
            array(
                'status' => $status,
                'salesforce_id' => $salesforce_id,
                'error_message' => $error_message
            ),
            array('id' => $id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }

    /**
     * Render the contact form
     */
    public function render_contact_form($atts = array()) {
        $settings = get_option('sfnl_settings', array());

        $title = !empty($atts['title']) ? $atts['title'] : '';
        $class = !empty($atts['class']) ? $atts['class'] : 'sfnl-contact-form';

        // Check if Salesforce is configured
        $connector = new SFNL_Salesforce_Connector();
        $is_configured = $connector->is_configured();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>" id="sfnl-contact-form-container">
            <?php if (!empty($title)): ?>
                <h3 class="sfnl-form-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <?php if (!$is_configured && current_user_can('manage_options')): ?>
                <div class="sfnl-notice sfnl-notice-warning">
                    <p><?php _e('Salesforce integration is not configured.', '500d-salesforce'); ?>
                    <a href="<?php echo admin_url('admin.php?page=sfnl-settings'); ?>"><?php _e('Configure now', '500d-salesforce'); ?></a></p>
                </div>
            <?php endif; ?>

            <form id="sfnl-contact-form" method="post">
                <div class="sfnl-form-row">
                    <div class="sfnl-form-group sfnl-half">
                        <label for="sfnl-first-name" class="sfnl-label">
                            <?php _e('First Name', '500d-salesforce'); ?>
                            <span class="sfnl-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="sfnl-first-name"
                            name="first_name"
                            class="sfnl-input"
                            required
                        />
                    </div>

                    <div class="sfnl-form-group sfnl-half">
                        <label for="sfnl-last-name" class="sfnl-label">
                            <?php _e('Last Name', '500d-salesforce'); ?>
                            <span class="sfnl-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="sfnl-last-name"
                            name="last_name"
                            class="sfnl-input"
                            required
                        />
                    </div>
                </div>

                <div class="sfnl-form-row">
                    <div class="sfnl-form-group sfnl-half">
                        <label for="sfnl-contact-email" class="sfnl-label">
                            <?php _e('Email', '500d-salesforce'); ?>
                            <span class="sfnl-required">*</span>
                        </label>
                        <input
                            type="email"
                            id="sfnl-contact-email"
                            name="email"
                            class="sfnl-input"
                            required
                        />
                    </div>

                    <div class="sfnl-form-group sfnl-half">
                        <label for="sfnl-phone" class="sfnl-label">
                            <?php _e('Phone Number', '500d-salesforce'); ?>
                            <span class="sfnl-required">*</span>
                        </label>
                        <input
                            type="tel"
                            id="sfnl-phone"
                            name="phone"
                            class="sfnl-input"
                            required
                        />
                    </div>
                </div>

                <div class="sfnl-form-group">
                    <label for="sfnl-topic" class="sfnl-label">
                        <?php _e('Choose a topic', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <select id="sfnl-topic" name="topic" class="sfnl-input sfnl-select" required>
                        <option value=""><?php _e('Select one...', '500d-salesforce'); ?></option>
                        <option value="Consulting Services"><?php _e('Consulting Services', '500d-salesforce'); ?></option>
                        <option value="Implementation Services"><?php _e('Implementation Services', '500d-salesforce'); ?></option>
                        <option value="Managed Services"><?php _e('Managed Services', '500d-salesforce'); ?></option>
                        <option value="Residency Services"><?php _e('Residency Services', '500d-salesforce'); ?></option>
                    </select>
                </div>

                <div class="sfnl-form-group">
                    <label for="sfnl-message" class="sfnl-label">
                        <?php _e('Message', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <textarea
                        id="sfnl-message"
                        name="message"
                        class="sfnl-input sfnl-textarea"
                        rows="5"
                        placeholder="<?php esc_attr_e('Type your message...', '500d-salesforce'); ?>"
                        required
                    ></textarea>
                </div>

                <div class="sfnl-form-group sfnl-checkbox-group">
                    <label class="sfnl-checkbox-label">
                        <input
                            type="checkbox"
                            id="sfnl-terms"
                            name="terms"
                            class="sfnl-checkbox"
                            required
                        />
                        <span><?php _e('I accept the', '500d-salesforce'); ?>
                        <a href="<?php echo esc_url(home_url('/terms-of-use/')); ?>" target="_blank"><?php _e('Terms of Use', '500d-salesforce'); ?></a>
                        <span class="sfnl-required">*</span>
                        </span>
                    </label>
                </div>

                <div class="sfnl-form-group">
                    <button type="submit" class="btn-effect fade-left-right" id="sfnl-contact-submit-btn">
                        <span class="sfnl-btn-text"><?php _e('Submit', '500d-salesforce'); ?></span>
                        <span class="sfnl-btn-loading" style="display: none;"><?php _e('Submitting...', '500d-salesforce'); ?></span>
                        <svg>
                            <defs>
                                <linearGradient id="borderGradient-contact" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="rgba(243, 108, 33, 0.70)" />
                                    <stop offset="52.51%" stop-color="rgba(243, 108, 33, 0.07)" />
                                    <stop offset="100.99%" stop-color="rgba(243, 108, 33, 0.35)" />
                                </linearGradient>
                            </defs>
                            <rect rx="0" ry="0" />
                        </svg>
                    </button>
                </div>

                <div id="sfnl-contact-form-messages" class="sfnl-messages"></div>

                <?php wp_nonce_field('sfnl_contact_form_nonce', 'sfnl_contact_nonce'); ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle contact form submission via AJAX
     */
    public function handle_contact_form_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sfnl_contact_form_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', '500d-salesforce')));
        }

        // Validate and sanitize inputs
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $page_title = isset($_POST['page_title']) ? sanitize_text_field(wp_unslash($_POST['page_title'])) : '';
        $referral_url = isset($_POST['referral_url']) ? esc_url_raw(wp_unslash($_POST['referral_url'])) : '';

        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($topic) || empty($message)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', '500d-salesforce')));
        }

        // Validate email with domain check
        if (!$this->validate_email_domain($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address with a valid domain.', '500d-salesforce')));
        }

        // Validate US phone number
        if (!$this->validate_us_phone($phone)) {
            wp_send_json_error(array('message' => __('Please enter a valid US phone number (e.g., (123) 456-7890 or 123-456-7890).', '500d-salesforce')));
        }

        // Store submission in database
        $submission_id = $this->store_contact_submission(array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'topic' => $topic,
            'message' => $message,
            'page_title' => $page_title,
            'referral_url' => $referral_url
        ));

        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Database error. Please try again.', '500d-salesforce')));
        }

        // Submit to Salesforce
        $connector = new SFNL_Salesforce_Connector();
        $sf_result = $connector->create_contact_lead(array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'topic' => $topic,
            'message' => $message,
            'page_title' => $page_title,
            'referral_url' => $referral_url
        ));

        $settings = get_option('sfnl_settings', array());

        if ($sf_result['success']) {
            // Update submission with Salesforce ID
            $this->update_submission($submission_id, 'success', $sf_result['id'], '');

            wp_send_json_success(array(
                'message' => $settings['success_message'] ?? __('Thank you for contacting us!', '500d-salesforce'),
                'redirect_url' => home_url('/?form=success#form')
            ));
        } else {
            // Update submission with error
            $error_message = $sf_result['error'] ?? __('Unknown error', '500d-salesforce');
            $this->update_submission($submission_id, 'failed', '', $error_message);

            wp_send_json_error(array(
                'message' => $settings['error_message'] ?? __('There was an error. Please try again.', '500d-salesforce')
            ));
        }
    }

    /**
     * Store contact form submission in database
     */
    private function store_contact_submission($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        // Check if table exists and has required columns
        $columns = $wpdb->get_col("DESCRIBE {$table_name}");

        $result = $wpdb->insert(
            $table_name,
            array(
                'form_type' => 'contact',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => $data['country'],
                'topic' => $data['topic'],
                'message' => $data['message'],
                'page_title' => $data['page_title'],
                'referral_url' => $data['referral_url'],
                'submission_date' => current_time('mysql'),
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            error_log('SFNL Contact Form DB Error: ' . $wpdb->last_error);
            error_log('SFNL Contact Form DB Query: ' . $wpdb->last_query);
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Render the lead generation form
     */
    public function render_leadgen_form($atts = array()) {
        $settings = get_option('sfnl_settings', array());

        $title = !empty($atts['title']) ? $atts['title'] : '';
        $description = !empty($atts['description']) ? $atts['description'] : '';
        $class = !empty($atts['class']) ? $atts['class'] : 'sfnl-leadgen-form';
        $target = !empty($atts['target']) && $atts['target'] === 'blank' ? '_blank' : '_self';
        $align = !empty($atts['align']) ? $atts['align'] : 'center';

        // Validate align value
        $valid_aligns = array('left', 'center', 'right');
        if (!in_array($align, $valid_aligns)) {
            $align = 'center';
        }

        // Process macros - replace %page_title% with actual page title
        $page_title = get_the_title();
        $title = str_replace('%page_title%', $page_title, $title);
        $description = str_replace('%page_title%', $page_title, $description);

        // Replace "Download" with "Access" in title
        $title = str_replace('Download', 'Access', $title);

        // Check if Salesforce is configured
        $connector = new SFNL_Salesforce_Connector();
        $is_configured = $connector->is_configured();

        // Get white paper URL from post meta
        $whitepaper_url = get_post_meta(get_the_ID(), 'upload_white_paper_file', true);

        ob_start();
        ?>
        <div id="sfnl-leadgen-modal" class="sfnl-modal" data-post-id="<?php echo esc_attr(get_the_ID()); ?>" data-whitepaper-url="<?php echo esc_url($whitepaper_url); ?>">
            <div class="sfnl-modal-content">
                <a href="/resources" class="sfnl-modal-close" aria-label="Close">&times;</a>
                <div class="<?php echo esc_attr($class); ?>" id="sfnl-leadgen-form-container" data-target="<?php echo esc_attr($target); ?>" data-post-id="<?php echo esc_attr(get_the_ID()); ?>">
                    <?php if (!empty($title)): ?>
                        <div class="sfnl-form-title sfnl-align-<?php echo esc_attr($align); ?>"><?php echo esc_html($title); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($description)): ?>
                        <p class="sfnl-form-description sfnl-align-<?php echo esc_attr($align); ?>"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>

            <?php if (!$is_configured && current_user_can('manage_options')): ?>
                <div class="sfnl-notice sfnl-notice-warning">
                    <p><?php _e('Salesforce integration is not configured.', '500d-salesforce'); ?>
                    <a href="<?php echo admin_url('admin.php?page=sfnl-settings'); ?>"><?php _e('Configure now', '500d-salesforce'); ?></a></p>
                </div>
            <?php endif; ?>

            <form id="sfnl-leadgen-form" method="post">
                <div class="sfnl-form-row">
                    <div class="sfnl-form-group sfnl-half">
                        <label for="sfnl-leadgen-email" class="sfnl-label">
                            <?php _e('Company Email', '500d-salesforce'); ?>
                            <span class="sfnl-required">*</span>
                        </label>
                        <input
                            type="email"
                            id="sfnl-leadgen-email"
                            name="email"
                            class="sfnl-input"
                            required
                        />
                    </div>

                    <div class="sfnl-form-group sfnl-half">
                        <label for="sfnl-company-name" class="sfnl-label">
                            <?php _e('Company Name', '500d-salesforce'); ?>
                            <span class="sfnl-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="sfnl-company-name"
                            name="company_name"
                            class="sfnl-input"
                            required
                        />
                    </div>
                </div>

                <div class="sfnl-form-row">
                    <div class="sfnl-form-group sfnl-half">
                        <label for="sfnl-leadgen-first-name" class="sfnl-label">
                            <?php _e('First Name', '500d-salesforce'); ?>
                            <span class="sfnl-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="sfnl-leadgen-first-name"
                            name="first_name"
                            class="sfnl-input"
                            required
                        />
                    </div>

                    <div class="sfnl-form-group sfnl-half">
                        <label for="sfnl-leadgen-last-name" class="sfnl-label">
                            <?php _e('Last Name', '500d-salesforce'); ?>
                            <span class="sfnl-required">*</span>
                        </label>
                        <input
                            type="text"
                            id="sfnl-leadgen-last-name"
                            name="last_name"
                            class="sfnl-input"
                            required
                        />
                    </div>
                </div>

                <div class="sfnl-form-group">
                    <label for="sfnl-leadgen-phone" class="sfnl-label">
                        <?php _e('Phone Number', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <input
                        type="tel"
                        id="sfnl-leadgen-phone"
                        name="phone"
                        class="sfnl-input"
                        required
                    />
                </div>

                <div class="sfnl-form-group">
                    <label for="sfnl-leadgen-employees" class="sfnl-label">
                        <?php _e('Number of Employees', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <select id="sfnl-leadgen-employees" name="employees" class="sfnl-input sfnl-select" required>
                        <option value=""><?php _e('Select range', '500d-salesforce'); ?></option>
                        <option value="1">1-10</option>
                        <option value="11">11-50</option>
                        <option value="51">51-200</option>
                        <option value="201">201-500</option>
                        <option value="501">501-1000</option>
                        <option value="1001">1001-5000</option>
                        <option value="5001">5001+</option>
                    </select>
                </div>

                <div class="sfnl-form-group sfnl-checkbox-group">
                    <label class="sfnl-checkbox-label">
                        <input
                            type="checkbox"
                            id="sfnl-leadgen-terms"
                            name="terms"
                            class="sfnl-checkbox"
                            required
                        />
                        <span><?php _e('I accept the', '500d-salesforce'); ?>
                        <a href="<?php echo esc_url(home_url('/terms-of-use/')); ?>" target="_blank"><?php _e('Terms of Use', '500d-salesforce'); ?></a>
                        <span class="sfnl-required">*</span>
                        </span>
                    </label>
                </div>

                <div class="sfnl-form-group">
                    <button type="submit" class="btn-effect fade-left-right" id="sfnl-leadgen-submit-btn">
                        <span class="sfnl-btn-text"><?php _e('Submit', '500d-salesforce'); ?></span>
                        <span class="sfnl-btn-loading" style="display: none;"><?php _e('Submitting...', '500d-salesforce'); ?></span>
                        <svg>
                            <defs>
                                <linearGradient id="borderGradient-leadgen" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="rgba(243, 108, 33, 0.70)" />
                                    <stop offset="52.51%" stop-color="rgba(243, 108, 33, 0.07)" />
                                    <stop offset="100.99%" stop-color="rgba(243, 108, 33, 0.35)" />
                                </linearGradient>
                            </defs>
                            <rect rx="0" ry="0" />
                        </svg>
                    </button>
                </div>

                <div id="sfnl-leadgen-form-messages" class="sfnl-messages"></div>

                <?php wp_nonce_field('sfnl_leadgen_form_nonce', 'sfnl_leadgen_nonce'); ?>
            </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle lead generation form submission via AJAX
     */
    public function handle_leadgen_form_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sfnl_leadgen_form_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', '500d-salesforce')));
        }

        // Validate and sanitize inputs
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $employees = isset($_POST['employees']) ? sanitize_text_field(wp_unslash($_POST['employees'])) : '';
        $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
        $page_title = isset($_POST['page_title']) ? sanitize_text_field(wp_unslash($_POST['page_title'])) : '';
        $referral_url = isset($_POST['referral_url']) ? esc_url_raw(wp_unslash($_POST['referral_url'])) : '';

        // Validate required fields
        if (empty($email) || empty($company_name) || empty($first_name) || empty($last_name) || empty($phone) || empty($employees)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields.', '500d-salesforce')));
        }

        // Validate email with domain check
        if (!$this->validate_email_domain($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address with a valid domain.', '500d-salesforce')));
        }

        // Validate US phone number
        if (!$this->validate_us_phone($phone)) {
            wp_send_json_error(array('message' => __('Please enter a valid US phone number (e.g., (123) 456-7890 or 123-456-7890).', '500d-salesforce')));
        }

        // Store submission in database
        $submission_id = $this->store_leadgen_submission(array(
            'email' => $email,
            'company_name' => $company_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'employees' => $employees,
            'country' => $country,
            'page_title' => $page_title,
            'referral_url' => $referral_url
        ));

        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Database error. Please try again.', '500d-salesforce')));
        }

        // Submit to Salesforce
        $connector = new SFNL_Salesforce_Connector();
        $sf_result = $connector->create_leadgen_lead(array(
            'email' => $email,
            'company_name' => $company_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'employees' => $employees,
            'country' => $country,
            'page_title' => $page_title,
            'referral_url' => $referral_url
        ));

        $settings = get_option('sfnl_settings', array());

        if ($sf_result['success']) {
            // Update submission with Salesforce ID
            $this->update_submission($submission_id, 'success', $sf_result['id'], '');

            // Get white paper URL from post meta
            $redirect_url = '';
            if ($post_id > 0) {
                $meta_url = get_post_meta($post_id, 'upload_white_paper_file', true);
                if (!empty($meta_url)) {
                    $redirect_url = esc_url($meta_url);
                }
            }

            wp_send_json_success(array(
                'message' => __('Thank you! Your information has been submitted successfully.', '500d-salesforce'),
                'redirect_url' => $redirect_url
            ));
        } else {
            // Update submission with error
            $error_message = $sf_result['error'] ?? __('Unknown error', '500d-salesforce');
            $this->update_submission($submission_id, 'failed', '', $error_message);

            wp_send_json_error(array(
                'message' => $settings['error_message'] ?? __('There was an error. Please try again.', '500d-salesforce')
            ));
        }
    }

    /**
     * Store lead generation form submission in database
     */
    private function store_leadgen_submission($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        $result = $wpdb->insert(
            $table_name,
            array(
                'form_type' => 'leadgen',
                'email' => $data['email'],
                'company_name' => $data['company_name'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'employees' => $data['employees'],
                'country' => $data['country'],
                'page_title' => $data['page_title'],
                'referral_url' => $data['referral_url'],
                'submission_date' => current_time('mysql'),
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            error_log('SFNL Lead Gen Form DB Error: ' . $wpdb->last_error);
            error_log('SFNL Lead Gen Form DB Query: ' . $wpdb->last_query);
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Render the job application form
     */
    public function render_job_application_form($atts = array()) {
        $settings = get_option('sfnl_settings', array());

        $title = !empty($atts['title']) ? $atts['title'] : '';
        $class = !empty($atts['class']) ? $atts['class'] : 'sfnl-job-application-form';

        // Check if Salesforce is configured
        $connector = new SFNL_Salesforce_Connector();
        $is_configured = $connector->is_configured();

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class); ?>" id="sfnl-job-application-form-container">
            <?php if (!empty($title)): ?>
                <h3 class="sfnl-form-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <?php if (!$is_configured && current_user_can('manage_options')): ?>
                <div class="sfnl-notice sfnl-notice-warning">
                    <p><?php _e('Salesforce integration is not configured.', '500d-salesforce'); ?>
                    <a href="<?php echo admin_url('admin.php?page=sfnl-settings'); ?>"><?php _e('Configure now', '500d-salesforce'); ?></a></p>
                </div>
            <?php endif; ?>

            <form id="sfnl-job-application-form" method="post" enctype="multipart/form-data">
                <div class="sfnl-form-group">
                    <label for="sfnl-job-name" class="sfnl-label">
                        <?php _e('Name', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <input
                        type="text"
                        id="sfnl-job-name"
                        name="name"
                        class="sfnl-input"
                        required
                    />
                </div>

                <div class="sfnl-form-group">
                    <label for="sfnl-job-email" class="sfnl-label">
                        <?php _e('Email', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <input
                        type="email"
                        id="sfnl-job-email"
                        name="email"
                        class="sfnl-input"
                        required
                    />
                </div>

                <div class="sfnl-form-group">
                    <label for="sfnl-job-phone" class="sfnl-label">
                        <?php _e('Phone Number', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <input
                        type="tel"
                        id="sfnl-job-phone"
                        name="phone"
                        class="sfnl-input"
                        required
                    />
                </div>

                <div class="sfnl-form-group">
                    <label for="sfnl-job-cv" class="sfnl-label">
                        <?php _e('CV/Resume', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <input
                        type="file"
                        id="sfnl-job-cv"
                        name="cv"
                        class="sfnl-input sfnl-file-input"
                        accept=".pdf,.doc,.docx"
                        required
                        style="display: none;"
                    />
                    <button type="button" class="btn-effect fade-left-right" id="sfnl-job-cv-btn">
                        <span class="sfnl-btn-text"><?php _e('Upload File', '500d-salesforce'); ?></span>
                        <svg>
                            <defs>
                                <linearGradient id="borderGradient-cv" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="rgba(243, 108, 33, 0.70)" />
                                    <stop offset="52.51%" stop-color="rgba(243, 108, 33, 0.07)" />
                                    <stop offset="100.99%" stop-color="rgba(243, 108, 33, 0.35)" />
                                </linearGradient>
                            </defs>
                            <rect rx="0" ry="0" />
                        </svg>
                    </button>
                    <div id="sfnl-job-cv-filename" class="sfnl-filename-display"></div>
                    <small class="sfnl-help-text"><?php _e('Accepted formats: PDF, DOC, DOCX (Max 5MB)', '500d-salesforce'); ?></small>
                </div>

                <div class="sfnl-form-group">
                    <label for="sfnl-job-message" class="sfnl-label">
                        <?php _e('Message', '500d-salesforce'); ?>
                        <span class="sfnl-required">*</span>
                    </label>
                    <textarea
                        id="sfnl-job-message"
                        name="message"
                        class="sfnl-input sfnl-textarea"
                        rows="5"
                        placeholder="<?php esc_attr_e('Tell us why you would be a great fit...', '500d-salesforce'); ?>"
                        required
                    ></textarea>
                </div>

                <div class="sfnl-form-group sfnl-checkbox-group">
                    <label class="sfnl-checkbox-label">
                        <input
                            type="checkbox"
                            id="sfnl-job-terms"
                            name="terms"
                            class="sfnl-checkbox"
                            required
                        />
                        <span><?php _e('I accept the', '500d-salesforce'); ?>
                        <a href="<?php echo esc_url(home_url('/terms-of-use/')); ?>" target="_blank"><?php _e('Terms of Use', '500d-salesforce'); ?></a>
                        <span class="sfnl-required">*</span>
                        </span>
                    </label>
                </div>

                <div class="sfnl-form-group">
                    <button type="submit" class="btn-effect fade-left-right" id="sfnl-job-submit-btn">
                        <span class="sfnl-btn-text"><?php _e('Submit', '500d-salesforce'); ?></span>
                        <span class="sfnl-btn-loading" style="display: none;"><?php _e('Submitting...', '500d-salesforce'); ?></span>
                        <svg>
                            <defs>
                                <linearGradient id="borderGradient-job" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="rgba(243, 108, 33, 0.70)" />
                                    <stop offset="52.51%" stop-color="rgba(243, 108, 33, 0.07)" />
                                    <stop offset="100.99%" stop-color="rgba(243, 108, 33, 0.35)" />
                                </linearGradient>
                            </defs>
                            <rect rx="0" ry="0" />
                        </svg>
                    </button>
                </div>

                <div id="sfnl-job-form-messages" class="sfnl-messages"></div>

                <?php wp_nonce_field('sfnl_job_application_nonce', 'sfnl_job_nonce'); ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle job application form submission via AJAX
     */
    public function handle_job_application_submission() {
        error_log('Job Application: Handler started');

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'sfnl_job_application_nonce')) {
            error_log('Job Application: Nonce verification failed');
            wp_send_json_error(array('message' => __('Security check failed.', '500d-salesforce')));
        }

        // Validate and sanitize inputs
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $page_title = isset($_POST['page_title']) ? sanitize_text_field(wp_unslash($_POST['page_title'])) : '';
        $referral_url = isset($_POST['referral_url']) ? esc_url_raw(wp_unslash($_POST['referral_url'])) : '';

        error_log('Job Application: Data received - Name: ' . $name . ', Email: ' . $email);

        // Validate required fields
        if (empty($name) || empty($email) || empty($phone) || empty($message)) {
            error_log('Job Application: Missing required fields');
            wp_send_json_error(array('message' => __('Please fill in all required fields.', '500d-salesforce')));
        }

        // Validate email with domain check
        if (!$this->validate_email_domain($email)) {
            error_log('Job Application: Invalid email domain');
            wp_send_json_error(array('message' => __('Please enter a valid email address with a valid domain.', '500d-salesforce')));
        }

        // Validate US phone number
        if (!$this->validate_us_phone($phone)) {
            error_log('Job Application: Invalid phone number');
            wp_send_json_error(array('message' => __('Please enter a valid US phone number (e.g., (123) 456-7890 or 123-456-7890).', '500d-salesforce')));
        }

        // Handle file upload
        error_log('Job Application: Checking file upload - isset: ' . (isset($_FILES['cv']) ? 'yes' : 'no'));
        if (isset($_FILES['cv'])) {
            error_log('Job Application: File error code: ' . $_FILES['cv']['error']);
            error_log('Job Application: File name: ' . $_FILES['cv']['name']);
            error_log('Job Application: File size: ' . $_FILES['cv']['size']);
        }

        if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
            $error_msg = 'Please upload a valid CV/Resume file.';
            if (isset($_FILES['cv']['error'])) {
                $error_msg .= ' (Error code: ' . $_FILES['cv']['error'] . ')';
            }
            error_log('Job Application: File upload validation failed');
            wp_send_json_error(array('message' => __($error_msg, '500d-salesforce')));
        }

        // Validate file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($_FILES['cv']['size'] > $max_size) {
            wp_send_json_error(array('message' => __('File size must be less than 5MB.', '500d-salesforce')));
        }

        // Validate file type
        $allowed_types = array('pdf', 'doc', 'docx');
        $file_ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_types)) {
            wp_send_json_error(array('message' => __('Only PDF, DOC, and DOCX files are allowed.', '500d-salesforce')));
        }

        // Generate secure filename with random prefix
        $random_prefix = bin2hex(random_bytes(8)); // Generates 16-character hex string
        $original_filename = sanitize_file_name($_FILES['cv']['name']);
        $secure_filename = $random_prefix . '_' . $original_filename;

        // Prepare file upload
        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => function($dir, $name, $ext) use ($secure_filename) {
                return $secure_filename;
            }
        );

        // Include required WordPress files
        require_once(ABSPATH . 'wp-admin/includes/file.php');

        // Upload file to WordPress media library
        $uploaded_file = wp_handle_upload($_FILES['cv'], $upload_overrides);

        if (isset($uploaded_file['error'])) {
            wp_send_json_error(array('message' => __('File upload failed: ', '500d-salesforce') . $uploaded_file['error']));
        }

        $cv_url = $uploaded_file['url'];

        // Store submission in database
        $submission_id = $this->store_job_application_submission(array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
            'cv_url' => $cv_url,
            'page_title' => $page_title,
            'referral_url' => $referral_url
        ));

        if (!$submission_id) {
            wp_send_json_error(array('message' => __('Database error. Please try again.', '500d-salesforce')));
        }

        // Submit to Salesforce
        $connector = new SFNL_Salesforce_Connector();
        $sf_result = $connector->create_job_application_lead(array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message,
            'cv_url' => $cv_url,
            'page_title' => $page_title,
            'referral_url' => $referral_url
        ));

        $settings = get_option('sfnl_settings', array());

        if ($sf_result['success']) {
            // Update submission with Salesforce ID
            $this->update_submission($submission_id, 'success', $sf_result['id'], '');

            wp_send_json_success(array(
                'message' => __('Thank you for your application! We will review it and get back to you soon.', '500d-salesforce')
            ));
        } else {
            // Update submission with error
            $error_message = $sf_result['error'] ?? __('Unknown error', '500d-salesforce');
            $this->update_submission($submission_id, 'failed', '', $error_message);

            wp_send_json_error(array(
                'message' => $settings['error_message'] ?? __('There was an error. Please try again.', '500d-salesforce')
            ));
        }
    }

    /**
     * Store job application form submission in database
     */
    private function store_job_application_submission($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sfnl_submissions';

        $result = $wpdb->insert(
            $table_name,
            array(
                'form_type' => 'job_application',
                'email' => $data['email'],
                'phone' => $data['phone'],
                'message' => $data['message'],
                'cv_url' => $data['cv_url'],
                'page_title' => $data['page_title'],
                'referral_url' => $data['referral_url'],
                'submission_date' => current_time('mysql'),
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (!$result) {
            error_log('SFNL Job Application Form DB Error: ' . $wpdb->last_error);
            error_log('SFNL Job Application Form DB Query: ' . $wpdb->last_query);
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Validate US phone number format
     * Accepts: (123) 456-7890, 123-456-7890, 123.456.7890, 1234567890, +1 123 456 7890
     */
    private function validate_us_phone($phone) {
        // Remove all non-digit characters
        $cleaned = preg_replace('/\D/', '', $phone);

        // Valid formats:
        // 10 digits: 1234567890
        // 11 digits starting with 1: 11234567890
        if (strlen($cleaned) === 10) {
            return true;
        } elseif (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '1') {
            return true;
        }

        return false;
    }

    /**
     * Validate email with proper domain check
     */
    private function validate_email_domain($email) {
        // First check if it's a valid email format
        if (!is_email($email)) {
            return false;
        }

        // Check for valid TLD (at least 2 characters after the last dot)
        if (!preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
            return false;
        }

        // Check for common typos in popular domains
        $domain = strtolower(substr(strrchr($email, "@"), 1));
        $common_typos = array(
            'gmial.com' => 'gmail.com',
            'gmai.com' => 'gmail.com',
            'gmil.com' => 'gmail.com',
            'yahooo.com' => 'yahoo.com',
            'yaho.com' => 'yahoo.com',
            'hotmial.com' => 'hotmail.com',
            'outlok.com' => 'outlook.com'
        );

        if (isset($common_typos[$domain])) {
            return false; // Invalid - common typo detected
        }

        return true;
    }
}