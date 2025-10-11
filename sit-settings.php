<?php
/**
 * Creates Settings page under WooCommerce > Settings > Integrations
 *
 * @package MailChimpTagsForWooCommerce
 */

if ( ! defined ('ABSPATH') ) {
	exit;
}

/**
 * Add the integration to WooCommerce.
 *
 * @param array $integrations List of WooCommerce integrations.
 * @return array Updated list of WooCommerce integrations.
 */
function add_mctwc_integration( $integrations ) {
	$integrations[] = 'WC_MCTWC_Integration';
	return $integrations;
}
add_filter('woocommerce_integrations', 'add_mctwc_integration');

// Load the integration class only when WooCommerce is loaded.
add_action( 'plugins_loaded', 'load_mctwc_integration_class', 11 );
function load_mctwc_integration_class() {
	// Check if WooCommerce is active and WC_Integration exists.
	if ( ! class_exists('WC_Integration') ) {
		return;
	}

	// Now, we can safely define our integration class.
	class WC_MCTWC_Integration extends WC_Integration {

		public function __construct() {
			$this->id                 = 'mailchimp-tags';
			$this->method_title       = __('MailChimp Tags', 'mctwc');
			$this->method_description = __('Configure the settings for MailChimp audience syncing here.', 'mctwc');

			// Initialize form fields.
			$this->init_form_fields();
			$this->init_settings();

			// Save hook.
			add_action('woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ));

			// Keep your existing AJAX handler.
			add_action( 'wp_ajax_mctwc_get_mailchimp_lists', 'mctwc_ajax_get_mailchimp_lists' );

			// Keep your existing scripts.
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

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

		// Custom button field type.
		public function generate_button_html( $key, $data ) {
			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc"></th>
				<td class="forminp">
					<button type="button" id="mctwc_verify_api" class="button button-secondary">
						<?php echo esc_html($data['description']); ?>
					</button>
					<div id="mctwc_list_container" style="margin-top: 10px;">
						<!-- List dropdown will be inserted here by JavaScript -->
					</div>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		// Override the text input to add custom HTML.
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

		// Override to handle the list field specially.
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

		// Enqueue scripts with minimal changes.
		public function enqueue_scripts( $hook ) {
			if ( 'woocommerce_page_wc-settings' !== $hook ) {
				return;
			}

			if ( ! isset($_GET['tab'] ) || 'integration' !== $_GET['tab'] ) {
				return;
			}

			if ( isset($_GET['section']) && $this->id !== $_GET['section'] ) {
				return;
			}

			// Keep your existing script enqueue.
			wp_enqueue_script('jquery');
			wp_enqueue_script(
				'mctwc-admin',
				plugin_dir_url(__FILE__) . 'js/admin.js',
				array( 'jquery' ),
				time(),
				true
			);

			wp_localize_script('mctwc-admin', 'mctwc', array(
				'ajax_url'     => admin_url('admin-ajax.php'),
				'nonce'        => wp_create_nonce('mctwc_nonce'),
				'loading_text' => 'Loading lists...',
				'error_text'   => 'Error loading lists. Please check your API key and try again.',
			));
		}
	}
}

// AJAX handler for getting MailChimp lists.
function mctwc_ajax_get_mailchimp_lists() {
	ob_start();

	// Set JSON header.
	header('Content-Type: application/json');

	error_log('AJAX handler called: mctwc_get_mailchimp_lists');

	try {
		// Verify nonce for security.
		if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'mctwc_nonce') ) {
			throw new Exception('Security check failed');
		}

		// Check for API key.
		$api_key = sanitize_text_field($_POST['api_key']);
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

		$lists = array_map( function ( $list ) {
			return array(
				'id'   => $list['id'],
				'name' => $list['name'],
			);
		}, $result['lists'] );

		// Clear any previous output.
		ob_clean();

		wp_send_json_success( array( 'lists' => $lists ) );

	} catch ( Exception $e ) {
		error_log('MailChimp exception: ' . $e->getMessage());
		// Clear any previous output.
		ob_clean();
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
