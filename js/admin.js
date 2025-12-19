jQuery(document).ready(function ($) {
	function getSavedListId() {
		return (
			$('#mailchimp_list_id').val() ||
			$('#woocommerce_mailchimp-tags_list_id').val() ||
			$('input[name="woocommerce_mailchimp-tags_list_id"]').val() ||
			''
		);
	}

	const initialListId = getSavedListId();

	/**
	 * Load audiences from Mailchimp API.
	 *
	 * @param {Object}   options            - Configuration options.
	 * @param {boolean}  options.showStatus - Whether to show connection status message.
	 * @param {Function} options.onComplete - Callback function when request completes.
	 */
	function loadAudiences(options) {
		const settings = $.extend(
			{
				showStatus: false,
				onComplete: null,
			},
			options
		);

		const apiKey = $('#mailchimp_api_key').val();
		const savedListId = initialListId || getSavedListId();

		if (!apiKey) {
			if (settings.onComplete) {
				settings.onComplete();
			}
			return;
		}

		const listContainer = $('#mctwc_list_container_main');

		listContainer.html(
			'<span class="spinner is-active" style="float: none;"></span> ' +
				mctwc.loading_text
		);

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
					// Only show status message when explicitly requested (button click)
					if (settings.showStatus) {
						$('#mctwc_api_status').html(
							'<span style="color: #00a32a; margin-left: 10px;">&#10003; Connected</span>'
						);
					}

					const fieldName = 'woocommerce_mailchimp-tags_list_id';

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
						const $option = $('<option>', {
							value: audience.id,
							text: audience.name,
						});

						// Pre-select if this matches the saved value
						if (savedListId && audience.id === savedListId) {
							$option.prop('selected', true);
						}

						$select.append($option);
					});

					listContainer.empty().append($select);
				} else {
					// Always show error messages so users know something went wrong
					$('#mctwc_api_status').html(
						'<span style="color: #d63638; margin-left: 10px;">&#10007; ' +
							response.data.message +
							'</span>'
					);
					listContainer
						.empty()
						.append(
							$('<input>', {
								type: 'text',
								name: 'woocommerce_mailchimp-tags_list_id',
								id: 'mailchimp_list_id',
								value: savedListId,
								class: 'regular-text',
							})
						)
						.append(
							'<p class="description">Enter your Mailchimp audience/list ID or verify your API key to see a dropdown.</p>'
						);
				}
			},
			error() {
				$('#mctwc_api_status').html(
					'<span style="color: #d63638; margin-left: 10px;">&#10007; Connection failed</span>'
				);
				listContainer
					.empty()
					.append(
						$('<input>', {
							type: 'text',
							name: 'woocommerce_mailchimp-tags_list_id',
							id: 'mailchimp_list_id',
							value: savedListId,
							class: 'regular-text',
						})
					)
					.append(
						'<p class="description">Enter your Mailchimp audience/list ID or verify your API key to see a dropdown.</p>'
					);
			},
			complete() {
				if (settings.onComplete) {
					settings.onComplete();
				}
			},
		});
	}

	// Auto-load audiences on page load if API key exists (without status message)
	if ($('#mailchimp_api_key').val()) {
		loadAudiences({ showStatus: false });
	}

	// Button click handler - shows status message
	$('#mctwc_verify_api').on('click', function (e) {
		e.preventDefault();
		const apiKey = $('#mailchimp_api_key').val();

		if (!apiKey) {
			$('#mctwc_api_status').html(
				'<span style="color: #d63638; margin-left: 10px;">Please enter an API key first.</span>'
			);
			return;
		}

		const $button = $(this);
		$button.prop('disabled', true).text(mctwc.verifying_text);
		$('#mctwc_api_status').html('');

		loadAudiences({
			showStatus: true,
			onComplete() {
				$button.prop('disabled', false).text(mctwc.button_text);
			},
		});
	});
});
