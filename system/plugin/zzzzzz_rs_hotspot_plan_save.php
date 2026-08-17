<?php

/**
 * Final RS Hotspot package save handler.
 *
 * Replaces the recursive controller re-entry used by the earlier compatibility
 * layer.  Hotspot packages are always router-scoped.  WireGuard-managed routers
 * are automatically Radius-backed, while legacy/manual routers preserve their
 * selected authentication mode.
 */

$route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($route === 'plugin/rs_hotspot_plan_add_post') {
    $_GET['_route'] = 'plugin/rs4_hotspot_plan_add_post';
} elseif ($route === 'plugin/rs_hotspot_plan_edit_post') {
    $_GET['_route'] = 'plugin/rs4_hotspot_plan_edit_post';
}

function rs4_hotspot_plan_admin()
{
    _admin();
    $admin = Admin::_info();
    if (!in_array($admin['user_type'] ?? '', ['SuperAdmin', 'Admin'], true)) {
        r2(getUrl('dashboard'), 'e', Lang::T('You do not have permission to access this page'));
    }
    return $admin;
}

function rs4_hotspot_plan_router($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return false;
    }
    return ORM::for_table('tbl_routers')->where('name', $name)->find_one();
}

function rs4_hotspot_plan_is_wireguard($router)
{
    return $router && strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';
}

function rs4_hotspot_plan_post_router()
{
    $routerName = _post('routers');
    if (is_array($routerName)) {
        $routerName = reset($routerName);
    }
    return trim((string) $routerName);
}

function rs4_hotspot_plan_validate_common($editingId = 0)
{
    $name = trim((string) _post('name'));
    $idBw = trim((string) _post('id_bw'));
    $price = trim((string) _post('price'));
    $validity = trim((string) _post('validity'));
    $routerName = rs4_hotspot_plan_post_router();

    $errors = [];
    if ($name === '' || $idBw === '' || $price === '' || $validity === '') {
        $errors[] = Lang::T('All field is required');
    }
    if (!Validator::UnsignedNumber($validity)) {
        $errors[] = 'The validity must be a number';
    }
    if (!Validator::UnsignedNumber($price)) {
        $errors[] = 'The price must be a number';
    }
    if ($routerName === '') {
        $errors[] = 'Please select the router this Hotspot package belongs to.';
    }

    $router = $routerName !== '' ? rs4_hotspot_plan_router($routerName) : false;
    if ($routerName !== '' && !$router) {
        $errors[] = 'Selected router was not found.';
    }

    $bw = $idBw !== '' ? ORM::for_table('tbl_bandwidth')->find_one($idBw) : false;
    if ($idBw !== '' && !$bw) {
        $errors[] = 'Selected bandwidth profile was not found.';
    }

    if ($name !== '') {
        $existing = ORM::for_table('tbl_plans')
            ->where('name_plan', $name)
            ->where('type', 'Hotspot')
            ->find_one();
        if ($existing && (int) $existing['id'] !== (int) $editingId) {
            $errors[] = Lang::T('Name Plan Already Exist');
        }
    }

    return [
        'errors' => $errors,
        'router' => $router,
        'router_name' => $routerName,
        'bandwidth' => $bw,
    ];
}

function rs4_hotspot_plan_fill($plan, $router, $routerName, $preserveRadius = false)
{
    $wireguard = rs4_hotspot_plan_is_wireguard($router);
    $postedRadius = !empty(_post('radius'));
    $useRadius = $wireguard || $postedRadius || ($preserveRadius && (int) ($plan['is_radius'] ?? 0) === 1);

    $plan->name_plan = trim((string) _post('name'));
    $plan->id_bw = _post('id_bw');
    $plan->price = _post('price');
    $plan->type = 'Hotspot';
    $plan->typebp = trim((string) _post('typebp')) ?: 'Unlimited';
    $plan->plan_type = trim((string) _post('plan_type')) ?: 'Personal';
    $plan->limit_type = trim((string) _post('limit_type'));
    $plan->time_limit = _post('time_limit');
    $plan->time_unit = _post('time_unit');
    $plan->data_limit = _post('data_limit');
    $plan->data_unit = _post('data_unit');
    $plan->validity = _post('validity');
    $plan->validity_unit = _post('validity_unit');
    $plan->shared_users = max(1, (int) _post('sharedusers'));
    $plan->routers = $routerName;
    $plan->is_radius = $useRadius ? 1 : 0;
    $plan->device = $useRadius ? 'Radius' : 'MikrotikHotspot';
    $enabled = (string) _post('enabled');
    $plan->enabled = in_array($enabled, ['0', '1'], true) ? $enabled : '1';
    $plan->prepaid = _post('prepaid') === 'no' ? 'no' : 'yes';
    $plan->on_login = _post('on_login');
    $plan->on_logout = _post('on_logout');

    $priceOld = trim((string) _post('price_old'));
    if ($priceOld !== '' && is_numeric($priceOld) && (float) $priceOld > (float) $plan->price) {
        $plan->price_old = $priceOld;
    } else {
        $plan->price_old = '';
    }

    $expiredDate = (int) _post('expired_date');
    if ($plan->prepaid === 'no') {
        $plan->expired_date = ($expiredDate >= 1 && $expiredDate <= 28) ? $expiredDate : 20;
    } else {
        $plan->expired_date = 20;
    }

    if (isset($_POST['plan_expired'])) {
        $plan->plan_expired = (int) _post('plan_expired', '0');
    }

    return $useRadius;
}

function rs4_hotspot_plan_sync_device($plan, $old = null)
{
    global $_app_stage;
    if (isset($_app_stage) && strtolower((string) $_app_stage) === 'demo') {
        return;
    }

    $deviceFile = Package::getDevice($plan);
    if (!file_exists($deviceFile)) {
        throw new RuntimeException('Package device file was not found for ' . (string) $plan['device'] . '.');
    }
    require_once $deviceFile;

    $class = (string) $plan['device'];
    if (!class_exists($class)) {
        throw new RuntimeException('Package device class ' . $class . ' is unavailable.');
    }

    $driver = new $class();
    if ($old && method_exists($driver, 'update_plan')) {
        $driver->update_plan($old, $plan);
    } elseif (method_exists($driver, 'add_plan')) {
        $driver->add_plan($plan);
    }
}

function rs4_hotspot_plan_add_post()
{
    rs4_hotspot_plan_admin();
    $check = rs4_hotspot_plan_validate_common(0);
    if ($check['errors']) {
        r2(getUrl('services/add'), 'e', implode('<br>', $check['errors']));
    }

    $plan = ORM::for_table('tbl_plans')->create();
    try {
        rs4_hotspot_plan_fill($plan, $check['router'], $check['router_name'], false);
        $plan->save();
        rs4_hotspot_plan_sync_device($plan);
    } catch (Throwable $e) {
        $id = $plan->id();
        try {
            if ($id) {
                $plan->delete();
            }
        } catch (Throwable $ignored) {
        }
        error_log('[hotspot-plan] create failed id=' . (int) $id . ' error=' . $e->getMessage());
        r2(getUrl('services/add'), 'e', 'Package was not created: ' . $e->getMessage());
    }

    r2(getUrl('services/hotspot'), 's', Lang::T('Data Created Successfully'));
}

function rs4_hotspot_plan_edit_post()
{
    rs4_hotspot_plan_admin();
    $id = (int) _post('id');
    $plan = ORM::for_table('tbl_plans')->where('id', $id)->where('type', 'Hotspot')->find_one();
    $old = ORM::for_table('tbl_plans')->where('id', $id)->where('type', 'Hotspot')->find_one();
    if (!$plan || !$old) {
        r2(getUrl('services/hotspot'), 'e', Lang::T('Data Not Found'));
    }

    $check = rs4_hotspot_plan_validate_common($id);
    if ($check['errors']) {
        r2(getUrl('services/edit/') . $id, 'e', implode('<br>', $check['errors']));
    }

    try {
        rs4_hotspot_plan_fill($plan, $check['router'], $check['router_name'], true);
        $plan->save();
        rs4_hotspot_plan_sync_device($plan, $old);
    } catch (Throwable $e) {
        error_log('[hotspot-plan] edit failed id=' . $id . ' error=' . $e->getMessage());
        r2(getUrl('services/edit/') . $id, 'e', 'Package save failed: ' . $e->getMessage());
    }

    r2(getUrl('services/hotspot'), 's', Lang::T('Data Updated Successfully'));
}
