# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a comprehensive WordPress plugin called "500d Salesforce Newsletter" that integrates multiple form types with Salesforce via REST API. The plugin creates leads in Salesforce for newsletter subscriptions, contact inquiries, lead generation, and job applications through shortcode-powered forms.

## Environment

- **Type**: WordPress Plugin in LocalWP
- **WordPress Root**: `../../../../` (relative to plugin directory)
- **Plugin Slug**: `500d-salesforce`
- **Text Domain**: `500d-salesforce`
- **Database Table**: `wp_sfnl_submissions` (stores all form submissions)

## Development Commands

```bash
# Navigate to WordPress root for WP-CLI commands
cd ../../../../

# Plugin management
wp plugin activate 500d-salesforce
wp plugin deactivate 500d-salesforce
wp plugin list --status=active

# Database operations
wp db query "SELECT * FROM wp_sfnl_submissions ORDER BY submission_date DESC LIMIT 10"
wp db query "SELECT * FROM wp_sfnl_submissions WHERE form_type = 'contact' ORDER BY submission_date DESC LIMIT 10"
wp db query "SELECT form_type, COUNT(*) as count FROM wp_sfnl_submissions GROUP BY form_type"
wp db query "SELECT * FROM wp_options WHERE option_name = 'sfnl_settings'"
wp db export backup.sql

# Debug and monitor
tail -f wp-content/debug.log

# Test shortcode functionality
wp eval "echo do_shortcode('[sfnl_form]');"
wp eval "echo do_shortcode('[sfnl_contact_form]');"
wp eval "echo do_shortcode('[sfnl_leadgen_form]');"
wp eval "echo do_shortcode('[sfnl_job_application_form]');"

# Clear cached Salesforce token (useful after settings change)
wp transient delete sfnl_sf_token

# Manually trigger table creation
wp eval "sfnl_create_tables();"

# Check database table schema
wp db query "DESCRIBE wp_sfnl_submissions"
```

## Plugin Architecture

### Current File Structure (Implemented)
```
500d-salesforce/
├── 500d-salesforce.php     # Main plugin file with activation hooks
├── includes/
│   ├── class-salesforce-connector.php # Salesforce API integration
│   ├── class-admin-settings.php       # Admin interface & settings
│   └── class-form-handler.php         # Form rendering & AJAX processing
├── admin/js/admin.js                  # Admin JavaScript (connection testing)
├── public/
│   ├── css/frontend.css               # Form styling
│   └── js/frontend.js                 # AJAX form submission
└── README.md                          # Installation & setup guide
```

### Core Architecture Components

**1. Four Form Types with Different Purposes**

a. **Newsletter Form** (`[sfnl_form]`)
   - Simple email subscription
   - Creates lead with LastName: "Newsletter Subscriber", Company: "Website Subscriber"
   - AJAX handler: `sfnl_submit_form`
   - Method: `SFNL_Salesforce_Connector::create_lead()`

b. **Contact Form** (`[sfnl_contact_form]`)
   - Full contact form with first name, last name, email, phone, country, topic, message
   - Includes Terms of Use checkbox (hardcoded URL: impextechnologies.com)
   - Custom Salesforce fields: `Topic__c`, `Page_Title__c`, `Referral_URL__c`, `Country`
   - Success redirects to: `https://www.impextechnologies.com/contact-us/?form=success#form`
   - AJAX handler: `sfnl_submit_contact_form`
   - Method: `SFNL_Salesforce_Connector::create_contact_lead()`

c. **Lead Generation Form** (`[sfnl_leadgen_form]`)
   - Business lead capture: company email, company name, first/last name, phone, employees, country
   - Displays in modal overlay with customizable title and description
   - Shortcode attributes:
     - `title` - Modal title (supports `%page_title%` macro)
     - `description` - Description text below title (supports `%page_title%` macro)
     - `align` - Text alignment: `left`, `center` (default), `right`
     - `target` - Redirect behavior: `blank` (new tab) or `self` (same tab, default)
     - `class` - Custom CSS class
   - Macro support: `%page_title%` replaced with current page title
   - Redirects to white paper URL from post meta field `upload_white_paper_file`
   - Custom Salesforce fields: `NumberOfEmployees`, `Page_Title__c`, `Referral_URL__c`, `Country`
   - AJAX handler: `sfnl_submit_leadgen_form`
   - Method: `SFNL_Salesforce_Connector::create_leadgen_lead()`

d. **Job Application Form** (`[sfnl_job_application_form]`)
   - Job application with CV upload: name, email, phone, CV file, message
   - Accepts PDF, DOC, DOCX (max 5MB)
   - File uploaded to WordPress media library with random prefix security
   - Custom Salesforce field: `CV__c` (stores uploaded file URL)
   - Includes Terms of Use checkbox (hardcoded URL: impextechnologies.com)
   - AJAX handler: `sfnl_submit_job_application`
   - Method: `SFNL_Salesforce_Connector::create_job_application_lead()`

**2. Form Submission Flow (All Forms)**
- User submits form via AJAX
- Data validated and sanitized on server
- Stored in `wp_sfnl_submissions` with status: 'pending'
- Sent to Salesforce via REST API
- Success: Updates status to 'success', stores Salesforce ID, shows success message or redirects
- Error: Updates status to 'failed', stores error message, shows error to user
- All submissions tracked in database regardless of Salesforce status

**3. Salesforce Integration Pattern**
- OAuth 2.0 password flow authentication
- Access tokens cached as WordPress transients (90 minutes)
- API endpoint: `/services/data/v58.0/sobjects/Lead/`
- All forms create Salesforce **Leads** (not Contacts)
- Handles sandbox vs production environments via login URL selection
- Custom fields used: `Topic__c`, `Page_Title__c`, `Referral_URL__c`, `CV__c`, `NumberOfEmployees`, `Country`

**4. Admin Interface Structure**
- Main settings page with Salesforce credentials configuration
- Import/Export functionality for settings (JSON format)
- Submissions listing page showing all form types with filtering by form_type
- AJAX connection testing functionality
- Comprehensive Salesforce Connected App setup instructions

### Key Implementation Details

**Database Schema (Unified Table for All Forms)**
```sql
wp_sfnl_submissions:
- id (bigint auto increment, PRIMARY KEY)
- form_type (varchar 20, default 'newsletter', indexed) - Values: 'newsletter', 'contact', 'leadgen', 'job_application'
- email (varchar 255, NOT NULL, indexed)
- first_name (varchar 100)
- last_name (varchar 100)
- phone (varchar 50)
- topic (varchar 100)
- message (text)
- company_name (varchar 255) - Used by leadgen form
- employees (varchar 10) - Used by leadgen form (stores numeric range)
- country (varchar 100) - Used by contact and leadgen forms
- cv_url (varchar 500) - Used by job application form
- page_title (varchar 255) - Tracking field
- referral_url (varchar 500) - Tracking field
- submission_date (datetime, DEFAULT CURRENT_TIMESTAMP, indexed)
- salesforce_id (varchar 50)
- status (varchar 20, DEFAULT 'pending', indexed) - Values: 'pending', 'success', 'failed'
- error_message (text)
```

**WordPress Integration Points**
- Shortcodes:
  - `[sfnl_form]` - Newsletter form (attributes: title, class)
  - `[sfnl_contact_form]` - Contact form (attributes: title, class)
  - `[sfnl_leadgen_form]` - Lead gen form (attributes: title, description, align, target, class)
  - `[sfnl_job_application_form]` - Job application form (attributes: title, class)
- Admin menu: "SF Newsletter" with settings and submissions pages
- AJAX endpoints:
  - `sfnl_submit_form` (newsletter)
  - `sfnl_submit_contact_form` (contact)
  - `sfnl_submit_leadgen_form` (lead gen)
  - `sfnl_submit_job_application` (job application)
  - `sfnl_test_connection` (admin)
  - `sfnl_export_settings` (admin)
  - `sfnl_import_settings` (admin)
- Options: `sfnl_settings` (all plugin configuration)
- Transient: `sfnl_sf_token` (access token cached for 90 minutes)
- Post Meta: `upload_white_paper_file` (used by leadgen form for redirect URL)

## Salesforce Configuration Requirements

The plugin requires these Salesforce credentials (detailed setup in admin):
- Client ID (Consumer Key from Connected App)
- Client Secret (Consumer Secret from Connected App)
- Username (Salesforce login email)
- Password (Salesforce password)
- Security Token (from Profile → Settings → Reset Security Token)
- Environment selection (Production/Sandbox)

**Custom Salesforce Fields Required:**
The plugin uses these custom fields on the Lead object. If they don't exist, those fields will be skipped:
- `Topic__c` (Text) - Contact form topic selection
- `Page_Title__c` (Text) - Tracking: page where form was submitted
- `Referral_URL__c` (URL) - Tracking: referrer URL
- `CV__c` (URL) - Job application CV file URL
- `NumberOfEmployees` (Number) - Standard field, used by leadgen form
- `Country` (Text) - Standard field, used by contact and leadgen forms

## Form Customization & Usage

**Newsletter Form**
```php
[sfnl_form] // Basic form
[sfnl_form title="Subscribe to Updates" class="custom-class"] // With attributes
```
- Success: Configurable message in admin settings
- Error: Configurable message in admin settings
- Duplicate prevention: Checks for existing successful submission with same email

**Contact Form**
```php
[sfnl_contact_form] // Basic form
[sfnl_contact_form title="Get In Touch" class="custom-class"] // With attributes
```
- Success: Redirects to `https://www.impextechnologies.com/contact-us/?form=success#form`
- Error: Shows inline error message
- Required fields: first name, last name, email, phone, country, topic, message, terms checkbox

**Lead Generation Form**
```php
[sfnl_leadgen_form] // Basic form - opens in same tab
[sfnl_leadgen_form title="Download White Paper" target="blank"] // Opens in new tab
[sfnl_leadgen_form
    title="Download: %page_title%"
    description="Enter your details to access this resource."
    align="center"
    target="blank"
    class="custom-class"] // With all attributes
```
- Displays in modal overlay with customizable title and description
- Success: Redirects to URL from post meta field `upload_white_paper_file`
- Attributes:
  - `title` - Modal title (supports `%page_title%` macro to display current page title)
  - `description` - Description text below title (supports `%page_title%` macro)
  - `align` - Text alignment: "left", "center" (default), "right"
  - `target` - Redirect behavior: "blank" for new tab, "self" for same tab (default: "self")
  - `class` - Custom CSS class
- Required fields: company email, company name, first name, last name, phone, employees, country

**Job Application Form**
```php
[sfnl_job_application_form] // Basic form
[sfnl_job_application_form title="Apply Now" class="custom-class"] // With attributes
```
- Success: Configurable message in admin settings
- Error: Configurable message in admin settings
- Required fields: name, email, phone, CV file (PDF/DOC/DOCX, max 5MB), message, terms checkbox
- Files uploaded to WordPress media library with random 16-character prefix for security

## Important Implementation Notes

**Security & Data Handling**
- All settings are sanitized on save via `sanitize_settings()` in class-admin-settings.php:423
- Email validation happens both client-side (JavaScript) and server-side (PHP)
- Nonce verification required for all AJAX requests (separate nonces for each form type)
- Duplicate email submissions prevented only for newsletter form (checks existing success records)
- Contact, leadgen, and job application forms allow duplicate emails
- File uploads (job application) use WordPress `wp_handle_upload()` with validation
- CV files get random 16-character hex prefix for security: `bin2hex(random_bytes(8))`
- Import/Export settings use JSON format with validation
- All user inputs sanitized using WordPress functions: `sanitize_text_field()`, `sanitize_email()`, `sanitize_textarea_field()`, `esc_url_raw()`

**Token Management**
- Salesforce access tokens are cached as WordPress transients for 5400 seconds (90 minutes)
- Tokens expire after 2 hours, cached for 1.5 hours to ensure validity
- Token cache is cleared when settings are changed
- Manual token clearing: `wp transient delete sfnl_sf_token`
- Authentication method: `SFNL_Salesforce_Connector::authenticate()` in class-salesforce-connector.php:31

**Error Logging & Debugging**
- Salesforce authentication errors logged via `error_log()` in class-salesforce-connector.php:61,80
- Lead creation failures logged in class-form-handler.php:156
- Contact form DB errors logged in class-form-handler.php:570-572
- Lead gen form DB errors logged in class-form-handler.php:873-875
- Job application form DB errors logged in class-form-handler.php:1153-1155
- All error logging uses WordPress `error_log()` function
- Check `wp-content/debug.log` when troubleshooting connection issues
- Enable WP_DEBUG via: `wp config set WP_DEBUG true --raw`

**Database Considerations**
- Submissions table created on plugin activation via `sfnl_create_tables()` in 500d-salesforce.php:59
- Uses `dbDelta()` for safe table creation/updates
- Indexes on: `email`, `submission_date`, `status`, `form_type` for performance
- Submission status flow: pending → success/failed
- All forms use single unified table with `form_type` discriminator
- Query pattern: Use `$wpdb->prepare()` for all database queries

**Hardcoded Values to be Aware Of**
- Contact form Terms of Use URL: `https://www.impextechnologies.com/terms-of-use/`
- Contact form success redirect: `https://www.impextechnologies.com/contact-us/?form=success#form`
- Job application Terms of Use URL: `https://www.impextechnologies.com/terms-of-use/`
- Salesforce API version: `v58.0`
- Max CV file size: 5MB (5 * 1024 * 1024 bytes)
- Allowed CV file types: PDF, DOC, DOCX

**Class Methods Architecture**

*SFNL_Salesforce_Connector (includes/class-salesforce-connector.php)*
- `authenticate()` - OAuth 2.0 password flow, caches token
- `create_lead($email)` - Newsletter form lead creation
- `create_contact_lead($data)` - Contact form lead creation
- `create_leadgen_lead($data)` - Lead gen form lead creation
- `create_job_application_lead($data)` - Job application lead creation
- `test_connection()` - Tests Salesforce connection via Chatter API
- `is_configured()` - Checks if all credentials are set

*SFNL_Form_Handler (includes/class-form-handler.php)*
- `render_form($atts)` - Newsletter form HTML
- `render_contact_form($atts)` - Contact form HTML
- `render_leadgen_form($atts)` - Lead gen form HTML (includes modal)
- `render_job_application_form($atts)` - Job application form HTML
- `handle_form_submission()` - Newsletter AJAX handler
- `handle_contact_form_submission()` - Contact AJAX handler
- `handle_leadgen_form_submission()` - Lead gen AJAX handler
- `handle_job_application_submission()` - Job application AJAX handler (includes file upload)
- `email_already_submitted($email)` - Checks for duplicate newsletter submissions
- `store_submission($email)` - Stores newsletter submission
- `store_contact_submission($data)` - Stores contact submission
- `store_leadgen_submission($data)` - Stores leadgen submission
- `store_job_application_submission($data)` - Stores job application submission
- `update_submission($id, $status, $sf_id, $error)` - Updates submission after Salesforce response

*SFNL_Admin_Settings (includes/class-admin-settings.php)*
- `add_admin_menu()` - Creates admin menu pages
- `settings_page()` - Renders settings page with import/export
- `submissions_page()` - Renders submissions listing
- `test_connection()` - AJAX handler for connection test
- `export_settings()` - Exports settings to JSON file
- `import_settings()` - Imports settings from JSON file
- `sanitize_settings($input)` - Sanitizes and validates settings on save