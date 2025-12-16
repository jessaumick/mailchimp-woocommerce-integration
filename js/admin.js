jQuery(document).ready(function ($) {
	$('#mctwc_verify_api').on('click', function (e) {
		e.preventDefault();
		// Get API key.
		const apiKey = $('#mailchimp_api_key').val();
		const listContainer = $('#mctwc_list_container_main');
		if (!apiKey) {
			listContainer.html(
				'<p class="error">Please enter a MailChimp API key first.</p>'
			);
			return;
		}
		// Show loading message
		listContainer.html('<p>' + mctwc.loading_text + '</p>');
		// Make AJAX call to verify API key and fetch lists.
		$.ajax({
			url: mctwc.ajax_url,
			type: 'POST',
			data: {
				action: 'mctwc_get_mailchimp_lists',
				api_key: apiKey,
				nonce: mctwc.nonce,
			},
			success: function (response) {
				if (response.success) {
					// Build the dropdown with the correct field name.
					const fieldName = $('#woocommerce_mailchimp-tags_list_id')
						.length
						? 'woocommerce_mailchimp-tags_list_id'
						: 'mailchimp_list_id';

					let selectHtml =
						'<select name="' +
						fieldName +
						'" id="mailchimp_list_id">';
					selectHtml +=
						'<option value="">-- Select Audience --</option>';

					$.each(response.data.lists, function (index, audience) {
						selectHtml +=
							'<option value="' +
							audience.id +
							'">' +
							audience.name +
							'</option>';
					});

					selectHtml += '</select>';
					selectHtml +=
						'<p class="description">Select your MailChimp audience/list</p>';
					listContainer.html(selectHtml);
				} else {
					listContainer.html(
						'<p class="error">' + response.data.message + '</p>'
					);
				}
			},
			error: function () {
				listContainer.html(
					'<p class="error">' + mctwc.error_text + '</p>'
				);
			},
		});
	});
});
