<?php
// Define a function to create the settings page
function zendo_mailchimp_settings_page() {
    ?>
    <div class="wrap">
        <h2>MailChimp Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('zendo_mailchimp_settings'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">MailChimp API Key</th>
                    <td><input type="text" name="zendo_mailchimp_api_key" value="<?php echo esc_attr(get_option('zendo_mailchimp_api_key')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register the settings page
function zendo_mailchimp_settings() {
    // Add a section for our settings
    add_settings_section('zendo_mailchimp_section', 'MailChimp Settings', '', 'zendo-mailchimp-settings');

    // Add a field for the API key
    add_settings_field('zendo_mailchimp_api_key', 'API Key', 'zendo_mailchimp_api_key_callback', 'zendo-mailchimp-settings', 'zendo_mailchimp_section');

    // Register the settings
    register_setting('zendo_mailchimp_settings', 'zendo_mailchimp_api_key');
}

// Callback function for rendering the API key field
function zendo_mailchimp_api_key_callback() {
    $api_key = get_option('zendo_mailchimp_api_key');
    echo "<input type='text' name='zendo_mailchimp_api_key' value='{$api_key}' />";
}

// Add the settings page to the admin menu
function zendo_mailchimp_add_settings_page() {
    add_options_page('MailChimp Settings', 'MailChimp', 'manage_options', 'zendo-mailchimp-settings', 'zendo_mailchimp_settings_page');
}
add_action('admin_menu', 'zendo_mailchimp_add_settings_page');

// Hook into the settings API
add_action('admin_init', 'zendo_mailchimp_settings');

?>