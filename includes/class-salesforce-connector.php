<?php

if (!defined('ABSPATH')) {
    exit;
}

class SFNL_Salesforce_Connector {

    private $client_id;
    private $client_secret;
    private $username;
    private $password;
    private $security_token;
    private $environment;
    private $access_token;
    private $instance_url;

    public function __construct() {
        $settings = get_option('sfnl_settings', array());
        $this->client_id = $settings['sf_client_id'] ?? '';
        $this->client_secret = $settings['sf_client_secret'] ?? '';
        $this->username = $settings['sf_username'] ?? '';
        $this->password = $settings['sf_password'] ?? '';
        $this->security_token = $settings['sf_security_token'] ?? '';
        $this->environment = $settings['sf_environment'] ?? 'production';
    }

    /**
     * Authenticate with Salesforce and get access token
     */
    public function authenticate() {
        // Check if we have a cached token
        $cached_token = get_transient('sfnl_sf_token');
        if ($cached_token && isset($cached_token['access_token'])) {
            $this->access_token = $cached_token['access_token'];
            $this->instance_url = $cached_token['instance_url'];
            return true;
        }

        $login_url = $this->environment === 'sandbox'
            ? 'https://test.salesforce.com/services/oauth2/token'
            : 'https://login.salesforce.com/services/oauth2/token';

        $params = array(
            'grant_type' => 'password',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'username' => $this->username,
            'password' => $this->password . $this->security_token
        );

        $response = wp_remote_post($login_url, array(
            'body' => $params,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            error_log('Salesforce authentication error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $this->instance_url = $data['instance_url'];

            // Cache the token for 1.5 hours (tokens expire in 2 hours)
            set_transient('sfnl_sf_token', array(
                'access_token' => $this->access_token,
                'instance_url' => $this->instance_url
            ), 5400);

            return true;
        } else {
            error_log('Salesforce authentication failed: ' . $body);
            return false;
        }
    }

    /**
     * Create a lead in Salesforce (newsletter form)
     */
    public function create_lead($email) {
        if (!$this->authenticate()) {
            return array('success' => false, 'error' => 'Authentication failed');
        }

        $lead_data = array(
            'Email' => sanitize_email($email),
            'LastName' => 'Newsletter Subscriber',
            'Company' => 'Website Subscriber',
            'LeadSource' => 'Website Form'
        );

        $url = $this->instance_url . '/services/data/v58.0/sobjects/Lead/';

        $response = wp_remote_post($url, array(
            'body' => json_encode($lead_data),
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 201 && isset($data['id'])) {
            return array('success' => true, 'id' => $data['id']);
        } else {
            $error_message = isset($data['message']) ? $data['message'] : 'Unknown error';
            return array('success' => false, 'error' => $error_message);
        }
    }

    /**
     * Create a lead in Salesforce (contact form)
     */
    public function create_contact_lead($data) {
        if (!$this->authenticate()) {
            return array('success' => false, 'error' => 'Authentication failed');
        }

        $lead_data = array(
            'FirstName' => sanitize_text_field($data['first_name']),
            'LastName' => sanitize_text_field($data['last_name']),
            'Email' => sanitize_email($data['email']),
            'Phone' => sanitize_text_field($data['phone']),
            'Company' => sanitize_text_field($data['last_name']),
            'LeadSource' => 'Website Contact Form'
        );

        // Add custom fields
        if (!empty($data['message'])) {
            $lead_data['Comments__c'] = sanitize_textarea_field($data['message']);
        }
        if (!empty($data['topic'])) {
            $lead_data['Topic__c'] = sanitize_text_field($data['topic']);
        }
        if (!empty($data['page_title'])) {
            $lead_data['Page_Title__c'] = sanitize_text_field($data['page_title']);
        }
        if (!empty($data['referral_url'])) {
            $lead_data['Referral_URL__c'] = esc_url_raw($data['referral_url']);
        }

        $url = $this->instance_url . '/services/data/v58.0/sobjects/Lead/';

        $response = wp_remote_post($url, array(
            'body' => json_encode($lead_data),
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 201 && isset($data['id'])) {
            return array('success' => true, 'id' => $data['id']);
        } else {
            $error_message = isset($data[0]['message']) ? $data[0]['message'] :
                           (isset($data['message']) ? $data['message'] : 'Unknown error');
            return array('success' => false, 'error' => $error_message);
        }
    }

    /**
     * Test the connection to Salesforce
     */
    public function test_connection() {
        if (!$this->authenticate()) {
            return array('success' => false, 'message' => 'Authentication failed. Please check your credentials.');
        }

        // Test by getting user info
        $url = $this->instance_url . '/services/data/v58.0/chatter/users/me';

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            $user_name = isset($data['name']) ? $data['name'] : 'Unknown User';
            return array('success' => true, 'message' => "Connection successful! Connected as: {$user_name}");
        } else {
            return array('success' => false, 'message' => 'Connection test failed.');
        }
    }

    /**
     * Create a lead in Salesforce (lead generation form)
     */
    public function create_leadgen_lead($data) {
        if (!$this->authenticate()) {
            return array('success' => false, 'error' => 'Authentication failed');
        }

        $lead_data = array(
            'FirstName' => sanitize_text_field($data['first_name']),
            'LastName' => sanitize_text_field($data['last_name']),
            'Email' => sanitize_email($data['email']),
            'Phone' => sanitize_text_field($data['phone']),
            'Company' => sanitize_text_field($data['company_name']),
            'LeadSource' => 'Website Lead Generation',
        );

        // Add Country to Address Information
        if (!empty($data['country'])) {
            $lead_data['Country'] = sanitize_text_field($data['country']);
        }

        // Add Number of Employees
        if (!empty($data['employees'])) {
            $lead_data['NumberOfEmployees'] = absint($data['employees']);
        }

        // Add custom fields for tracking
        if (!empty($data['page_title'])) {
            $lead_data['Page_Title__c'] = sanitize_text_field($data['page_title']);
        }
        if (!empty($data['referral_url'])) {
            $lead_data['Referral_URL__c'] = esc_url_raw($data['referral_url']);
        }

        $url = $this->instance_url . '/services/data/v58.0/sobjects/Lead/';

        $response = wp_remote_post($url, array(
            'body' => json_encode($lead_data),
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 201 && isset($data['id'])) {
            return array('success' => true, 'id' => $data['id']);
        } else {
            $error_message = isset($data[0]['message']) ? $data[0]['message'] :
                           (isset($data['message']) ? $data['message'] : 'Unknown error');
            return array('success' => false, 'error' => $error_message);
        }
    }

    /**
     * Create a lead in Salesforce (job application form)
     */
    public function create_job_application_lead($data) {
        error_log('Salesforce Job Application: Starting...');

        if (!$this->authenticate()) {
            error_log('Salesforce Job Application: Authentication failed');
            return array('success' => false, 'error' => 'Authentication failed');
        }

        error_log('Salesforce Job Application: Authenticated successfully');

        // Split name into first and last
        $name_parts = explode(' ', sanitize_text_field($data['name']), 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : $name_parts[0];

        $lead_data = array(
            'FirstName' => $first_name,
            'LastName' => $last_name,
            'Email' => sanitize_email($data['email']),
            'Phone' => sanitize_text_field($data['phone']),
            'Company' => $last_name,
            'LeadSource' => 'Job Application',
            'Description' => sanitize_textarea_field($data['message'])
        );

        // Add CV URL to custom field
        if (!empty($data['cv_url'])) {
            $lead_data['CV__c'] = esc_url_raw($data['cv_url']);
            error_log('Salesforce Job Application: CV URL: ' . $data['cv_url']);
        }

        // Add custom fields for tracking
        if (!empty($data['page_title'])) {
            $lead_data['Page_Title__c'] = sanitize_text_field($data['page_title']);
        }
        if (!empty($data['referral_url'])) {
            $lead_data['Referral_URL__c'] = esc_url_raw($data['referral_url']);
        }

        error_log('Salesforce Job Application: Lead data prepared: ' . json_encode($lead_data));

        $url = $this->instance_url . '/services/data/v58.0/sobjects/Lead/';

        $response = wp_remote_post($url, array(
            'body' => json_encode($lead_data),
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            error_log('Salesforce Job Application: WP Error: ' . $response->get_error_message());
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        $response_code = wp_remote_retrieve_response_code($response);

        error_log('Salesforce Job Application: Response code: ' . $response_code);
        error_log('Salesforce Job Application: Response body: ' . $body);

        if ($response_code === 201 && isset($data['id'])) {
            error_log('Salesforce Job Application: Success! Lead ID: ' . $data['id']);
            return array('success' => true, 'id' => $data['id']);
        } else {
            $error_message = isset($data[0]['message']) ? $data[0]['message'] :
                           (isset($data['message']) ? $data['message'] : 'Unknown error');
            error_log('Salesforce Job Application: Failed with error: ' . $error_message);
            return array('success' => false, 'error' => $error_message);
        }
    }

    /**
     * Check if all required settings are configured
     */
    public function is_configured() {
        return !empty($this->client_id) &&
               !empty($this->client_secret) &&
               !empty($this->username) &&
               !empty($this->password) &&
               !empty($this->security_token);
    }
}