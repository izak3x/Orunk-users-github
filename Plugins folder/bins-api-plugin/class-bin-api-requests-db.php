<?php
/**
 * Class Bin_API_Requests_DB
 * Handles database operations for the bin_api_requests table.
 */
class Bin_API_Requests_DB {
    private $table_name;
    private $db_version = '1.1';

    public function __construct() {
        // --- Use non-prefixed table name ---
        $this->table_name = 'bin_api_requests';
        // --- End Use non-prefixed table name ---

        add_action('plugins_loaded', array($this, 'ensure_db_table_exists'));
        if (defined('BIN_LOOKUP_API_DIR')) {
             register_activation_hook(BIN_LOOKUP_API_DIR . 'bins-api-plugin.php', array($this, 'on_activation'));
             register_deactivation_hook(BIN_LOOKUP_API_DIR . 'bins-api-plugin.php', array($this, 'on_deactivation'));
        } else { error_log('BIN API DB Error: BIN_LOOKUP_API_DIR constant not defined.'); }
    }

    public function on_activation() { $this->create_or_update_db_table(); }
    public function on_deactivation() { /* Optional cleanup */ }

    public function ensure_db_table_exists() {
        global $wpdb; $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) === $this->table_name;
        if (!$table_exists) { error_log("BIN API DB: Table {$this->table_name} does not exist. Attempting creation."); $this->create_or_update_db_table(); } else { $this->check_db_update(); }
    }

     private function check_db_update() {
         $installed_db_version = get_option('bin_api_db_version', '0.0'); if (version_compare($installed_db_version, $this->db_version, '<')) { error_log("BIN API DB: Database update needed. Running dbDelta."); $this->create_or_update_db_table(); }
     }

    public function create_or_update_db_table() {
        global $wpdb; error_log("BIN API DB: Entering create_or_update_db_table for: {$this->table_name}"); $charset_collate = $wpdb->get_charset_collate(); $installed_db_version = get_option('bin_api_db_version', '0.0'); $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) === $this->table_name;
        $requests_sql = "CREATE TABLE {$this->table_name} ( id bigint(20) NOT NULL AUTO_INCREMENT, api_key varchar(64) NOT NULL, user_id bigint(20) NOT NULL, ip_address varchar(45) NOT NULL, request_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL, status varchar(50) NOT NULL, PRIMARY KEY (id), INDEX idx_api_key (api_key), INDEX idx_request_date (request_date) ) $charset_collate;";
        if (!$table_exists || version_compare($installed_db_version, $this->db_version, '<')) { error_log("BIN API DB: Attempting dbDelta for table {$this->table_name}."); require_once(ABSPATH . 'wp-admin/includes/upgrade.php'); $delta_results = dbDelta($requests_sql); error_log("BIN API DB: dbDelta results for {$this->table_name}: " . print_r($delta_results, true)); $table_exists_after = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)); if ($table_exists_after !== $this->table_name) { $error_message = "dbDelta failed for table {$this->table_name}. DB Error: " . $wpdb->last_error; error_log("BIN API DB: $error_message"); set_transient('bin_api_db_notice', $error_message, 60); } else { error_log("BIN API DB: Table {$this->table_name} OK via dbDelta."); update_option('bin_api_db_version', $this->db_version); set_transient('bin_api_db_notice', "Table {$this->table_name} OK.", 60); } } else { error_log("BIN API DB: Table {$this->table_name} exists and is up-to-date."); }
        add_action('admin_notices', array($this, 'display_db_notice'));
    }

    public function display_db_notice() {
        $notice = get_transient('bin_api_db_notice'); if ($notice) { $notice_class = (strpos($notice, 'failed') !== false || strpos($notice, 'Error') !== false) ? 'notice-error' : 'notice-success'; echo '<div class="notice ' . $notice_class . ' is-dismissible"><p>' . esc_html($notice) . '</p></div>'; delete_transient('bin_api_db_notice'); }
    }

    public function check_table_exists() {
        global $wpdb; return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $this->table_name)) === $this->table_name;
    }

    public function get_request_count_today($api_key) {
        global $wpdb; if (!$this->check_table_exists()) { return 0; } $c = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE api_key = %s AND DATE(request_date) = CURDATE()", $api_key)); return $c !== null ? (int) $c : 0;
    }

    public function log_request($api_key, $user_id, $ip, $status) {
        global $wpdb; if (!$this->check_table_exists()) { return; } $inserted = $wpdb->insert($this->table_name, array('api_key' => $api_key ?: 'N/A', 'user_id' => $user_id ?: 0, 'ip_address' => $ip, 'status' => substr($status, 0, 50), 'request_date' => current_time('mysql')), array('%s', '%d', '%s', '%s', '%s')); if ($inserted === false) { error_log("BIN API DB: Failed log request. Error: " . $wpdb->last_error); }
    }

    public function reset_requests($api_key) {
        global $wpdb; if (!$this->check_table_exists()) { return false; } $r = $wpdb->delete($this->table_name, array('api_key' => $api_key), array('%s')); return $r !== false;
    }

    public function get_request_counts() {
        global $wpdb; $defaults = array('total' => 0, 'today' => 0, 'week' => 0, 'month' => 0); if (!$this->check_table_exists()) { return $defaults; }
        $t = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"); $d = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(request_date) = CURDATE()"); $w = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"); $m = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        return array('total' => $t !== null ? (int) $t : 0, 'today' => $d !== null ? (int) $d : 0, 'week' => $w !== null ? (int) $w : 0, 'month' => $m !== null ? (int) $m : 0);
    }

    public function get_recent_requests($limit = 50) { // Default limit updated here
        global $wpdb; if (!$this->check_table_exists()) { return array(); }
        $users_table = $wpdb->prefix . 'users';
        $sql = "SELECT r.*, u.user_login FROM {$this->table_name} r LEFT JOIN {$users_table} u ON r.user_id = u.ID ORDER BY r.request_date DESC LIMIT %d";
        return $wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A);
    }

    /**
     * Get all request logs.
     * Used for downloading the full log.
     *
     * @return array An array of all log entries.
     */
    public function get_all_requests() { // <--- NEW METHOD ADDED
        global $wpdb;
        if (!$this->check_table_exists()) {
            return array(); // Return empty array if table doesn't exist
        }
        $users_table = $wpdb->prefix . 'users';
        $sql = "SELECT r.id, r.request_date, r.api_key, r.user_id, u.user_login, r.ip_address, r.status
                FROM {$this->table_name} r
                LEFT JOIN {$users_table} u ON r.user_id = u.ID
                ORDER BY r.request_date DESC"; // Order by date descending
        $results = $wpdb->get_results($sql, ARRAY_A);

        if ($wpdb->last_error) {
             error_log("BIN API DB Error fetching all requests: " . $wpdb->last_error);
             return array(); // Return empty array on error
        }
        return $results ? $results : array(); // Ensure an array is returned
    } // <--- END NEW METHOD


    public function get_request_counts_for_key($api_key) {
        global $wpdb; $defaults = array('today' => 0, 'week' => 0, 'month' => 0, 'total' => 0); if (!$this->check_table_exists()) { return $defaults; }
        $t = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE api_key = %s AND DATE(request_date) = CURDATE()", $api_key)); $w = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE api_key = %s AND request_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)", $api_key)); $m = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE api_key = %s AND request_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", $api_key)); $tot = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE api_key = %s", $api_key));
        return array('today' => $t !== null ? (int) $t : 0, 'week' => $w !== null ? (int) $w : 0, 'month' => $m !== null ? (int) $m : 0, 'total' => $tot !== null ? (int) $tot : 0);
    }

    public function get_requests_table_name() { return $this->table_name; }

} // End Class Bin_API_Requests_DB