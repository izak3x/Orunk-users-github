<?php
/**
 * Template Name: Orunk User Order History
 * Template Post Type: page
 *
 * Displays a complete history of a logged-in user's orders, transactions,
 * plan switches, renewals, etc., from the Orunk Users plugin.
 * Includes basic filtering and sorting via JavaScript.
 * Displays pending and failed orders with failure details.
 *
 * @package Astra
 * @version 1.2.0 - Display All Statuses & Failure Details
 */

// --- Security & Setup ---
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Redirect non-logged-in users to the login page
if (!is_user_logged_in()) {
    $redirect_url = add_query_arg('redirect_to', get_permalink(), wp_login_url());
    wp_safe_redirect($redirect_url);
    exit;
}

// Check for required Orunk Users plugin classes
if (!class_exists('Custom_Orunk_Core') || !class_exists('Custom_Orunk_DB')) {
    get_header();
    ?>
    <div id="primary" class="content-area">
        <main id="main" class="site-main">
            <div class="orunk-container orunk-error" style="padding: 15px; margin: 15px; border: 1px solid #dc3232; background: #fef2f2; color: #991b1b;">
                <?php esc_html_e('Error: Required Orunk Users plugin classes (Core or DB) are missing. Please ensure the plugin is active and correctly installed.', 'orunk-users'); ?>
            </div>
        </main>
    </div>
    <?php
    get_footer();
    return; // Stop execution
}

// --- Get Data ---
global $wpdb;
$user_id    = get_current_user_id();
$orunk_core = new Custom_Orunk_Core();
// Fetch ALL history, including pending and failed (Phase 2 core update ensures this)
$all_history = $orunk_core->get_user_purchases($user_id);

// --- Prepare distinct values for filters ---
$distinct_types = [];
$distinct_statuses = [];
if (!empty($all_history)) {
    foreach ($all_history as $item) {
        // Get Transaction Type
        $raw_type = $item['transaction_type'] ?? 'purchase';
        $type_display = 'Purchase'; // Default
        switch ($raw_type) {
            case 'purchase':          $type_display = 'Initial Purchase'; break;
            case 'renewal_success':   $type_display = 'Renewal Success'; break;
            case 'switch_success':    $type_display = 'Plan Switch Success'; break;
            case 'renewal_failure':   $type_display = 'Renewal Failed'; break;
            case 'switch_failure':    $type_display = 'Plan Switch Failed'; break; // If you store this
            case 'renewal_attempt':   $type_display = 'Renewal Attempt'; break; // If you store this
            case 'switch_attempt':    $type_display = 'Plan Switch Attempt'; break; // If you store this
        }
        // Store raw value -> translated display
        $distinct_types[$raw_type] = esc_html__(ucwords(str_replace('_', ' ', $type_display)), 'orunk-users');

        // Get Status and ensure lowercase for consistency
        $status_raw = strtolower($item['status'] ?? 'unknown');
        $status_display_label = 'Unknown'; // Default display label
        switch ($status_raw) {
            case 'active':          $status_display_label = 'Active'; break;
            case 'pending payment': $status_display_label = 'Pending Payment'; break; // Specific label
            case 'pending':         $status_display_label = 'Pending'; break; // Keep generic pending if used
            case 'failed':          $status_display_label = 'Failed'; break;
            case 'expired':         $status_display_label = 'Expired'; break;
            case 'cancelled':       $status_display_label = 'Cancelled'; break;
            case 'switched':        $status_display_label = 'Switched'; break;
        }
        // Use raw status as key, translated label as value
        $distinct_statuses[$status_raw] = esc_html__(ucfirst($status_display_label), 'orunk-users');
    }
    ksort($distinct_types); // Sort by raw key
    ksort($distinct_statuses); // Sort alphabetically by raw key
}


// --- Start HTML Output ---
get_header();
?>
<head>
    <?php // Add any necessary <head> elements ?>
    <style>
        .orunk-container { max-width: 1140px; margin: 2rem auto; padding: 1.5rem; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .entry-title { margin-bottom: 1.5rem; font-size: 1.8em; font-weight: 700; color: #1f2937; }
        .orunk-history-controls { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee; }
        .orunk-history-controls label { font-weight: 500; margin-right: 0.5rem; font-size: 0.9em; color: #4b5563; }
        .orunk-history-controls select, .orunk-history-controls button {
            padding: 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.85em;
            background-color: #fff;
            cursor: pointer;
        }
         .orunk-history-controls button { background-color: #f9fafb; color: #374151; }
         .orunk-history-controls button:hover { background-color: #f3f4f6; }
         .orunk-history-controls button.active-sort { border-color: #6366f1; background-color: #eef2ff; color: #4338ca; font-weight: 600; }

        .orunk-history-table-wrapper { overflow-x: auto; }
        .orunk-history-table { width: 100%; border-collapse: collapse; margin-top: 0; font-size: 0.9em; }
        .orunk-history-table th,
        .orunk-history-table td { padding: 10px 12px; border: 1px solid #e5e7eb; text-align: left; vertical-align: middle; white-space: nowrap; }
        .orunk-history-table th { background-color: #f9fafb; font-weight: 600; color: #4b5563; }
        .orunk-history-table th.sortable { cursor: pointer; }
        .orunk-history-table th.sortable:hover { background-color: #f3f4f6; }
        .orunk-history-table th .sort-icon { margin-left: 5px; color: #9ca3af; display: inline-block; width: 1em; }
        .orunk-history-table th .sort-icon.asc::after { content: '▲'; font-size: 0.8em;}
        .orunk-history-table th .sort-icon.desc::after { content: '▼'; font-size: 0.8em;}

        .orunk-history-table tbody tr:nth-child(odd) { background-color: #fdfdff; }
        .orunk-history-table tbody tr:hover { background-color: #f5f5f5; }
        .orunk-history-table tbody tr.filtered-out { display: none; } /* Class to hide rows */

        /* Status Badge Styles */
        .orunk-history-table .status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; font-weight: 500; text-transform: capitalize; color: #fff; margin-bottom: 3px; /* Add margin if failure details shown below */ }
        .status-active { background-color: #10b981; } /* Emerald 500 */
        .status-pending-payment, /* Use same style as pending */
        .status-pending { background-color: #f59e0b; } /* Amber 500 */
        .status-failed { background-color: #ef4444; } /* Red 500 - Changed from gray */
        .status-expired, .status-cancelled, .status-switched { background-color: #6b7280; } /* Gray 500 */
        .status-unknown { background-color: #9ca3af; } /* Gray 400 */

        /* Failure Details Style */
        .failure-details {
            font-size: 0.75em;
            color: #4b5563; /* Slightly darker gray */
            margin-top: 5px; /* Space below badge */
            line-height: 1.4;
            white-space: normal; /* Allow reason to wrap */
            display: block; /* Ensure it takes block space */
        }
        .failure-details strong { font-weight: 600; }
        .failure-details code { background-color: #f3f4f6; padding: 1px 4px; border-radius: 3px; font-family: monospace; }

        .no-history { text-align: center; padding: 2rem; color: #6b7280; }
        .orunk-history-table .col-price, .orunk-history-table .col-id { text-align: right; }
        .orunk-history-table .col-status { text-align: center; vertical-align: top; /* Align top for consistent badge placement */ }
    </style>
</head>
<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="orunk-container">
            <header class="entry-header">
                <h1 class="entry-title"><?php esc_html_e('Order & Transaction History', 'orunk-users'); ?></h1>
            </header>
            <div class="entry-content">

                <?php if (empty($all_history)) : ?>
                    <div class="no-history">
                        <p><?php esc_html_e('You have no order history.', 'orunk-users'); ?></p>
                        <?php
                        $catalog_page = get_page_by_path('orunk-catalog');
                        if ($catalog_page) {
                            echo '<a href="' . esc_url(get_permalink($catalog_page->ID)) . '" class="button">' . esc_html__('Browse Plans', 'orunk-users') . '</a>';
                        }
                        ?>
                    </div>
                <?php else : ?>
                    <?php // --- Filter and Sort Controls --- ?>
                    <div class="orunk-history-controls">
                        <div>
                            <label for="history-filter-type"><?php esc_html_e('Type:', 'orunk-users'); ?></label>
                            <select id="history-filter-type">
                                <option value="all"><?php esc_html_e('All Types', 'orunk-users'); ?></option>
                                <?php foreach ($distinct_types as $raw_type => $display_type) : ?>
                                    <option value="<?php echo esc_attr($raw_type); ?>"><?php echo esc_html($display_type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="history-filter-status"><?php esc_html_e('Status:', 'orunk-users'); ?></label>
                            <select id="history-filter-status">
                                <option value="all"><?php esc_html_e('All Statuses', 'orunk-users'); ?></option>
                                <?php // Use the processed distinct statuses with user-friendly labels
                                foreach ($distinct_statuses as $raw_status => $display_status) : ?>
                                    <option value="<?php echo esc_attr($raw_status); ?>"><?php echo esc_html($display_status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                             <label><?php esc_html_e('Sort By:', 'orunk-users'); ?></label>
                            <button id="sort-date-desc" class="sort-button active-sort" data-column="0" data-order="desc">Date <span class="sort-icon desc"></span></button>
                            <button id="sort-date-asc" class="sort-button" data-column="0" data-order="asc">Date <span class="sort-icon asc"></span></button>
                        </div>
                         <div id="history-count" style="margin-left: auto; font-size: 0.9em; color: #6b7280; align-self: center;">
                            <?php printf(esc_html__('%s items showing', 'orunk-users'), '<span id="visible-count">' . count($all_history) . '</span>'); ?>
                        </div>
                    </div>

                    <div class="orunk-history-table-wrapper">
                        <table class="orunk-history-table" id="order-history-table">
                            <thead>
                                <tr>
                                    <th class="sortable" data-column-index="0"><?php esc_html_e('Date', 'orunk-users'); ?><span class="sort-icon"></span></th>
                                    <th><?php esc_html_e('Type', 'orunk-users'); ?></th>
                                    <th><?php esc_html_e('Plan', 'orunk-users'); ?></th>
                                    <th><?php esc_html_e('Feature', 'orunk-users'); ?></th>
                                    <th class="col-price"><?php esc_html_e('Price', 'orunk-users'); ?></th>
                                    <th class="col-status"><?php esc_html_e('Status', 'orunk-users'); ?></th>
                                    <th><?php esc_html_e('Payment Method', 'orunk-users'); ?></th>
                                    <th><?php esc_html_e('Expiry Date', 'orunk-users'); ?></th>
                                    <th><?php esc_html_e('Transaction ID', 'orunk-users'); ?></th>
                                    <th class="col-id"><?php esc_html_e('Purchase ID', 'orunk-users'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_history as $item) : ?>
                                    <?php
                                        // Extract and format data safely
                                        $purchase_timestamp = strtotime($item['purchase_date']);
                                        $purchase_date_display = $item['purchase_date'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $purchase_timestamp) : '-'; // Use timestamp directly
                                        $expiry_date_display = $item['expiry_date'] ? date_i18n(get_option('date_format'), strtotime($item['expiry_date'])) : __('N/A', 'orunk-users');
                                        $plan_name = esc_html($item['plan_name'] ?? __('N/A', 'orunk-users'));
                                        $feature_key = esc_html($item['product_feature_key'] ?? __('N/A', 'orunk-users'));
                                        $gateway = esc_html(ucfirst(str_replace('_', ' ', $item['payment_gateway'] ?? __('N/A', 'orunk-users'))));
                                        $transaction_id = esc_html($item['transaction_id'] ?? __('N/A', 'orunk-users'));
                                        $purchase_id = esc_html($item['id']);

                                        // Handle Status Display and Class
                                        $status_raw = strtolower($item['status'] ?? 'unknown');
                                        $status_display_text = 'Unknown'; // Default display label
                                        $status_badge_class = 'status-unknown'; // Default class

                                        switch ($status_raw) {
                                            case 'active':
                                                $status_display_text = 'Active';
                                                $status_badge_class = 'status-active';
                                                break;
                                            case 'pending payment': // Specific handling
                                                $status_display_text = 'Pending Payment';
                                                $status_badge_class = 'status-pending-payment'; // Map to pending style
                                                break;
                                            case 'pending': // Keep generic pending if used
                                                $status_display_text = 'Pending';
                                                $status_badge_class = 'status-pending';
                                                break;
                                            case 'failed':
                                                $status_display_text = 'Failed';
                                                $status_badge_class = 'status-failed';
                                                break;
                                            case 'expired':
                                                $status_display_text = 'Expired';
                                                $status_badge_class = 'status-expired';
                                                break;
                                            case 'cancelled':
                                                $status_display_text = 'Cancelled';
                                                $status_badge_class = 'status-cancelled';
                                                break;
                                            case 'switched':
                                                $status_display_text = 'Switched';
                                                $status_badge_class = 'status-switched';
                                                break;
                                        }
                                        $status_display_text = esc_html__(ucfirst($status_display_text), 'orunk-users'); // Translate

                                        // Get Price from Snapshot
                                        $price_display = __('N/A');
                                        if (!empty($item['plan_details_snapshot'])) {
                                            $snapshot_data = json_decode($item['plan_details_snapshot'], true);
                                            if ($snapshot_data && isset($snapshot_data['price'])) {
                                                $price_display = '$' . number_format((float)$snapshot_data['price'], 2);
                                            }
                                        }

                                        // Get Transaction Type Display (Keep existing logic)
                                        $raw_type = $item['transaction_type'] ?? 'purchase';
                                        $type_display_text = 'Purchase';
                                        switch ($raw_type) {
                                            case 'purchase': $type_display_text = 'Initial Purchase'; break;
                                            case 'renewal_success': $type_display_text = 'Renewal Success'; break;
                                            case 'switch_success': $type_display_text = 'Plan Switch Success'; break;
                                            case 'renewal_failure': $type_display_text = 'Renewal Failed'; break;
                                            case 'switch_failure': $type_display_text = 'Plan Switch Failed'; break;
                                            case 'renewal_attempt': $type_display_text = 'Renewal Attempt'; break;
                                            case 'switch_attempt': $type_display_text = 'Plan Switch Attempt'; break;
                                        }
                                        $type_display_text = esc_html__(ucwords(str_replace('_', ' ', $type_display_text)), 'orunk-users'); // Translate

                                        // Get Failure Details
                                        $failure_reason = isset($item['failure_reason']) ? esc_html($item['failure_reason']) : null;
                                        $failure_timestamp = isset($item['failure_timestamp']) ? strtotime($item['failure_timestamp']) : null;
                                        $failure_timestamp_display = $failure_timestamp ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $failure_timestamp) : null;

                                    ?>
                                    <tr data-timestamp="<?php echo esc_attr($purchase_timestamp); ?>" data-status="<?php echo esc_attr($status_raw); ?>" data-type="<?php echo esc_attr($raw_type); ?>">
                                        <td><?php echo $purchase_date_display; ?></td>
                                        <td><?php echo $type_display_text; ?></td>
                                        <td><?php echo $plan_name; ?></td>
                                        <td><?php echo $feature_key; ?></td>
                                        <td class="col-price"><?php echo $price_display; ?></td>
                                        <td class="col-status">
                                            <span class="status-badge <?php echo esc_attr($status_badge_class); ?>"><?php echo $status_display_text; ?></span>
                                            <?php // Display failure details if status is 'failed' ?>
                                            <?php if ($status_raw === 'failed' && ($failure_reason || $failure_timestamp_display)) : ?>
                                                <div class="failure-details">
                                                    <?php if ($failure_timestamp_display): ?>
                                                        <span title="<?php esc_attr_e('Failure Time'); ?>">⏱️ <?php echo $failure_timestamp_display; ?></span><br>
                                                    <?php endif; ?>
                                                    <?php if ($failure_reason): ?>
                                                        <span title="<?php esc_attr_e('Reason'); ?>">Reason: <code><?php echo $failure_reason; ?></code></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $gateway; ?></td>
                                        <td><?php echo $expiry_date_display; ?></td>
                                        <td><?php echo $transaction_id; ?></td>
                                        <td class="col-id">#<?php echo $purchase_id; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="no-results-message" style="display: none; text-align: center; padding: 2rem; color: #6b7280;">
                        <?php esc_html_e('No history items match your filters.', 'orunk-users'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeFilter = document.getElementById('history-filter-type');
    const statusFilter = document.getElementById('history-filter-status');
    const tableBody = document.getElementById('order-history-table')?.querySelector('tbody');
    const sortButtons = document.querySelectorAll('.sort-button');
    const visibleCountSpan = document.getElementById('visible-count');
    const noResultsMessage = document.getElementById('no-results-message');
    let tableRows = tableBody ? Array.from(tableBody.querySelectorAll('tr')) : [];

    function filterAndSortTable() {
        if (!tableBody) return;

        const selectedType = typeFilter.value;
        const selectedStatus = statusFilter.value;
        const currentSortButton = document.querySelector('.sort-button.active-sort');
        const sortColumnIndex = parseInt(currentSortButton?.dataset.column || '0', 10);
        const sortOrder = currentSortButton?.dataset.order || 'desc';

        let visibleRows = 0;

        // 1. Filter Rows
        tableRows.forEach(row => {
            const typeMatch = selectedType === 'all' || row.dataset.type === selectedType;
            // Use strict equality for status matching
            const statusMatch = selectedStatus === 'all' || row.dataset.status === selectedStatus;

            if (typeMatch && statusMatch) {
                row.classList.remove('filtered-out');
                visibleRows++;
            } else {
                row.classList.add('filtered-out');
            }
        });

        // 2. Sort Filtered Rows
        const sortedRows = tableRows.filter(row => !row.classList.contains('filtered-out')); // Get only visible rows

        sortedRows.sort((a, b) => {
            let valA, valB;

            if (sortColumnIndex === 0) { // Date column (timestamp)
                 valA = parseInt(a.dataset.timestamp || '0', 10);
                 valB = parseInt(b.dataset.timestamp || '0', 10);
            } else {
                 // Fallback for other columns (text comparison)
                 const cellA = a.cells[sortColumnIndex]?.textContent.trim().toLowerCase() || '';
                 const cellB = b.cells[sortColumnIndex]?.textContent.trim().toLowerCase() || '';
                 // Basic numeric check for price column maybe? (Assumes column 4 is price)
                 if (sortColumnIndex === 4) {
                     valA = parseFloat(cellA.replace(/[^0-9.-]+/g,"")) || 0;
                     valB = parseFloat(cellB.replace(/[^0-9.-]+/g,"")) || 0;
                 } else {
                     valA = cellA;
                     valB = cellB;
                 }
            }

            // Comparison logic
            if (valA < valB) {
                return sortOrder === 'asc' ? -1 : 1;
            }
            if (valA > valB) {
                return sortOrder === 'asc' ? 1 : -1;
            }
            return 0;
        });

        // 3. Re-append sorted rows to the table body
        // Detach all rows first to avoid layout thrashing
        while (tableBody.firstChild) {
            tableBody.removeChild(tableBody.firstChild);
        }
        // Append sorted visible rows
        sortedRows.forEach(row => tableBody.appendChild(row));
        // Append filtered-out rows (to keep them in DOM if needed, hidden by CSS)
        tableRows.filter(row => row.classList.contains('filtered-out')).forEach(row => tableBody.appendChild(row));


        // 4. Update Visible Count and No Results Message
        if (visibleCountSpan) {
            visibleCountSpan.textContent = visibleRows;
        }
        if (noResultsMessage) {
            noResultsMessage.style.display = visibleRows === 0 ? 'block' : 'none';
        }
         if (document.getElementById('order-history-table')) {
            document.getElementById('order-history-table').style.display = visibleRows === 0 ? 'none' : '';
        }
    }

    function handleSortClick(event) {
        const button = event.currentTarget;
        const currentActive = document.querySelector('.sort-button.active-sort');

        // Remove active class from previous button
        if (currentActive) {
            currentActive.classList.remove('active-sort');
        }
        // Add active class to clicked button
        button.classList.add('active-sort');

        filterAndSortTable(); // Re-filter and sort
    }


    // --- Initial Setup ---
    if (typeFilter) {
        typeFilter.addEventListener('change', filterAndSortTable);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', filterAndSortTable);
    }
    sortButtons.forEach(button => {
        button.addEventListener('click', handleSortClick);
    });

    // Initial sort on page load (defaults to date descending)
    if (tableBody) {
         filterAndSortTable();
    }

});
</script>

<?php
get_footer(); // Include theme footer
?>