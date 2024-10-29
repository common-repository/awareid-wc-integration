function validateAndCorrectURL(inputElement) {
    var url = inputElement.val();
    var errorDiv = jQuery('#domain-error');

    // Remove validation classes and hide error message
    inputElement.removeClass('is-invalid');
    errorDiv.hide();

    // Check for https://
    if (!url.startsWith('https://')) {
        inputElement.addClass('is-invalid');
        errorDiv.text('URL must start with https://').show();
        return false;
    }

    // Remove www. and update the input box
    url = url.replace(/www\./, '');

    inputElement.val(url);
    if (!url.endsWith('/')) {
        url += '/';
        inputElement.val(url);
    }

    // Regular expression for URL validation
    var pattern = new RegExp('^(https:\\/\\/)?'+ // protocol
        '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'+ // domain name
        '((\\d{1,3}\\.){3}\\d{1,3}))'+ // OR ip (v4) address
        '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // port and path
        '(\\?[;&a-z\\d%_.~+=-]*)?'+ // query string
        '(\\#[-a-z\\d_]*)?$','i'); // fragment locator

    if (!pattern.test(url)) {
        inputElement.addClass('is-invalid');
        errorDiv.text('Please enter a valid URL.').show();
        return false;
    }

    return true;
}

function validateRealmName(inputElement) {
    var realmName = inputElement.val();
    var errorDiv = jQuery('#realm-error');

    inputElement.removeClass('is-invalid');
    errorDiv.hide();

    if (!realmName.trim()) {
        inputElement.addClass('is-invalid');
        errorDiv.text('Realm name cannot be empty').show();
        return false;
    }

    return true;
}

function toggleDurationFields() {
    var isChecked = jQuery('#awareid_field_checkbox_field').is(':checked');
    jQuery('#awareid_field_duration_number').closest('tr').toggle(isChecked);
    jQuery('#awareid_field_duration_unit').closest('tr').toggle(isChecked);
}

function toggleLicenseField() {
    if (jQuery('#awareid_field_development_field').is(':checked')) {
        jQuery('#awareid_field_development_license').closest('tr').show();
    } else {
        jQuery('#awareid_field_development_license').closest('tr').hide();
    }
}

jQuery(document).ready(function($) {
    jQuery('.awareid-custom-title').html('<i class="fa-solid fa-circle-info text-primary"></i>');
    jQuery('.awareid-custom-title').attr({
        "data-bs-toggle": "tooltip",
        "data-bs-placement": "top",
        "title": "Enable this for development only as this can reveal sensitive information to the frontend, please contact Aware to whitelist your domain for document capture in production."
    });
    jQuery('#awareid_field_checkbox_field').change(function() {
        toggleDurationFields();
    });
    jQuery('#awareid_field_development_field').change(function() {
        toggleLicenseField();
    });
    jQuery('#update-pubkey-btn').click(function() {
        var button = jQuery(this);
        button.prop('disabled', true); // Disable the button to prevent multiple clicks
        var domainInput = $('#awareid_field_awareid_domain').val();
        var realmNameInput = $('#awareid_field_realm_name').val();
        var clientSecretInput = $('#awareid_field_client_secret').val();
        var apikey = $('#awareid_field_apikey').val();
    
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'update_pubkey_action', // The action hook name for wp_ajax_
                domain: domainInput, // Pass the value, not the jQuery object
                realm: realmNameInput, // Pass the value, not the jQuery object
                client_secret: clientSecretInput, // Pass the value, not the jQuery object
                apikey: apikey,
                nonce: awareVerification.update_pubkey_nonce 
            },
            success: function(response) {
                if (response.startsWith('-----BEGIN PUBLIC KEY-----')) {
                    $('#awareid_field_pubkey').val(response);
                }
                else {
                    alert('Public Key Update: ' + response); // Alert or update the DOM with your success message
                }
                button.prop('disabled', false); // Re-enable the button
            }
        });
    });
    toggleDurationFields();
    toggleLicenseField();
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    var ageInput = document.getElementById('awareid_field_minimum_age'); // Replace with your actual input ID
    ageInput.addEventListener('input', function () {
        var value = this.value;
        var errorDiv = document.getElementById('realm-error');
        if (value < 1 || value > 100) {
            this.classList.add('is-invalid');
            errorDiv.style.display = 'block';
        } else {
            this.classList.remove('is-invalid');
            errorDiv.style.display = 'none';
        }
    });
    $(document).on('click', '#test-server-config', function() {
        let $button = $(this);
        let domainInput = $('#awareid_field_awareid_domain');
        let realmNameInput = $('#awareid_field_realm_name');
        let isDomainValid = validateAndCorrectURL(domainInput);
        let isRealmNameValid = validateRealmName(realmNameInput);

        if (!isDomainValid || !isRealmNameValid) return;
        let domain = domainInput.val();
        let realmName = realmNameInput.val();
        let clientSecret = $('#awareid_field_client_secret').val();
        let apikey = $('#awareid_field_apikey').val();
        
        let testUrl = domain + realmName + '/b2c-sample-web/proxy/ping';
        let $spinner = $('#loading-spinner');

        // Show spinner
        $spinner.show();

        // Disable the button while the request is being processed
        $button.prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_awareid_server',
                url: testUrl,
                domain: domain,
                realm_name: realmName,
                client_secret: clientSecret,
                apikey: apikey,
                nonce: awareVerification.test_server_nonce 
            },
            success: function(response) {
                $('#test-result').text(response.data.message);
            },
            error: function() {
                $('#test-result').text('Something went wrong, please check configuration.');
            },
            complete: function() {
                $spinner.hide();
                $button.prop('disabled', false);
            }
        });
    });
});
