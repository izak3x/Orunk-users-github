<?php
/**
 * Template Name: Orunk Logs
 * Template Post Type: page
 */
get_header();

if (!current_user_can('manage_options')) {
    echo '<p>You do not have permission to view this page.</p>';
} else {
    $log_file = WP_CONTENT_DIR . '/debug.log';

    if (!file_exists($log_file)) {
        echo '<p>No debug log found. Please ensure debugging is enabled in <code>wp-config.php</code>.</p>';
        echo '<p>Add the following lines to <code>wp-config.php</code> to enable debugging:</p>';
        echo '<pre>';
        echo "define('WP_DEBUG', true);\n";
        echo "define('WP_DEBUG_LOG', true);\n";
        echo "define('WP_DEBUG_DISPLAY', false);\n";
        echo '</pre>';
    } else {
        $logs = file_get_contents($log_file);
        if ($logs === false) {
            echo '<p>Unable to read the debug log. Please check file permissions.</p>';
        } else {
            ?>

            <div class="orunk-logs">
                <h2>Website Debug Logs</h2>
                <p>Showing logs from <code><?php echo esc_html($log_file); ?></code></p>
                <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; max-height:500px; overflow-y:scroll; font-family:monospace; white-space:pre-wrap;">
                    <?php echo esc_html($logs); ?>
                </div>
                <p><a href="<?php echo esc_url(add_query_arg('clear_logs', '1')); ?>" class="button">Clear Logs</a></p>
            </div>

            <?php
            // Handle clearing logs
            if (isset($_GET['clear_logs']) && $_GET['clear_logs'] == '1') {
                if (file_put_contents($log_file, '') !== false) {
                    echo '<p style="color:green;">Logs cleared successfully.</p>';
                    echo '<script>window.location.href = "' . esc_url(get_permalink()) . '";</script>';
                } else {
                    echo '<p style="color:red;">Failed to clear logs. Please check file permissions.</p>';
                }
            }
        }
    }
}

get_footer();