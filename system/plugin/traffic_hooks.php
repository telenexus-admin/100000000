<?php
// Hook untuk reset usage saat recharge success
// File ini akan di-include dari system payment hooks

use PEAR2\Net\RouterOS;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;


function traffic_reset_on_recharge($customer_id)
{
    // Get customer data
    $customer = ORM::for_table('tbl_customers')->find_one($customer_id);
    if (!$customer) return;

    $pppoeUsername = $customer['pppoe_username'];
    if (empty($pppoeUsername)) {
        error_log("PAYMENT RESET: No PPPoE username for customer ID: $customer_id");
        return;
    }

    // Ambil periode billing LAMA dari expiration tracking
    $tracking = ORM::for_table('tbl_expiration_tracking')
        ->where('customer_id', $customer_id)
        ->find_one();

    $oldPeriodYear  = intval(date('Y'));
    $oldPeriodMonth = intval(date('m'));
    if ($tracking && !empty($tracking['last_expiration'])) {
        $oldPeriodYear  = intval(date('Y', strtotime($tracking['last_expiration'])));
        $oldPeriodMonth = intval(date('m', strtotime($tracking['last_expiration'])));
    }

    // Ambil usage dari connection_status (last_tx/last_rx)
    $statusRecord = ORM::for_table('tbl_user_connection_status')
        ->where('customer_id', $customer_id)
        ->find_one();

    $currentTx = $statusRecord ? intval($statusRecord['last_tx']) : 0;
    $currentRx = $statusRecord ? intval($statusRecord['last_rx']) : 0;

    // Reset usage history from database
    $historyRecord = ORM::for_table('tbl_user_usage_history')
        ->where('customer_id', $customer_id)
        ->find_one();

    if ($historyRecord) {
        // Gabungkan history + current session
        $totalUp   = intval($historyRecord['total_upload'])  + $currentTx;
        $totalDown = intval($historyRecord['total_download']) + $currentRx;

        // Simpan snapshot ke periode billing LAMA sebelum dihapus
        if ($totalUp > 0 || $totalDown > 0) {
            $existing = ORM::for_table('tbl_usage_history_monthly')
                ->where('customer_id', $customer_id)
                ->where('period_year', $oldPeriodYear)
                ->where('period_month', $oldPeriodMonth)
                ->find_one();

            if ($existing) {
                $existing->total_upload   = max(intval($existing['total_upload']), $totalUp);
                $existing->total_download = max(intval($existing['total_download']), $totalDown);
                $existing->total_bytes    = $existing->total_upload + $existing->total_download;
                $existing->recorded_at    = date('Y-m-d H:i:s');
                $existing->save();
            } else {
                $snapshot               = ORM::for_table('tbl_usage_history_monthly')->create();
                $snapshot->customer_id  = $customer_id;
                $snapshot->username     = $pppoeUsername;
                $snapshot->user_comment = $customer['fullname'] ?? '';
                $snapshot->period_year  = $oldPeriodYear;
                $snapshot->period_month = $oldPeriodMonth;
                $snapshot->total_upload   = $totalUp;
                $snapshot->total_download = $totalDown;
                $snapshot->total_bytes    = $totalUp + $totalDown;
                $snapshot->recorded_at  = date('Y-m-d H:i:s');
                $snapshot->save();
            }

            error_log("PAYMENT RESET: Monthly snapshot saved for {$customer['fullname']} ({$oldPeriodYear}-{$oldPeriodMonth}) - UP: $totalUp, DOWN: $totalDown");
        }

        $historyRecord->delete();
        error_log("PAYMENT RESET: Usage history cleared for customer ID: $customer_id");
    } elseif ($currentTx > 0 || $currentRx > 0) {
        // Tidak ada history tapi ada current session
        $existing = ORM::for_table('tbl_usage_history_monthly')
            ->where('customer_id', $customer_id)
            ->where('period_year', $oldPeriodYear)
            ->where('period_month', $oldPeriodMonth)
            ->find_one();

        if ($existing) {
            $existing->total_upload   = max(intval($existing['total_upload']), $currentTx);
            $existing->total_download = max(intval($existing['total_download']), $currentRx);
            $existing->total_bytes    = $existing->total_upload + $existing->total_download;
            $existing->recorded_at    = date('Y-m-d H:i:s');
            $existing->save();
        } else {
            $snapshot               = ORM::for_table('tbl_usage_history_monthly')->create();
            $snapshot->customer_id  = $customer_id;
            $snapshot->username     = $pppoeUsername;
            $snapshot->user_comment = $customer['fullname'] ?? '';
            $snapshot->period_year  = $oldPeriodYear;
            $snapshot->period_month = $oldPeriodMonth;
            $snapshot->total_upload   = $currentTx;
            $snapshot->total_download = $currentRx;
            $snapshot->total_bytes    = $currentTx + $currentRx;
            $snapshot->recorded_at  = date('Y-m-d H:i:s');
            $snapshot->save();
        }

        error_log("PAYMENT RESET: Monthly snapshot saved from session for {$customer['fullname']} ({$oldPeriodYear}-{$oldPeriodMonth})");
    }

    // Reset connection status from database
    if ($statusRecord) {
        $statusRecord->delete();
        error_log("PAYMENT RESET: Connection status cleared for customer ID: $customer_id");
    }

    // ✅ Reset interface counter jika user sedang online
    try {
        $routers = ORM::for_table('tbl_routers')->where('enabled', '1')->find_many();

        foreach ($routers as $mikrotik) {
            try {
                $client = Mikrotik::getClient($mikrotik['ip_address'], $mikrotik['username'], $mikrotik['password']);

                $pppActive   = $client->sendSync(new RouterOS\Request('/ppp/active/print'));
                $userOnline  = false;

                foreach ($pppActive as $active) {
                    if ($active->getProperty('name') === $pppoeUsername) {
                        $userOnline = true;
                        break;
                    }
                }

                if ($userOnline) {
                    $interfaceName = "<pppoe-$pppoeUsername>";
                    $resetRequest  = new RouterOS\Request('/interface/reset-counters');
                    $resetRequest->setArgument('numbers', $interfaceName);
                    $client->sendSync($resetRequest);

                    error_log("PAYMENT RESET: Interface counter reset for $pppoeUsername in router: {$mikrotik['name']}");
                    break;
                }
            } catch (Exception $routerError) {
                error_log("PAYMENT RESET: Error checking router {$mikrotik['name']}: " . $routerError->getMessage());
                continue;
            }
        }
    } catch (Exception $e) {
        error_log("PAYMENT RESET: Interface reset error: " . $e->getMessage());
    }
}
