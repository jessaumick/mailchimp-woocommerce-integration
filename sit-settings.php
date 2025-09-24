<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add integration to WooCommerce
add_filter('woocommerce_integrations', 'add_mctwc_integration');
function add_mctwc_integration($integrations) {
    $integrations[] = 'WC_MCTWC_Integration';
    return $integrations;
}

// Load the integration class only when WooCommerce is loaded
add_action('plugins_loaded', 'load_mctwc_integration_class', 11);
function load_mctwc_integration_class() {
    // Check if WooCommerce is active and WC_Integration exists
    if (!class_exists('WC_Integration')) {
        return;
    }
    
    // Now we can safely define our integration class
    class WC_MCTWC_Integration extends WC_Integration {
    
    public function __construct() {
        $this->id = 'mailchimp-tags';
        $this->method_title = __('MailChimp Tags', 'mctwc-sit');
        $this->method_description = __('Configure the settings for MailChimp audience syncing here.', 'mctwc-sit');
        
        // Initialize form fields
        $this->init_form_fields();
        $this->init_settings();
        
        // Save hook
        add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
        
        // Keep your existing AJAX handler
        add_action('wp_ajax_zendo_get_mailchimp_lists', 'zendo_ajax_get_mailchimp_lists');
        
        // Keep your existing scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Migrate old settings on first load
        $this->migrate_settings();
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'api_key' => array(
                'title' => __('MailChimp API Key', 'mctwc-sit'),
                'type' => 'password',
                'description' => __('Enter your MailChimp API key.', 'mctwc-sit') . ' <a href="https://mailchimp.com/help/about-api-keys/" target="_blank">' . __('How to get your API key', 'mctwc-sit') . '</a>',
                'default' => '',
                'id' => 'mailchimp_api_key' // Keep your existing ID for compatibility
            ),
            'verify_button' => array(
                'type' => 'button',
                'title' => '',
                'description' => __('Verify & Load Lists', 'mctwc-sit'),
            ),
            'list_id' => array(
                'title' => __('MailChimp Audience', 'mctwc-sit'),
                'type' => 'text',
                'description' => __('Enter your MailChimp audience/list ID or verify your API key to see a dropdown', 'mctwc-sit'),
                'default' => '',
                'id' => 'mailchimp_list_id', // Keep your existing ID
                'class' => 'regular-text'
            ),
        );
    }
    
    // Custom button field type
    public function generate_button_html($key, $data) {
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"></th>
            <td class="forminp">
                <button type="button" id="zendo_verify_api" class="button button-secondary">
                    <?php echo esc_html($data['description']); ?>
                </button>
                <div id="zendo_list_container" style="margin-top: 10px;">
                    <!-- List dropdown will be inserted here by JavaScript -->
                </div>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
    
    // Override the text input to add custom HTML
    public function generate_password_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $value = $this->get_option($key);
        
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
    
    // Override to handle the list field specially
    public function generate_text_html($key, $data) {
        if ($key === 'list_id') {
            // This will be replaced by JavaScript when API is verified
            $field_key = $this->get_field_key($key);
            $value = $this->get_option($key);
            
            ob_start();
            ?>
            <tr valign="top" id="list_id_row">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($data['id']); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                </th>
                <td class="forminp">
                    <div id="zendo_list_container_main">
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
    
    // Enqueue scripts with minimal changes
    public function enqueue_scripts($hook) {
        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }
        
        if (!isset($_GET['tab']) || 'integration' !== $_GET['tab']) {
            return;
        }
        
        if (isset($_GET['section']) && $this->id !== $_GET['section']) {
            return;
        }
        
        // Keep your existing script enqueue
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'zendo-sit-admin', 
            plugin_dir_url(__FILE__) . 'js/admin.js', 
            array('jquery'), 
            time(),
            true
        );
        
        wp_localize_script('zendo-sit-admin', 'zendo_sit', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zendo_sit_nonce'),
            'loading_text' => 'Loading lists...',
            'error_text' => 'Error loading lists. Please check your API key and try again.'
        ));
    }
    
    // Migrate settings from old location
    private function migrate_settings() {
        // Only migrate if we haven't already
        if (get_option('mctwc_settings_migrated')) {
            return;
        }
        
        // Get old values
        $old_api_key = get_option('mailchimp_api_key');
        $old_list_id = get_option('mailchimp_list_id');
        
        // If we have old values and no new values, migrate them
        if ($old_api_key && !$this->get_option('api_key')) {
            $this->update_option('api_key', $old_api_key);
        }
        
        if ($old_list_id && !$this->get_option('list_id')) {
            $this->update_option('list_id', $old_list_id);
        }
        
        // Mark as migrated
        update_option('mctwc_settings_migrated', '1');
    }
    
    // Override process_admin_options to also update old options for compatibility
    public function process_admin_options() {
        parent::process_admin_options();
        
        // Also update the old options to maintain compatibility with your existing code
        update_option('mailchimp_api_key', $this->get_option('api_key'));
        update_option('mailchimp_list_id', $this->get_option('list_id'));
    }
}
} // End of load_mctwc_integration_class function

// Keep your existing AJAX handler exactly as is
function zendo_ajax_get_mailchimp_lists() {
    // Prevent any output before our JSON response
    ob_start();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    error_log('AJAX handler called: zendo_get_mailchimp_lists');
    
    try {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zendo_sit_nonce')) {
            throw new Exception('Security check failed');
        }
        
        // Check for API key
        $api_key = sanitize_text_field($_POST['api_key']);
        if (empty($api_key)) {
            throw new Exception('API key is required');
        }
        
        // Check for autoloader
        if (!file_exists(plugin_dir_path(__FILE__) . "vendor/autoload.php")) {
            throw new Exception('MailChimp API dependencies not installed');
        }
        
        require_once plugin_dir_path(__FILE__) . "vendor/autoload.php";
        
        $mailchimp = new \DrewM\MailChimp\MailChimp($api_key);
        
        // Test the API connection first
        $ping = $mailchimp->get('ping');
        if (!$mailchimp->success()) {
            throw new Exception('Invalid API key or connection failed: ' . $mailchimp->getLastError());
        }
        
        $result = $mailchimp->get('lists');
        
        if (!$mailchimp->success()) {
            throw new Exception($mailchimp->getLastError());
        }
        
        if (empty($result['lists'])) {
            throw new Exception('No lists found in your MailChimp account');
        }
        
        $lists = array_map(function($list) {
            return array(
                'id' => $list['id'],
                'name' => $list['name']
            );
        }, $result['lists']);
        
        // Clear any previous output
        ob_clean();
        
        wp_send_json_success(array('lists' => $lists));
        
    } catch (Exception $e) {
        error_log('MailChimp exception: ' . $e->getMessage());
        // Clear any previous output
        ob_clean();
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}

// Create the admin.js file if it doesn't exist
function mctwc_create_admin_js() {
    $js_dir = plugin_dir_path(__FILE__) . 'js';
    
    // Create the directory if it doesn't exist
    if (!file_exists($js_dir)) {
        mkdir($js_dir, 0755, true);
    }
    
    $js_file = $js_dir . '/admin.js';
    
    // Only create the file if it doesn't exist
    if (!file_exists($js_file)) {
        $js_content = <<<'EOT'
jQuery(document).ready(function($) {
    $('#zendo_verify_api').on('click', function(e) {
        e.preventDefault();
        
        // Try both possible field IDs (old and new)
        var apiKey = $('#mailchimp_api_key').val() || $('#woocommerce_mailchimp-tags_api_key').val();
        var listContainer = $('#zendo_list_container_main');
        
        if (!apiKey) {
            alert('Please enter a MailChimp API key first.');
            return;
        }
        
        // Show loading message
        listContainer.html('<p>' + zendo_sit.loading_text + '</p>');
        
        // Make AJAX call to verify API key and fetch lists
        $.ajax({
            url: zendo_sit.ajax_url,
            type: 'POST',
            data: {
                action: 'zendo_get_mailchimp_lists',
                api_key: apiKey,
                nonce: zendo_sit.nonce
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
                listContainer.html('<p class="error">' + zendo_sit.error_text + '</p>');
            }
        });
    });
});
EOT;
        file_put_contents($js_file, $js_content);
    }
}

// Initialize JS file
add_action('plugins_loaded', 'mctwc_create_admin_js');