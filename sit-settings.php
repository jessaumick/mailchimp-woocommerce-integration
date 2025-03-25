<?php
// Hook to add the settings page to the admin menu
add_action('admin_menu', 'zendo_add_settings_page');

function zendo_add_settings_page() {
    add_menu_page(
        'Zendo SIT Settings', // Page title
        'Zendo SIT',       // Menu title
        'manage_options',     // Capability
        'zendo-sit-settings', // Menu slug
        'zendo_render_settings_page', // Callback function
        '',                   // Icon URL (optional)
        100                   // Position in menu
    );
}

// Callback function to render the settings page
function zendo_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Zendo SIT Settings</h1>
        <p>Configure the settings for the Zendo SIT plugin here.</p>
    </div>
    <?php
}
