jQuery(window).on('load', function() {
    var $ = jQuery;
    console.log('Salesforce plugin JavaScript loaded');

    // Lead Generation Modal Management
    var $modal = $('#sfnl-leadgen-modal');
    if ($modal.length > 0) {
        var postId = $modal.data('post-id');
        var whitepaperUrl = $modal.data('whitepaper-url');
        var storageKey = 'sfnl_leadgen_completed_' + postId;

        // Capture and store the referrer URL (where the user came from)
        var referrerUrl = document.referrer;
        if (referrerUrl) {
            // Update the close button href with the referrer URL
            $('.sfnl-modal-close').attr('href', referrerUrl);
        } else {
            // If no referrer, fallback to /resources
            $('.sfnl-modal-close').attr('href', '/resources');
        }

        // Check if form was already completed for this post
        var isCompleted = localStorage.getItem(storageKey);

        if (!isCompleted) {
            // Show modal
            $modal.addClass('sfnl-modal-active');
            $('body').addClass('sfnl-modal-open');

            // Prevent closing modal with ESC key
            $(document).on('keydown.sfnl-modal', function(e) {
                if (e.keyCode === 27) { // ESC key
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });

            // Prevent closing modal by clicking outside
            $modal.on('click', function(e) {
                if (e.target === this) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });

            // Setup lead gen form handler AFTER modal is shown
            setTimeout(function() {
                var $btn = $('#sfnl-leadgen-submit-btn');
                $btn.off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $('#sfnl-leadgen-form').trigger('submit');
                    return false;
                });
            }, 100);
        } else {
            // User already completed the form - don't show modal
            // Allow user to access article content directly
        }
    }

    // Clean URL if it has the success parameter (for better UX)
    if (window.location.search.includes('form=success')) {
        // Remove the success parameter from URL after a few seconds
        setTimeout(function() {
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.delete('form');
                window.history.replaceState({}, document.title, url.toString());
            }
        }, 5000);
    }

    // Intercept button clicks to force submit (fixes Elementor interference)
    $('#sfnl-submit-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#sfnl-newsletter-form').trigger('submit');
    });

    $('#sfnl-contact-submit-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#sfnl-contact-form').trigger('submit');
    });

    $('#sfnl-job-submit-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#sfnl-job-application-form').trigger('submit');
    });

    // Upload file button for job application
    // Using single robust event listener to avoid duplicate triggers
    var $cvBtn = $('#sfnl-job-cv-btn');
    var $cvInput = $('#sfnl-job-cv');
    if ($cvBtn.length && $cvInput.length) {
        console.log('Job application form elements found');

        // Handle button click to open file picker
        $cvBtn.off('click.sfnl').on('click.sfnl', function(e) {
            console.log('Upload button clicked');
            e.preventDefault();
            e.stopImmediatePropagation();
            // Trigger the file input using native DOM method
            $cvInput[0].click();
            return false;
        });

        // Handle file selection to display filename
        $cvInput.off('change.sfnl').on('change.sfnl', function() {
            console.log('File input changed');
            var $filenameDisplay = $('#sfnl-job-cv-filename');
            console.log('Filename display element found:', $filenameDisplay.length);

            var fileName = '';
            if (this.files && this.files.length > 0) {
                fileName = this.files[0].name;
                console.log('File selected:', fileName);
                $filenameDisplay.text(fileName).addClass('sfnl-file-selected');
                console.log('Display element classes:', $filenameDisplay.attr('class'));
                console.log('Display element text:', $filenameDisplay.text());
            } else {
                console.log('No file selected');
                $filenameDisplay.text('').removeClass('sfnl-file-selected');
            }
        });
    }

    // Handle form submission
    $('#sfnl-newsletter-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#sfnl-submit-btn');
        var $messages = $('#sfnl-form-messages');
        var $emailInput = $('#sfnl-email');

        // Clear previous messages
        $messages.empty();

        // Get form data
        var email = $emailInput.val().trim();

        // Basic validation
        if (!email) {
            showMessage('error', 'Please enter your email address.');
            return;
        }

        if (!isValidEmail(email)) {
            showMessage('error', 'Please enter a valid email address with a valid domain.');
            $emailInput.addClass('error');
            return;
        }

        // Remove error styling
        $emailInput.removeClass('error');

        // Show loading state
        $submitBtn.prop('disabled', true).addClass('loading');

        // Submit via AJAX
        $.ajax({
            url: sfnl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sfnl_submit_form',
                email: email,
                nonce: sfnl_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload page with success parameter and anchor
                    var url = new URL(window.location.href);
                    url.searchParams.set('form', 'success');
                    url.hash = 'form';
                    window.location.href = url.toString();
                } else {
                    showMessage('error', response.data.message);
                }
            },
            error: function() {
                showMessage('error', 'Network error. Please check your connection and try again.');
            },
            complete: function() {
                // Reset button state
                $submitBtn.prop('disabled', false).removeClass('loading');
            }
        });
    });

    // Remove error styling when user starts typing
    $('#sfnl-email').on('input', function() {
        $(this).removeClass('error');
    });

    // Helper function to validate email with proper domain
    function isValidEmail(email) {
        // More strict email validation that checks for valid TLD
        var emailRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (!emailRegex.test(email)) {
            return false;
        }

        // Additional check for common typos in popular domains
        var domain = email.split('@')[1].toLowerCase();
        var commonDomains = {
            'gmial.com': 'gmail.com',
            'gmai.com': 'gmail.com',
            'gmil.com': 'gmail.com',
            'yahooo.com': 'yahoo.com',
            'yaho.com': 'yahoo.com',
            'hotmial.com': 'hotmail.com',
            'outlok.com': 'outlook.com'
        };

        if (commonDomains[domain]) {
            return false; // Invalid - common typo detected
        }

        return true;
    }

    // Helper function to validate US phone number
    function isValidUSPhone(phone) {
        // Remove all non-digit characters
        var cleaned = phone.replace(/\D/g, '');

        // Valid formats:
        // 10 digits: 1234567890
        // 11 digits starting with 1: 11234567890
        if (cleaned.length === 10) {
            return true;
        } else if (cleaned.length === 11 && cleaned[0] === '1') {
            return true;
        }

        return false;
    }

    // Helper function to format phone number display error
    function getPhoneErrorMessage() {
        return 'Please enter a valid US phone number (e.g., (123) 456-7890 or 123-456-7890)';
    }

    // Helper function to show messages
    function showMessage(type, message) {
        var $messages = $('#sfnl-form-messages');
        var $messageDiv = $('<div class="sfnl-message ' + type + '">' + message + '</div>');

        $messages.html($messageDiv);

        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $messageDiv.fadeOut();
            }, 5000);
        }

        // Scroll to message if needed
        if ($messages.offset().top < $(window).scrollTop()) {
            $('html, body').animate({
                scrollTop: $messages.offset().top - 20
            }, 300);
        }
    }

    // Handle contact form submission
    $('#sfnl-contact-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#sfnl-contact-submit-btn');
        var $messages = $('#sfnl-contact-form-messages');

        // Clear previous messages
        $messages.empty();

        // Get form data
        var formData = {
            action: 'sfnl_submit_contact_form',
            nonce: sfnl_ajax.contact_nonce,
            first_name: $('#sfnl-first-name').val().trim(),
            last_name: $('#sfnl-last-name').val().trim(),
            email: $('#sfnl-contact-email').val().trim(),
            phone: $('#sfnl-phone').val().trim(),
            topic: $('#sfnl-topic').val(),
            message: $('#sfnl-message').val().trim(),
            page_title: document.title,
            referral_url: window.location.href
        };

        // Validate required fields
        if (!formData.first_name || !formData.last_name || !formData.email ||
            !formData.phone || !formData.topic || !formData.message) {
            showContactMessage('error', 'Please fill in all required fields.');
            return;
        }

        // Validate email
        if (!isValidEmail(formData.email)) {
            showContactMessage('error', 'Please enter a valid email address with a valid domain.');
            return;
        }

        // Validate phone
        if (!isValidUSPhone(formData.phone)) {
            showContactMessage('error', getPhoneErrorMessage());
            return;
        }

        // Check terms checkbox
        if (!$('#sfnl-terms').is(':checked')) {
            showContactMessage('error', 'Please accept the Terms of Use.');
            return;
        }

        // Show loading state
        $submitBtn.prop('disabled', true).addClass('loading');

        // Submit via AJAX
        $.ajax({
            url: sfnl_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Redirect to success page
                    if (response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    }
                } else {
                    showContactMessage('error', response.data.message);
                }
            },
            error: function() {
                showContactMessage('error', 'Network error. Please check your connection and try again.');
            },
            complete: function() {
                // Reset button state
                $submitBtn.prop('disabled', false).removeClass('loading');
            }
        });
    });

    // Helper function to show contact form messages
    function showContactMessage(type, message) {
        var $messages = $('#sfnl-contact-form-messages');
        var $messageDiv = $('<div class="sfnl-message ' + type + '">' + message + '</div>');

        $messages.html($messageDiv);

        // Scroll to message if needed
        if ($messages.offset().top < $(window).scrollTop()) {
            $('html, body').animate({
                scrollTop: $messages.offset().top - 20
            }, 300);
        }
    }

    // Handle lead generation form submission
    $(document).on('submit', '#sfnl-leadgen-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#sfnl-leadgen-submit-btn');
        var $messages = $('#sfnl-leadgen-form-messages');
        var $container = $('#sfnl-leadgen-form-container');
        var target = $container.data('target') || '_self';
        var postId = $container.data('post-id') || 0;

        // Clear previous messages
        $messages.empty();

        // Get form data
        var formData = {
            action: 'sfnl_submit_leadgen_form',
            nonce: sfnl_ajax.leadgen_nonce,
            post_id: postId,
            email: $('#sfnl-leadgen-email').val().trim(),
            company_name: $('#sfnl-company-name').val().trim(),
            first_name: $('#sfnl-leadgen-first-name').val().trim(),
            last_name: $('#sfnl-leadgen-last-name').val().trim(),
            phone: $('#sfnl-leadgen-phone').val().trim(),
            employees: $('#sfnl-leadgen-employees').val(),
            page_title: document.title,
            referral_url: window.location.href
        };

        // Validate required fields
        if (!formData.email || !formData.company_name || !formData.first_name ||
            !formData.last_name || !formData.phone || !formData.employees) {
            showLeadgenMessage('error', 'Please fill in all required fields.');
            return;
        }

        // Validate email
        if (!isValidEmail(formData.email)) {
            showLeadgenMessage('error', 'Please enter a valid email address with a valid domain.');
            return;
        }

        // Validate phone
        if (!isValidUSPhone(formData.phone)) {
            showLeadgenMessage('error', getPhoneErrorMessage());
            return;
        }

        // Check terms checkbox
        if (!$('#sfnl-leadgen-terms').is(':checked')) {
            showLeadgenMessage('error', 'Please accept the Terms of Use.');
            return;
        }

        // Show loading state
        $submitBtn.prop('disabled', true).addClass('loading');

        // Submit via AJAX
        $.ajax({
            url: sfnl_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Mark as completed in localStorage
                    var storageKey = 'sfnl_leadgen_completed_' + postId;
                    localStorage.setItem(storageKey, 'true');

                    // Close modal - multiple methods to ensure it closes
                    var $modal = $('#sfnl-leadgen-modal');
                    $modal.removeClass('sfnl-modal-active');
                    $modal.fadeOut(300); // Animate the fade out
                    $('body').removeClass('sfnl-modal-open');

                    // Remove event listeners
                    $(document).off('keydown.sfnl-modal');
                    $modal.off('click');

                    // Open white paper in new tab if URL is provided
                    if (response.data && response.data.redirect_url) {
                        window.open(response.data.redirect_url, '_blank');
                    }
                } else {
                    showLeadgenMessage('error', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                showLeadgenMessage('error', 'Network error. Please check your connection and try again.');
            },
            complete: function() {
                // Reset button state
                $submitBtn.prop('disabled', false).removeClass('loading');
            }
        });
    });

    // Helper function to show lead gen form messages
    function showLeadgenMessage(type, message) {
        var $messages = $('#sfnl-leadgen-form-messages');
        var $messageDiv = $('<div class="sfnl-message ' + type + '">' + message + '</div>');

        $messages.html($messageDiv);

        // Scroll to message if needed
        if ($messages.offset().top < $(window).scrollTop()) {
            $('html, body').animate({
                scrollTop: $messages.offset().top - 20
            }, 300);
        }
    }

    // Handle job application form submission
    $('#sfnl-job-application-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#sfnl-job-submit-btn');
        var $messages = $('#sfnl-job-form-messages');

        // Clear previous messages
        $messages.empty();

        // Get form data
        var formData = new FormData();
        formData.append('action', 'sfnl_submit_job_application');
        formData.append('nonce', sfnl_ajax.job_application_nonce);
        formData.append('name', $('#sfnl-job-name').val().trim());
        formData.append('email', $('#sfnl-job-email').val().trim());
        formData.append('phone', $('#sfnl-job-phone').val().trim());
        formData.append('message', $('#sfnl-job-message').val().trim());
        formData.append('page_title', document.title);
        formData.append('referral_url', window.location.href);

        // Add CV file
        var cvFile = $('#sfnl-job-cv')[0].files[0];
        if (cvFile) {
            formData.append('cv', cvFile);
        }

        // Validate required fields
        if (!$('#sfnl-job-name').val().trim() || !$('#sfnl-job-email').val().trim() ||
            !$('#sfnl-job-phone').val().trim() || !$('#sfnl-job-message').val().trim() || !cvFile) {
            showJobMessage('error', 'Please fill in all required fields.');
            return;
        }

        // Validate email
        if (!isValidEmail($('#sfnl-job-email').val().trim())) {
            showJobMessage('error', 'Please enter a valid email address with a valid domain.');
            return;
        }

        // Validate phone
        if (!isValidUSPhone($('#sfnl-job-phone').val().trim())) {
            showJobMessage('error', getPhoneErrorMessage());
            return;
        }

        // Check terms checkbox
        if (!$('#sfnl-job-terms').is(':checked')) {
            showJobMessage('error', 'Please accept the Terms of Use.');
            return;
        }

        // Validate file size (5MB max)
        if (cvFile && cvFile.size > 5 * 1024 * 1024) {
            showJobMessage('error', 'File size must be less than 5MB.');
            return;
        }

        // Validate file type
        var allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (cvFile && !allowedTypes.includes(cvFile.type)) {
            showJobMessage('error', 'Only PDF, DOC, and DOCX files are allowed.');
            return;
        }

        // Show loading state
        $submitBtn.prop('disabled', true).addClass('loading');

        // Submit via AJAX with FormData
        $.ajax({
            url: sfnl_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    // Reload page with success parameter and anchor
                    var url = new URL(window.location.href);
                    url.searchParams.set('form', 'success');
                    url.hash = 'form';
                    window.location.href = url.toString();
                } else {
                    console.error('Submission failed:', response.data);
                    showJobMessage('error', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                showJobMessage('error', 'Network error. Please check your connection and try again.');
            },
            complete: function() {
                // Reset button state
                $submitBtn.prop('disabled', false).removeClass('loading');
            }
        });
    });

    // Helper function to show job application form messages
    function showJobMessage(type, message) {
        var $messages = $('#sfnl-job-form-messages');
        var $messageDiv = $('<div class="sfnl-message ' + type + '">' + message + '</div>');

        $messages.html($messageDiv);

        // Scroll to message if needed
        if ($messages.offset().top < $(window).scrollTop()) {
            $('html, body').animate({
                scrollTop: $messages.offset().top - 20
            }, 300);
        }
    }
});