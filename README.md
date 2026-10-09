# 500d Salesforce Plugin

A comprehensive WordPress plugin that integrates multiple form types with Salesforce for seamless lead management. Supports Newsletter, Contact, Lead Generation, and Job Application forms.

## Features

- **Four Form Types**: Newsletter subscription, Contact form, Lead Generation (with modal), Job Application (with CV upload)
- **Salesforce REST API Integration**: OAuth 2.0 authentication with automatic token management
- **Modal Support**: Lead generation forms display in elegant modal overlays
- **File Uploads**: Job application form with CV/resume upload support (PDF, DOC, DOCX)
- **Custom Fields**: Support for custom Salesforce fields (Topic, Page Title, Referral URL, CV URL, etc.)
- **Macro Support**: Dynamic content replacement (e.g., %page_title%)
- **Submission Tracking**: Database logging of all form submissions with status tracking
- **Responsive Design**: Mobile-friendly forms with modern styling
- **AJAX Submission**: Seamless form submission without page reload
- **Duplicate Prevention**: Automatic duplicate email checking for newsletter forms
- **Admin Dashboard**: View and filter all submissions by form type
- **Import/Export Settings**: Easy backup and migration of plugin configuration

## Installation

1. Upload the plugin files to `/wp-content/plugins/500d-salesforce/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'SF Newsletter' → 'Settings' in your WordPress admin to configure Salesforce credentials
4. Use the shortcodes below to display forms on any page or post

## Salesforce Setup

### Step 1: Create Connected App

1. Log in to your Salesforce org
2. Go to **Setup** → **App Manager** → **New Connected App**
3. Fill in basic information:
   - Connected App Name: WordPress Integration
   - Contact Email: your@email.com
4. Enable **OAuth Settings**
5. Add these **OAuth Scopes**:
   - "Full access (full)"
   - "Perform requests on your behalf at any time (refresh_token, offline_access)"
6. Set **Callback URL** to: `https://yoursite.com` (can be any valid URL)
7. Click **Save** and wait 10 minutes for changes to propagate

### Step 2: Get Credentials

1. From your Connected App, copy:
   - **Consumer Key** → This is your Client ID
   - **Consumer Secret** → This is your Client Secret (click to reveal)
2. Go to your **Profile** → **Settings** → **Reset My Security Token**
3. Check your email for the Security Token
4. Your **Password** is your regular Salesforce login password

### Step 3: Configure Custom Fields (Optional)

The plugin uses these custom Salesforce Lead fields. Create them if needed:

- `Topic__c` (Text) - Contact form topic selection
- `Page_Title__c` (Text) - Tracking: page where form was submitted
- `Referral_URL__c` (URL) - Tracking: referrer URL
- `CV__c` (URL) - Job application CV file URL
- `NumberOfEmployees` (Number) - Standard field, used by leadgen form
- `Country` (Text) - Standard field, used by contact and leadgen forms

Note: If custom fields don't exist, those fields will be skipped during submission.

## Usage

### 1. Newsletter Form

Simple email subscription form.

**Basic:**
```
[sfnl_form]
```

**With custom attributes:**
```
[sfnl_form title="Subscribe to Updates" class="custom-class"]
```

**Attributes:**
- `title` - Form title (default: from plugin settings)
- `class` - Custom CSS class

**Salesforce Mapping:**
- Email → Email
- LastName → "Newsletter Subscriber"
- Company → "Website Subscriber"

---

### 2. Contact Form

Full contact form with topic selection and message field.

**Basic:**
```
[sfnl_contact_form]
```

**With custom attributes:**
```
[sfnl_contact_form title="Get In Touch" class="custom-class"]
```

**Attributes:**
- `title` - Form title
- `class` - Custom CSS class

**Form Fields:**
- First Name, Last Name (required)
- Email, Phone (required)
- Country (dropdown, required)
- Topic (dropdown, required)
- Message (textarea, required)
- Terms of Use checkbox (required)

**Behavior:**
- On success: Redirects to `https://www.impextechnologies.com/contact-us/?form=success#form`
- Allows duplicate email submissions

---

### 3. Lead Generation Form (Modal)

Business lead capture form that opens in a modal overlay and redirects to white paper download.

**Basic:**
```
[sfnl_leadgen_form]
```

**With all attributes:**
```
[sfnl_leadgen_form
    title="Download: %page_title%"
    description="Enter your details to access this resource."
    align="center"
    target="blank"
    class="custom-class"]
```

**Attributes:**
- `title` - Modal title (supports `%page_title%` macro)
- `description` - Description text below title (supports `%page_title%` macro)
- `align` - Text alignment: `left`, `center`, `right` (default: `center`)
- `target` - Redirect behavior: `blank` (new tab) or `self` (same tab) (default: `self`)
- `class` - Custom CSS class

**Macro Support:**
- `%page_title%` - Replaced with current page title
- Example: `title="Download: %page_title%"` → "Download: My Product Guide"

**Form Fields:**
- Company Email (required)
- Company Name (required)
- First Name, Last Name (required)
- Phone Number (required)
- Number of Employees (dropdown, required)
- Country (dropdown, required)

**Behavior:**
- Opens in modal overlay
- On success: Redirects to URL from post meta field `upload_white_paper_file`
- Allows duplicate email submissions

**Setup White Paper Redirect:**
Add custom field to your post/page:
```php
// Field name: upload_white_paper_file
// Value: https://yoursite.com/path/to/whitepaper.pdf
```

---

### 4. Job Application Form

Job application form with CV/resume file upload.

**Basic:**
```
[sfnl_job_application_form]
```

**With custom attributes:**
```
[sfnl_job_application_form title="Apply Now" class="custom-class"]
```

**Attributes:**
- `title` - Form title
- `class` - Custom CSS class

**Form Fields:**
- Name (required)
- Email (required)
- Phone (required)
- CV/Resume File Upload (required) - PDF, DOC, DOCX, max 5MB
- Message (textarea, required)
- Terms of Use checkbox (required)

**File Upload:**
- Accepted formats: PDF, DOC, DOCX
- Max file size: 5MB
- Files uploaded to WordPress media library
- Random 16-character prefix added for security
- File URL stored in Salesforce custom field `CV__c`

**Behavior:**
- Success message displayed after submission
- Allows duplicate email submissions
- CV file accessible from Salesforce Lead record

---

## Admin Features

### Settings Page

**SF Newsletter → Settings**

Configure Salesforce credentials:
- Client ID (Consumer Key)
- Client Secret (Consumer Secret)
- Username (Salesforce email)
- Password (Salesforce password)
- Security Token
- Environment (Production/Sandbox)

**Import/Export Settings:**
- Export: Download settings as JSON file
- Import: Upload JSON file to restore settings

**Test Connection:**
- Verify Salesforce credentials
- Test API connectivity

### Submissions Page

**SF Newsletter → Submissions**

View all form submissions:
- Filter by form type (newsletter, contact, leadgen, job_application)
- View submission details
- Check Salesforce sync status
- See error messages for failed submissions

---

## Database

All submissions stored in `wp_sfnl_submissions` table:

**Columns:**
- `form_type` - Type: newsletter, contact, leadgen, job_application
- `email`, `first_name`, `last_name`, `phone`
- `topic`, `message` - Contact form
- `company_name`, `employees`, `country` - Leadgen form
- `cv_url` - Job application form
- `page_title`, `referral_url` - Tracking fields
- `salesforce_id` - Salesforce Lead ID
- `status` - pending, success, failed
- `error_message` - Error details if failed

---

## Troubleshooting

### Forms Not Submitting

1. Check Salesforce credentials in settings
2. Use "Test Connection" button in admin
3. Enable WordPress debug: `wp config set WP_DEBUG true --raw`
4. Check `wp-content/debug.log` for errors

### Description Not Showing

1. Clear browser cache (Cmd+Shift+R or Ctrl+Shift+R)
2. Clear WordPress cache: `wp cache flush`
3. Reactivate plugin: `wp plugin deactivate 500d-salesforce && wp plugin activate 500d-salesforce`

### Token Expired Errors

Access tokens are cached for 90 minutes. To manually clear:
```bash
wp transient delete sfnl_sf_token
```

### Check Submissions

```bash
# View all submissions
wp db query "SELECT * FROM wp_sfnl_submissions ORDER BY submission_date DESC LIMIT 10"

# Count by form type
wp db query "SELECT form_type, COUNT(*) as count FROM wp_sfnl_submissions GROUP BY form_type"

# View failed submissions
wp db query "SELECT * FROM wp_sfnl_submissions WHERE status = 'failed' ORDER BY submission_date DESC"
```

---

## WP-CLI Commands

```bash
# Plugin management
wp plugin activate 500d-salesforce
wp plugin deactivate 500d-salesforce

# Test shortcodes
wp eval "echo do_shortcode('[sfnl_form]');"
wp eval "echo do_shortcode('[sfnl_leadgen_form title=\"Test\" description=\"Test description\"]');"

# Clear cached token
wp transient delete sfnl_sf_token

# Export database backup
wp db export backup.sql

# View settings
wp db query "SELECT * FROM wp_options WHERE option_name = 'sfnl_settings'"
```

---

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Active internet connection for Salesforce API calls
- Valid Salesforce org with API access
- cURL extension enabled

---

## Support

For issues and feature requests, please contact 500 Designs or visit the plugin documentation.

---

## Changelog

### 1.0.1
- Added title and description support for leadgen form
- Added macro support (%page_title%)
- Added align parameter (left, center, right)
- Improved modal styling

### 1.0.0
- Initial release
- Newsletter, Contact, Lead Generation, and Job Application forms
- Salesforce REST API integration
- Admin dashboard and submission tracking 
