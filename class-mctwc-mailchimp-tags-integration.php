<?php
/**
 * Creates Settings page under WooCommerce > Settings > Integrations
 *
 * @package PurchaseTagger
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
	$integrations[] = 'MCTWC_Mailchimp_Tags_Integration';
	return $integrations;
}
add_filter( 'woocommerce_integrations', 'mctwc_add_integration' );

/**
 * Load the Mailchimp Tags integration class.
 *
 * This function initializes the MCTWC_Mailchimp_Tags_Integration class which provides
 * the settings interface under WooCommerce > Settings > Integrations.
 * It only loads when WooCommerce is active and the WC_Integration class exists.
 *
 * @since 1.0.0
 * @return void Early return if WooCommerce is not active.
 */
function mctwc_load_integration_class() {
	if ( ! class_exists('WC_Integration') ) {
		return;
	}
	/**
	 * Product Tagger - Woocommerce to Mailchimp Integration Class.
	 *
	 * Adds a WooCommerce integration settings page for configuring Mailchimp API credentials and audience selection.
	 *
	 * @since 1.0.0
	 */
	class MCTWC_Mailchimp_Tags_Integration extends WC_Integration {
		/**
		 * Constructor.
		 *
		 * Initializes the integration settings, form fields, and hooks.
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			$this->id                 = 'mailchimp-tags';
			$this->method_title       = __('Product Tags for Mailchimp', 'purchase-tagger-for-mailchimp');
			$this->method_description = __('Configure the settings for Mailchimp audience tagging here.', 'purchase-tagger-for-mailchimp');
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
				'api_key'    => array(
					'title'       => __('Mailchimp API Key', 'purchase-tagger-for-mailchimp'),
					'type'        => 'password',
					'description' => __( 'Found in Mailchimp under Profile &rarr; Extras &rarr; API keys.', 'purchase-tagger-for-mailchimp' ) . ' <a href="https://mailchimp.com/help/about-api-keys/" target="_blank">' . __( 'Learn more', 'purchase-tagger-for-mailchimp' ) . '</a>',
					'default'     => '',
					'id'          => 'mailchimp_api_key',
				),
				'list_id'    => array(
					'title'       => __('Mailchimp Audience', 'purchase-tagger-for-mailchimp'),
					'type'        => 'text',
					'description' => __('Enter your Mailchimp audience/list ID or verify your API key to see a dropdown.', 'purchase-tagger-for-mailchimp'),
					'default'     => '',
					'id'          => 'mailchimp_list_id',
					'class'       => 'regular-text',
				),
				'global_tag' => array(
					'title'       => __( 'Global Tag', 'purchase-tagger-for-mailchimp' ),
					'type'        => 'text',
					'description' => __( 'Optional. This tag will be applied to all purchases, in addition to any product-specific tags.', 'purchase-tagger-for-mailchimp' ),
					'default'     => '',
					'id'          => 'mailchimp_global_tag',
					'class'       => 'regular-text',
					'placeholder' => __( 'e.g., Customer', 'purchase-tagger-for-mailchimp' ),
				),
			);
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
			$field_key = $this->get_field_key( $key );
			$value     = $this->get_option( $key );

			ob_start();
			?>
	<tr valign="top">
		<th scope="row" class="titledesc">
			<label for="<?php echo esc_attr( $data['id'] ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
		</th>
		<td class="forminp">
			<input type="password" 
				name="<?php echo esc_attr( $field_key ); ?>" 
				id="<?php echo esc_attr( $data['id'] ); ?>" 
				value="<?php echo esc_attr( $value ); ?>" 
				class="regular-text" />
			<button type="button" id="mctwc_verify_api" class="button button-secondary">
				<?php esc_html_e( 'Verify & Load Audiences', 'purchase-tagger-for-mailchimp' ); ?>
			</button>
			<span id="mctwc_api_status"></span>
			<p class="description"><?php echo wp_kses_post( $data['description'] ); ?></p>
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
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameters for conditional script loading only.
			$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameters for conditional script loading only.
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
				'button_text'    => __( 'Verify & Load Audiences', 'purchase-tagger-for-mailchimp' ),
				'verifying_text' => __( 'Verifying...', 'purchase-tagger-for-mailchimp' ),
				'loading_text'   => __( 'Loading audiences...', 'purchase-tagger-for-mailchimp' ),
				'error_text'     => __( 'Error loading audiences. Please check your API key and try again.', 'purchase-tagger-for-mailchimp' ),
			)
			);
		}
	}
}
add_action( 'plugins_loaded', 'mctwc_load_integration_class', 11 );

/**
 * AJAX handler to verify Mailchimp API key and retrieve audience lists.
 *
 * @since 1.0.0
 * @return void Outputs JSON response and exits.
 */
function mctwc_ajax_get_mailchimp_lists() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
	}

	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mctwc_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Security check failed' ), 403 );
	}

	$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
	if ( empty( $api_key ) ) {
		wp_send_json_error( array( 'message' => 'API key is required' ) );
	}

	$autoload_path = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
	if ( ! file_exists( $autoload_path ) ) {
		wp_send_json_error( array( 'message' => 'Mailchimp API dependencies not installed' ) );
	}

	require_once $autoload_path;

	try {
		$mailchimp = new \DrewM\MailChimp\MailChimp( $api_key );

		$mailchimp->get( 'ping' );
		if ( ! $mailchimp->success() ) {
			wp_send_json_error( array( 'message' => 'Invalid API key or connection failed: ' . $mailchimp->getLastError() ) );
		}

		$result = $mailchimp->get( 'lists' );

		if ( ! $mailchimp->success() ) {
			wp_send_json_error( array( 'message' => $mailchimp->getLastError() ) );
		}

		if ( empty( $result['lists'] ) ) {
			wp_send_json_error( array( 'message' => 'No audiences found in your Mailchimp account.' ) );
		}

		$lists = array_map(
			function ( $audience ) {
				return array(
					'id'   => $audience['id'],
					'name' => $audience['name'],
				);
			},
			$result['lists']
		);

		wp_send_json_success( array( 'lists' => $lists ) );

	} catch ( Exception $e ) {
		mctwc_log( 'Mailchimp API error: ' . $e->getMessage(), 'error' );
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_mctwc_get_mailchimp_lists', 'mctwc_ajax_get_mailchimp_lists' );