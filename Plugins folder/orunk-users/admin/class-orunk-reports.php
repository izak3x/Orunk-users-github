<?php
/**
 * Orunk Users Reports Admin Class
 *
 * Handles the admin interface for displaying reports related to sales,
 * active users, and potentially API usage.
 *
 * @package OrunkUsers\Admin
 * @version 1.1.6 // Increment version
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Custom_Orunk_Reports {

    /**
     * Initialize admin menu hooks.
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_reports_menu'));
    }

    /**
     * Add the "Reports" submenu page under "Orunk Users".
     */
    public function add_reports_menu() {
        add_submenu_page(
            'orunk-users-manager',                   // Parent slug
            __('Orunk Reports', 'orunk-users'),      // Page title
            __('Reports', 'orunk-users'),            // Menu title
            'manage_options',                       // Capability required
            'orunk-users-reports',                  // Menu slug (unique ID for this page)
            array($this, 'reports_page_html')       // Function to display page content
        );
    }

    /**
     * Fetch sales data.
     * Groups sales by plan and gateway for completed purchases.
     * TODO: Implement date range filtering.
     *
     * @return array Aggregated sales data grouped by plan name.
     */
    private function get_sales_data() {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';

        // Query to get total revenue and count per plan and gateway for 'active' or 'expired' purchases.
        // Excludes 'pending', 'cancelled', 'failed'. Includes 'free_checkout' for counts but not revenue calculation.
        $sql = "
            SELECT
                pl.plan_name,
                p.payment_gateway,
                COUNT(p.id) as sales_count,
                SUM(CASE WHEN p.payment_gateway != 'free_checkout' THEN pl.price ELSE 0 END) as total_revenue
            FROM {$purchases_table} p
            LEFT JOIN {$plans_table} pl ON p.plan_id = pl.id
            WHERE p.status IN ('active', 'expired') -- Only count completed/used purchases for revenue
            GROUP BY p.plan_id, p.payment_gateway
            ORDER BY pl.plan_name, p.payment_gateway;
        ";

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Process results into a more structured array grouped by plan
        $sales_by_plan = [];
        if ($results) {
            foreach ($results as $row) {
                $plan_name = $row['plan_name'] ?? __('Unknown Plan', 'orunk-users');
                $gateway = $row['payment_gateway'] ?? __('Unknown Gateway', 'orunk-users');
                // Initialize array for the plan if it doesn't exist
                if (!isset($sales_by_plan[$plan_name])) {
                    $sales_by_plan[$plan_name] = ['total_sales' => 0, 'total_revenue' => 0.0, 'gateways' => []];
                }
                // Aggregate totals for the plan
                $sales_by_plan[$plan_name]['total_sales'] += (int)$row['sales_count'];
                $sales_by_plan[$plan_name]['total_revenue'] += (float)$row['total_revenue'];
                // Store gateway breakdown
                $sales_by_plan[$plan_name]['gateways'][$gateway] = [
                    'count' => (int)$row['sales_count'],
                    'revenue' => (float)$row['total_revenue']
                ];
            }
        }

        return $sales_by_plan;
    }

    /**
     * Fetch active user counts per plan.
     * Counts distinct users with an active, non-expired purchase for each plan.
     *
     * @return array Associative array [plan_name => active_user_count].
     */
    private function get_active_users_per_plan() {
        global $wpdb;
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';

        // Query to count distinct active users for each plan whose purchase hasn't expired
        $sql = $wpdb->prepare("
            SELECT
                pl.plan_name,
                COUNT(DISTINCT p.user_id) as active_users
            FROM {$purchases_table} p
            JOIN {$plans_table} pl ON p.plan_id = pl.id
            WHERE p.status = 'active'
              AND (p.expiry_date IS NULL OR p.expiry_date >= %s) -- Check expiry against current GMT time
            GROUP BY p.plan_id
            ORDER BY pl.plan_name;
        ", current_time('mysql', 1)); // Use GMT time for comparison

        $results = $wpdb->get_results($sql, ARRAY_A);

        $active_users = [];
        if ($results) {
            foreach ($results as $row) {
                // Ensure plan_name is set before using it as key
                if (!empty($row['plan_name'])) {
                    $active_users[$row['plan_name']] = (int)$row['active_users'];
                } else {
                     // Handle cases where plan might be deleted but purchase exists
                     $active_users[__('Unknown Plan', 'orunk-users')] = isset($active_users[__('Unknown Plan', 'orunk-users')])
                                                                        ? $active_users[__('Unknown Plan', 'orunk-users')] + (int)$row['active_users']
                                                                        : (int)$row['active_users'];
                }
            }
        }
        return $active_users;
    }

    /**
     * Fetch API usage statistics aggregated by plan (Last 30 Days).
     * NOTE: Assumes the existence and structure of the 'bin_api_requests' table
     * from the bins-api-plugin. This join might be slow on large tables.
     *
     * @return array|string[] Aggregated API usage data or an error message.
     */
    private function get_api_usage_by_plan() {
        global $wpdb;
        // Define table names (ensure these are correct - check bins-api-plugin)
        $requests_table = 'bin_api_requests'; // <<< Assumed table name (no prefix)
        $purchases_table = $wpdb->prefix . 'orunk_user_purchases';
        $plans_table = $wpdb->prefix . 'orunk_product_plans';

        // Check if the requests table exists first to avoid errors
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $requests_table)) != $requests_table) {
            return ['error' => sprintf(__('API requests table (%s) not found. Cannot generate usage report.', 'orunk-users'), '<code>' . esc_html($requests_table) . '</code>')];
        }

        // Query: Get total requests per plan in the last 30 days by joining logs with purchases via api_key
        // This assumes api_key in bin_api_requests matches api_key in orunk_user_purchases
        $sql = "
            SELECT
                pl.plan_name,
                COUNT(r.id) as total_requests_30d
            FROM {$requests_table} r
            INNER JOIN {$purchases_table} p ON r.api_key = p.api_key -- Join based on API Key
            INNER JOIN {$plans_table} pl ON p.plan_id = pl.id
            WHERE r.request_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              -- Optional: AND p.status = 'active' -- Only count requests from currently active keys? Might exclude recently expired keys' usage.
            GROUP BY p.plan_id -- Group by plan ID for aggregation
            ORDER BY pl.plan_name;
        ";

        $results = $wpdb->get_results($sql, ARRAY_A);

        // Check for errors after the query
         if ($wpdb->last_error) {
             error_log("Orunk Reports Error fetching API usage: " . $wpdb->last_error);
              return ['error' => __('Database error fetching API usage data.', 'orunk-users')];
         }


        $api_usage = [];
         if ($results) {
             foreach ($results as $row) {
                if (!empty($row['plan_name'])) {
                    $api_usage[$row['plan_name']] = (int)$row['total_requests_30d'];
                } else {
                    // Aggregate usage for purchases linked to deleted plans
                     $api_usage[__('Unknown Plan', 'orunk-users')] = isset($api_usage[__('Unknown Plan', 'orunk-users')])
                                                                    ? $api_usage[__('Unknown Plan', 'orunk-users')] + (int)$row['total_requests_30d']
                                                                    : (int)$row['total_requests_30d'];
                }
             }
         }
        return $api_usage;
    }


    /**
     * Display the HTML content for the Reports page.
     */
    public function reports_page_html() {
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'orunk-users'));
        }

        // Fetch data for reports
        $sales_data = $this->get_sales_data();
        $active_users_data = $this->get_active_users_per_plan();
        $api_usage_data = $this->get_api_usage_by_plan();

        ?>
        <div class="wrap orunk-users-reports-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php esc_html_e('View reports on plan sales, active users, and API usage.', 'orunk-users'); ?></p>

            <?php // TODO: Add date range filters here ?>

            <div id="dashboard-widgets-wrap">
            <div id="dashboard-widgets" class="metabox-holder columns-2">

                <?php // Column 1: Sales & Revenue ?>
                <div id="postbox-container-1" class="postbox-container" style="width: 49%; margin-right: 2%; float: left;">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Sales & Revenue Summary (All Time)', 'orunk-users'); ?></span></h2>
                        <div class="inside">
                            <?php if (empty($sales_data)) : ?>
                                <p><?php esc_html_e('No sales data found for active/expired purchases.', 'orunk-users'); ?></p>
                            <?php else : ?>
                                <table class="wp-list-table widefat striped fixed">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php esc_html_e('Plan Name', 'orunk-users'); ?></th>
                                            <th scope="col"><?php esc_html_e('Sales', 'orunk-users'); ?></th>
                                            <th scope="col"><?php esc_html_e('Revenue', 'orunk-users'); ?></th>
                                            <th scope="col"><?php esc_html_e('Gateways', 'orunk-users'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $grand_total_sales = 0; $grand_total_revenue = 0.0; ?>
                                        <?php foreach ($sales_data as $plan_name => $data) : ?>
                                            <?php $grand_total_sales += $data['total_sales']; $grand_total_revenue += $data['total_revenue']; ?>
                                            <tr>
                                                <td><?php echo esc_html($plan_name); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($data['total_sales'])); ?></td>
                                                <td>$<?php echo esc_html(number_format($data['total_revenue'], 2)); ?></td>
                                                <td>
                                                    <?php if (!empty($data['gateways'])) : ?>
                                                        <ul style="margin: 0; padding: 0; list-style: none; font-size: 0.9em;">
                                                        <?php foreach ($data['gateways'] as $gw => $gw_data): ?>
                                                            <li><?php echo esc_html(ucfirst($gw)); ?>: <?php echo esc_html(number_format_i18n($gw_data['count'])); ?> ($<?php echo esc_html(number_format($gw_data['revenue'], 2)); ?>)</li>
                                                        <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                         <tr>
                                            <th scope="row"><?php esc_html_e('Grand Total', 'orunk-users'); ?></th>
                                            <td><strong><?php echo esc_html(number_format_i18n($grand_total_sales)); ?></strong></td>
                                            <td><strong>$<?php echo esc_html(number_format($grand_total_revenue, 2)); ?></strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div> <?php // end postbox-container-1 ?>

                <?php // Column 2: Active Users & API Usage ?>
                 <div id="postbox-container-2" class="postbox-container" style="width: 49%; float: left;">
                     <?php // Active Users Box ?>
                     <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Active Users per Plan', 'orunk-users'); ?></span></h2>
                        <div class="inside">
                             <?php if (empty($active_users_data)) : ?>
                                <p><?php esc_html_e('No active users found for any plan.', 'orunk-users'); ?></p>
                            <?php else : ?>
                                <table class="wp-list-table widefat striped fixed">
                                     <thead>
                                        <tr>
                                            <th scope="col"><?php esc_html_e('Plan Name', 'orunk-users'); ?></th>
                                            <th scope="col"><?php esc_html_e('Active Users', 'orunk-users'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                         <?php foreach ($active_users_data as $plan_name => $count) : ?>
                                            <tr>
                                                <td><?php echo esc_html($plan_name); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($count)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div> <?php // end active users postbox ?>

                    <?php // API Usage Box ?>
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('API Usage per Plan (Last 30 Days)', 'orunk-users'); ?></span></h2>
                        <div class="inside">
                            <?php if (isset($api_usage_data['error'])) : ?>
                                <p class="orunk-error notice notice-warning inline" style="border-left-width: 4px; background: #fff8e1; border-color: #ffe599; color: #8a6d3b; padding: 10px;">
                                    <?php echo wp_kses_post($api_usage_data['error']); ?>
                                </p>
                            <?php elseif (empty($api_usage_data)) : ?>
                                <p><?php esc_html_e('No API usage data found for the last 30 days.', 'orunk-users'); ?></p>
                            <?php else : ?>
                                <table class="wp-list-table widefat striped fixed">
                                     <thead>
                                        <tr>
                                            <th scope="col"><?php esc_html_e('Plan Name', 'orunk-users'); ?></th>
                                            <th scope="col"><?php esc_html_e('Total Requests', 'orunk-users'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                         <?php foreach ($api_usage_data as $plan_name => $count) : ?>
                                            <tr>
                                                <td><?php echo esc_html($plan_name); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($count)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            <p><small><?php esc_html_e('Note: API usage data requires the BIN Lookup API plugin and its logging table.', 'orunk-users'); ?></small></p>
                        </div>
                    </div> <?php // end API usage postbox ?>
                </div> <?php // end postbox-container-2 ?>

                <div class="clear"></div>
            </div> <?php // dashboard-widgets ?>
            </div> <?php // dashboard-widgets-wrap ?>
        </div> <?php // wrap ?>
        <?php
    } // end reports_page_html

} // End Class Custom_Orunk_Reports