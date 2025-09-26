jQuery(document).ready(function($) {
    $('#mctwc_verify_api').on('click', function(e) {
        e.preventDefault();
        
        // Get API key
        var apiKey = $('#mailchimp_api_key').val();
        var listContainer = $('#mctwc_list_container_main');
        
        if (!apiKey) {
            alert('Please enter a MailChimp API key first.');
            return;
        }
        
        // Show loading message
        listContainer.html('<p>' + mctwc.loading_text + '</p>');
        
        // Make AJAX call to verify API key and fetch lists
        $.ajax({
            url: mctwc.ajax_url,
            type: 'POST',
            data: {
                action: 'mctwc_get_mailchimp_lists',
                api_key: apiKey,
                nonce: mctwc.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Build the dropdown with the correct field name
                    var fieldName = $('#woocommerce_mailchimp-tags_list_id').length ? 
                                   'woocommerce_mailchimp-tags_list_id' : 
                                   'mailchimp_list_id';
                    
                    var selectHtml = '<select name="' + fieldName + '" id="mailchimp_list_id">';
                    selectHtml += '<option value="">-- Select a List --</option>';
                    
                    $.each(response.data.lists, function(index, list) {
                        selectHtml += '<option value="' + list.id + '">' + list.name + '</option>';
                    });
                    
                    selectHtml += '</select>';
                    selectHtml += '<p class="description">Select your MailChimp audience/list</p>';
                    listContainer.html(selectHtml);
                } else {
                    listContainer.html('<p class="error">' + response.data.message + '</p>');
                }
            },
            error: function() {
                listContainer.html('<p class="error">' + mctwc.error_text + '</p>');
            }
        });
    });
});