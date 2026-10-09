jQuery(document).ready(function($) {
    // Test Salesforce connection
    $('#test-sf-connection').on('click', function() {
        var $button = $(this);
        var $result = $('#connection-result');

        $button.prop('disabled', true).text('Testing...');
        $result.html('');

        $.ajax({
            url: sfnl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sfnl_test_connection',
                nonce: sfnl_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Connection test failed. Please check your settings.</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Salesforce Connection');
            }
        });
    });

    // Export settings
    $('#sfnl-export-form').on('submit', function(e) {
        e.preventDefault();

        var nonce = $(this).find('input[name="nonce"]').val();

        // Create a form and submit it to trigger download
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = sfnl_ajax.ajax_url;

        var actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'sfnl_export_settings';
        form.appendChild(actionInput);

        var nonceInput = document.createElement('input');
        nonceInput.type = 'hidden';
        nonceInput.name = 'nonce';
        nonceInput.value = nonce;
        form.appendChild(nonceInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    // Enable import button when file is selected
    $('#import-file-input').on('change', function() {
        var $btn = $('#import-settings-btn');
        if (this.files && this.files.length > 0) {
            $btn.prop('disabled', false);
        } else {
            $btn.prop('disabled', true);
        }
    });

    // Import settings
    $('#sfnl-import-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $('#import-settings-btn');
        var $result = $('#import-result');
        var fileInput = document.getElementById('import-file-input');

        if (!fileInput.files || fileInput.files.length === 0) {
            $result.html('<div class="notice notice-error"><p>Please select a file to import.</p></div>');
            return;
        }

        $button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align: middle; animation: rotation 2s infinite linear;"></span> Importing...');
        $result.html('');

        var formData = new FormData();
        formData.append('action', 'sfnl_import_settings');
        formData.append('nonce', sfnl_ajax.nonce);
        formData.append('import_file', fileInput.files[0]);

        $.ajax({
            url: sfnl_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    // Reload page after 2 seconds to show new settings
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p>Error: ' + response.data.message + '</p></div>');
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Import Settings');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Import failed. Please try again.</p></div>');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Import Settings');
            }
        });
    });

    // Add rotation animation for loading spinner
    $('<style>.dashicons.dashicons-update { animation: rotation 2s infinite linear; } @keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }</style>').appendTo('head');

    // Toggle password visibility
    $('.sfnl-toggle-password').on('click', function() {
        var $icon = $(this);
        var targetId = $icon.data('target');
        var $input = $('#' + targetId);

        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });
});