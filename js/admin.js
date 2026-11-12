jQuery(document).ready(function ($) {
	const savedListId =
		$('#mctwc_saved_list_id').val() || $('#mailchimp_list_id').val() || '';

	/**
	 * Show the disabled/locked state for the audience field.
	 *
	 * @param {string} message - Message to display in the disabled field.
	 */
	function showDisabledState(message) {
		const listContainer = $('#mctwc_list_container_main');
		const displayMessage = message || mctwc.verify_first_text;

		listContainer.empty().append(
			$('<select>', {
				id: 'mailchimp_list_id',
				class: 'regular-text',
				disabled: true,
			}).append(
				$('<option>', {
					value: '',
					text: displayMessage,
				})
			)
		);

		// Keep the saved value in a hidden field so it's not lost on save
		if (savedListId && !$('#mctwc_saved_list_id').length) {
			listContainer.append(
				$('<input>', {
					type: 'hidden',
					name: 'woocommerce_mailchimp-tags_list_id',
					id: 'mctwc_saved_list_id',
					value: savedListId,
				})
			);
		}
	}

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

		if (!apiKey) {
			showDisabledState();
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
					if (settings.showStatus) {
						$('#mctwc_api_status').html(
							'<span style="color: #00a32a; margin-left: 10px;">&#10003; Connected</span>'
						);
					}

					const fieldName = 'woocommerce_mailchimp-tags_list_id';

					const $select = $('<select>', {
						name: fieldName,
						id: 'mailchimp_list_id',
						class: 'regular-text',
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

						if (savedListId && audience.id === savedListId) {
							$option.prop('selected', true);
						}

						$select.append($option);
					});

					// Remove hidden field since we now have a working select
					$('#mctwc_saved_list_id').remove();

					listContainer.empty().append($select);
				} else {
					$('#mctwc_api_status').html(
						'<span style="color: #d63638; margin-left: 10px;">&#10007; ' +
							response.data.message +
							'</span>'
					);
					showDisabledState(mctwc.verify_first_text);
				}
			},
			error() {
				$('#mctwc_api_status').html(
					'<span style="color: #d63638; margin-left: 10px;">&#10007; Connection failed</span>'
				);
				showDisabledState(mctwc.verify_first_text);
			},
			complete() {
				if (settings.onComplete) {
					settings.onComplete();
				}
			},
		});
	}

	// On page load: if API key exists, try to load audiences; otherwise show disabled state
	if ($('#mailchimp_api_key').val()) {
		loadAudiences({ showStatus: false });
	} else {
		showDisabledState();
	}

	// Button click handler
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
