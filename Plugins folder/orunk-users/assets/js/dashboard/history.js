/**
 * Orunk User Dashboard - History Script
 * Handles the Purchase History table interactions (Show More).
 */

function initializePurchaseHistoryView() {
    const historyTableBody = document.getElementById('history-table-body');
    if (!historyTableBody) { return; }

    const showMoreHistoryBtn = document.getElementById('show-more-history');
    if (!showMoreHistoryBtn) { return; }

    const rows = historyTableBody.querySelectorAll('tr');
    // console.log(`Found ${rows.length} history rows.`); // Optional debug log

    if (rows.length <= 5) {
        showMoreHistoryBtn.classList.add('hidden');
        // console.log("Hiding 'Show More' button (<= 5 items)."); // Optional debug log
        return; // No need to add hidden classes if <= 5 rows
    } else {
        showMoreHistoryBtn.classList.remove('hidden');
        // console.log("Showing 'Show More' button (> 5 items)."); // Optional debug log
    }

    // Add 'history-hidden' class to rows beyond the initial 5
    rows.forEach((row, index) => {
        if (index >= 5) {
            row.classList.add('history-hidden');
        } else {
            row.classList.remove('history-hidden'); // Ensure first 5 are visible
        }
    });
    // console.log("Initial history view set (showing max 5)."); // Optional debug log
}

function handleShowMoreHistory(button) {
    const historyTableBody = document.getElementById('history-table-body');
    if (!historyTableBody) return;

    console.log("Handling 'Show More' click.");
    const hiddenRows = historyTableBody.querySelectorAll('tr.history-hidden');
    hiddenRows.forEach(row => {
        row.classList.remove('history-hidden');
    });
    console.log(`Revealed ${hiddenRows.length} hidden rows.`);

    button.classList.add('hidden'); // Hide the button after clicking
    console.log("Hiding 'Show More' button after click.");
}