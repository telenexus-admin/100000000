<?php
// Register the Mpesa Transactions menu
register_menu('M-Pesa Transactions', true, 'mpesa_transactions', 'AFTER_REPORTS', 'fa-solid fa-list', '', '', ['Admin', 'SuperAdmin']);

function mpesa_transactions()
{
    global $ui, $config, $admin;
    _admin();

    // Check if the table exists, if not, create it
    if (!check_table_exists('tbl_mpesa_transactions')) {
        createTableIfMpesaNotExists();
    }

    // Handle search input (GET "search" may be an array in malformed URLs)
    $rawSearch = $_GET['search'] ?? '';
    if (is_array($rawSearch)) {
        $rawSearch = $rawSearch === [] ? '' : (string) reset($rawSearch);
    }
    $search = trim((string) $rawSearch);

    // Fetch transactions based on search input or get all if no search input
    if ($search != '') {
        $transactions = ORM::for_table('tbl_mpesa_transactions')
            ->where_raw(
                "(TransID LIKE ? OR FirstName LIKE ? OR TransAmount LIKE ? OR MSISDN LIKE ? OR BillRefNumber LIKE ?)",
                ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]
            )
            ->order_by_desc('id')
            ->find_many();
    } else {
        $transactions = ORM::for_table('tbl_mpesa_transactions')
            ->order_by_desc('id')
            ->find_many();
    }

    // Assign variables to the template
    $ui->assign('t', $transactions);
    $ui->assign('txn_count', is_array($transactions) ? count($transactions) : 0);
    $ui->assign('search', $search);  // Pass the search term to the template
    $ui->assign('_title', Lang::T('Mpesa_Transactions'));
    $ui->assign('_system_menu', 'plugin/mpesa_transactions');
    $ui->assign('_admin', Admin::_info());

    // Display the template
    $ui->display('mpesa_transactions.tpl');
}

/**
 * Bulk-delete selected rows from tbl_mpesa_transactions (POST mpesa_txn_ids[]).
 */
function mpesa_transactions_multi_delete()
{
    global $admin;
    _admin();

    if (!check_table_exists('tbl_mpesa_transactions')) {
        r2(Text::url('plugin/mpesa_transactions'), 'e', Lang::T('Mpesa_transactions_table_not_found'));
    }

    $raw = isset($_POST['mpesa_txn_ids']) ? $_POST['mpesa_txn_ids'] : [];
    if (!is_array($raw)) {
        $raw = ($raw === '' || $raw === null) ? [] : [$raw];
    }
    if (empty($raw)) {
        r2(Text::url('plugin/mpesa_transactions'), 'e', Lang::T('Please_select_at_least_one_M_Pesa_transaction'));
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $raw))));
    if (empty($ids)) {
        r2(Text::url('plugin/mpesa_transactions'), 'e', Lang::T('Invalid_selection'));
    }

    $rs = _post('return_search');
    $return_search = is_array($rs) ? trim((string) reset($rs)) : trim((string) $rs);
    $qs = $return_search !== '' ? Text::isQA() . 'search=' . urlencode($return_search) : '';

    try {
        ORM::for_table('tbl_mpesa_transactions')->where_in('id', $ids)->delete_many();
        $n = count($ids);
        _log(
            '[' . ($admin['username'] ?? 'admin') . ']: Deleted ' . $n . ' M-Pesa transaction(s) (ids: ' . implode(',', $ids) . ')',
            'Admin',
            $admin['id'] ?? 0
        );
        r2(Text::url('plugin/mpesa_transactions') . $qs, 's', Lang::T('Selected_M_Pesa_transactions_deleted'));
    } catch (Exception $e) {
        r2(Text::url('plugin/mpesa_transactions') . $qs, 'e', Lang::T('Failed_to_delete_M_Pesa_transactions'));
    }
}

// Function to check if a table exists in the database
function check_table_exists($table_name)
{
    try {
        ORM::for_table($table_name)->find_one();
        return true;
    } catch (Exception $e) {
        return false; // Table doesn't exist or some other error occurred
    }
}

// Function to create the Mpesa transactions table if it doesn't exist
function createTableIfMpesaNotExists()
{
    $db = ORM::get_db();
    $tableCheckQuery = "CREATE TABLE IF NOT EXISTS tbl_mpesa_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        TransID VARCHAR(255) NOT NULL,
        TransactionType VARCHAR(255) NOT NULL,
        TransTime VARCHAR(255) NOT NULL,
        TransAmount DECIMAL(10, 2) NOT NULL,
        BusinessShortCode VARCHAR(255) NOT NULL,
        BillRefNumber VARCHAR(255) NOT NULL,
        OrgAccountBalance DECIMAL(10, 2) NOT NULL,
        MSISDN VARCHAR(255) NOT NULL,
        FirstName VARCHAR(255) NOT NULL
    )";
    $db->exec($tableCheckQuery);
}
