jQuery(document).ready(function ($) {
	$('#mctwc_verify_api').on('click', function (e) {
		e.preventDefault();
		const apiKey = $('#mailchimp_api_key').val();
		const listContainer = $('#mctwc_list_container_main');
		if (!apiKey) {
			listContainer.html(
				'<p class="error">Please enter a MailChimp API key first.</p>'
			);
			return;
		}
		const $button = $(this);

		// Disable button during request
		$button.prop('disabled', true).text(mctwc.verifying_text);
		listContainer.html($('<p>', { text: mctwc.loading_text }));

		$.ajax({
			url: mctwc.ajax_url,
			type: 'POST',
			data: {
				action: 'mctwc_get_mailchimp_lists',
				api_key: apiKey,
				nonce: mctwc.nonce,
			},
			success(response) {
				if (response.success) {
					const fieldName = $('#woocommerce_mailchimp-tags_list_id')
						.length
						? 'woocommerce_mailchimp-tags_list_id'
						: 'mailchimp_list_id';

					// jQuery escapes audience.name automatically.
					const $select = $('<select>', {
						name: fieldName,
						id: 'mailchimp_list_id',
					});

					$select.append(
						$('<option>', {
							value: '',
							text: '-- Select Audience --',
						})
					);

					$.each(response.data.lists, function (index, audience) {
						$select.append(
							$('<option>', {
								value: audience.id,
								text: audience.name,
							})
						);
					});

					listContainer
						.empty()
						.append($select)
						.append(
							'<p class="description">Select your MailChimp audience</p>'
						);
				} else {
					listContainer.html(
						$('<p>', {
							class: 'error',
							text: response.data.message,
						})
					);
				}
			},
			error() {
				listContainer.html(
					$('<p>', { class: 'error', text: mctwc.error_text })
				);
			},
			complete() {
				// Re-enable button regardless of success/failure.
				$button.prop('disabled', false).text(mctwc.button_text);
			},
		});
	});
});
