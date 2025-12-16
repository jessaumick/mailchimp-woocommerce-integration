<?php
/**
 * Creates Settings page under WooCommerce > Settings > Integrations
 *
 * @package MailChimpTagsForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the integration to WooCommerce.
 *
 * @since 1.0.0
 * @param array $integrations List of WooCommerce integrations.
 * @return array Updated list of WooCommerce integrations.
 */
function mctwc_add_integration( $integrations ) {
	$integrations[] = 'WC_MCTWC_Integration';
	return $integrations;
}
add_filter( 'woocommerce_integrations', 'mctwc_add_integration' );

/**
 * Load the MailChimp Tags integration class.
 *
 * This function initializes the WC_MCTWC_Integration class which provides
 * the settings interface under WooCommerce > Settings > Integrations.
 * It only loads when WooCommerce is active and the WC_Integration class exists.
 *
 * @since 1.0.0
 * @return void Early return if WooCommerce is not active.
 */
function load_mctwc_integration_class() {
	// Check if WooCommerce is active and WC_Integration exists.
	if ( ! class_exists('WC_Integration') ) {
		return;
	}
	/**
	 * MailChimp Tags for WooCommerce Integration Class.
	 *
	 * Adds a WooCommerce integration settings page for configuring MailChimp API credentials and audience selection.
	 *
	 * @since 1.0.0
	 */
	class WC_MCTWC_Integration extends WC_Integration {
		/**
		 * Constructor.
		 *
		 * Initializes the integration settings, form fields, and hooks.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->id                 = 'mailchimp-tags';
			$this->method_title       = __('MailChimp Tags', 'mctwc');
			$this->method_description = __('Configure the settings for MailChimp audience syncing here.', 'mctwc');

			// Initialize form fields.
			$this->init_form_fields();
			$this->init_settings();

			add_action('woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ));
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}
		/**
		 * Initialize form fields for the integration settings page.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'api_key'       => array(
					'title'       => __('MailChimp API Key', 'mctwc'),
					'type'        => 'password',
					'description' => __('Enter your MailChimp API key.', 'mctwc') . ' <a href="https://mailchimp.com/help/about-api-keys/" target="_blank">' . __('How to generate your API key', 'mctwc') . '</a>',
					'default'     => '',
					'id'          => 'mailchimp_api_key',
				),
				'verify_button' => array(
					'type'        => 'button',
					'title'       => '',
					'description' => __('Verify & Load Audiences', 'mctwc'),
				),
				'list_id'       => array(
					'title'       => __('MailChimp Audience', 'mctwc'),
					'type'        => 'text',
					'description' => __('Enter your MailChimp audience/list ID or verify your API key to see a dropdown', 'mctwc'),
					'default'     => '',
					'id'          => 'mailchimp_list_id',
					'class'       => 'regular-text',
				),
			);
		}

		/**
		 * Generate HTML for custom button field type.
		 *
		 * @since 1.0.0
		 * @param string $key  Field key.
		 * @param array  $data Field data.
		 * @return string HTML output for the button field.
		 */
		public function generate_button_html( $key, $data ) {
			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc"></th>
				<td class="forminp">
					<button type="button" id="mctwc_verify_api" class="button button-secondary">
						<?php echo esc_html( $data['description'] ); ?>
					</button>
					<div id="mctwc_list_container" style="margin-top: 10px;">
						<!-- List dropdown will be inserted here by JavaScript -->
					</div>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		/**
		 * Generate HTML for password input field.
		 *
		 * Used to render the API key field as a masked input for security.
		 *
		 * @since 1.0.0
		 * @param string $key  Field key.
		 * @param array  $data Field data.
		 * @return string HTML output for the password field.
		 */
		public function generate_password_html( $key, $data ) {
			$field_key = $this->get_field_key($key);
			$value     = $this->get_option($key);

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr($data['id']); ?>"><?php echo wp_kses_post($data['title']); ?></label>
				</th>
				<td class="forminp">
					<input type="password" 
						name="<?php echo esc_attr($field_key); ?>" 
						id="<?php echo esc_attr($data['id']); ?>" 
						value="<?php echo esc_attr($value); ?>" 
						class="regular-text" />
					<p class="description"><?php echo wp_kses_post($data['description']); ?></p>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		/**
		 * Generate HTML for text input field.
		 *
		 * Handles the list_id field specially to support dynamic dropdown replacement.
		 *
		 * @since 1.0.0
		 * @param string $key  Field key.
		 * @param array  $data Field data.
		 * @return string HTML output for the text field.
		 */
		public function generate_text_html( $key, $data ) {
			if ( 'list_id' === $key ) {
				// This will be replaced by JavaScript when API is verified.
				$field_key = $this->get_field_key($key);
				$value     = $this->get_option($key);

				ob_start();
				?>
				<tr valign="top" id="list_id_row">
					<th scope="row" class="titledesc">
						<label for="<?php echo esc_attr($data['id']); ?>"><?php echo wp_kses_post($data['title']); ?></label>
					</th>
					<td class="forminp">
						<div id="mctwc_list_container_main">
							<input type="text" 
								name="<?php echo esc_attr($field_key); ?>" 
								id="<?php echo esc_attr($data['id']); ?>"
								value="<?php echo esc_attr($value); ?>" 
								class="regular-text" />
							<p class="description"><?php echo wp_kses_post($data['description']); ?></p>
						</div>
					</td>
				</tr>
				<?php
				return ob_get_clean();
			}

			return parent::generate_text_html($key, $data);
		}

		/**
		 * Enqueue admin scripts for the integration settings page.
		 *
		 * @since 1.0.0
		 * @param string $hook The current admin page hook.
		 * @return void
		 */
		public function enqueue_scripts( $hook ) {
			if ( 'woocommerce_page_wc-settings' !== $hook ) {
				return;
			}

			// Sanitize GET parameters.
			$tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
			$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

			if ( 'integration' !== $tab ) {
				return;
			}

			if ( ! empty( $section ) && $this->id !== $section ) {
				return;
			}

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script(
			'mctwc-admin',
			plugin_dir_url( __FILE__ ) . 'js/admin.js',
			array( 'jquery' ),
			mctwc_get_version(),
			true
			);

			wp_localize_script(
			'mctwc-admin',
			'mctwc',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'mctwc_nonce' ),
				'button_text'    => __( 'Verify & Load Audiences', 'mctwc' ),
				'verifying_text' => __( 'Verifying...', 'mctwc' ),
				'loading_text'   => __( 'Loading audiences...', 'mctwc' ),
				'error_text'     => __( 'Error loading audiences. Please check your API key and try again.', 'mctwc' ),
			)
			);
		}
	}
}
add_action( 'plugins_loaded', 'load_mctwc_integration_class', 11 );
add_action( 'wp_ajax_mctwc_get_mailchimp_lists', 'mctwc_ajax_get_mailchimp_lists' );

/**
 * AJAX handler to verify MailChimp API key and retrieve audience lists.
 *
 * @since 1.0.0
 * @throws Exception When nonce verification fails, API key is invalid, dependencies are missing, or API connection fails.
 * @return void
 */
function mctwc_ajax_get_mailchimp_lists() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	ob_start();

	try {
		// Verify nonce for security.
		if ( ! isset($_POST['nonce']) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mctwc_nonce' ) ) {
			throw new Exception('Security check failed');
		}

		// Check for API key.
		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( empty($api_key) ) {
			throw new Exception('API key is required');
		}

		// Check for autoloader.
		if ( ! file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php') ) {
			throw new Exception('MailChimp API dependencies not installed');
		}

		require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

		$mailchimp = new \DrewM\MailChimp\MailChimp($api_key);

		// Test the API connection first.
		$ping = $mailchimp->get('ping');
		if ( ! $mailchimp->success() ) {
			throw new Exception('Invalid API key or connection failed: ' . $mailchimp->getLastError());
		}

		$result = $mailchimp->get('lists');

		if ( ! $mailchimp->success() ) {
			throw new Exception($mailchimp->getLastError());
		}

		if ( empty($result['lists']) ) {
			throw new Exception('No lists found in your MailChimp account');
		}

		$lists = array_map( function ( $audience ) {
			return array(
				'id'   => $audience['id'],
				'name' => $audience['name'],
			);
		}, $result['lists'] );

		// Clear any previous output.
		ob_clean();

		wp_send_json_success( array( 'lists' => $lists ) );

	} catch ( Exception $e ) {
		mctwc_log('MailChimp API error: ' . $e->getMessage());
		ob_clean();
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
