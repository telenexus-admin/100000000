<?php

/**
 * RS Hotspot plan/router binding compatibility layer.
 *
 * The legacy Hotspot plan form treated RADIUS as global: selecting Radius hid
 * the router and services/add-post saved routers=''.  The captive portal is
 * router-scoped, so those plans were successfully saved but never rendered for
 * any specific router.  RS/WireGuard plans must stay bound to their router and
 * use the Radius device.
 */

$route = isset($_GET['_route']) ? trim((string) $_GET['_route']) : '';
if ($route === 'services/add-post') {
    $_GET['_route'] = 'plugin/rs_hotspot_plan_add_post';
} elseif ($route === 'services/edit-post') {
    $_GET['_route'] = 'plugin/rs_hotspot_plan_edit_post';
}

register_hook('view_add_plan', 'rs_hotspot_plan_add_ui_fix');
register_hook('view_edit_plan', 'rs_hotspot_plan_edit_ui_fix');

function rs_hotspot_plan_require_admin()
{
    _admin();
    $admin = Admin::_info();
    if (!in_array($admin['user_type'] ?? '', ['SuperAdmin', 'Admin'], true)) {
        r2(getUrl('dashboard'), 'e', Lang::T('You do not have permission to access this page'));
    }
    return $admin;
}

function rs_hotspot_plan_router_by_name($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return false;
    }
    return ORM::for_table('tbl_routers')->where('name', $name)->find_one();
}

function rs_hotspot_plan_is_managed_router($router)
{
    return $router && strtolower(trim((string) ($router['management_transport'] ?? ''))) === 'wireguard';
}

function rs_hotspot_plan_router_map()
{
    $map = [];
    foreach (ORM::for_table('tbl_routers')->where('enabled', 1)->find_many() as $router) {
        $name = trim((string) $router['name']);
        if ($name === '') {
            continue;
        }
        $map[$name] = [
            'wireguard' => rs_hotspot_plan_is_managed_router($router),
        ];
    }
    return $map;
}

function rs_hotspot_plan_append_xfooter($html)
{
    global $ui;
    $existing = '';
    try {
        $existing = (string) $ui->getTemplateVars('xfooter');
    } catch (Throwable $ignored) {
    }
    $ui->assign('xfooter', $existing . "\n" . $html);
}

function rs_hotspot_plan_add_ui_fix()
{
    $mapJson = json_encode(rs_hotspot_plan_router_map(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    rs_hotspot_plan_append_xfooter(<<<HTML
<script>
(function () {
    var routerMap = {$mapJson};

    function syncRsPlanBinding() {
        var routerEl = document.getElementById('routers');
        var deviceEl = document.getElementById('device');
        var radiusEl = document.querySelector('input[name="radius"]');
        var routerChoose = document.getElementById('routerChoose');
        if (routerChoose) {
            routerChoose.classList.remove('hidden');
            routerChoose.style.display = '';
        }
        if (!routerEl) return;
        routerEl.required = true;

        var routerName = routerEl.value || '';
        var managed = !!(routerMap[routerName] && routerMap[routerName].wireguard);
        if (managed) {
            if (radiusEl) radiusEl.checked = true;
            if (deviceEl) deviceEl.value = 'Radius';
        } else {
            if (deviceEl) deviceEl.value = (radiusEl && radiusEl.checked) ? 'Radius' : 'MikrotikHotspot';
        }

        var limited = document.getElementById('Limited');
        if (limited) limited.disabled = false;
    }

    // Replace the old global-RADIUS behavior which hid the router selector.
    window.isRadius = function () {
        syncRsPlanBinding();
    };

    $(function () {
        $('#routerChoose').removeClass('hidden').show();
        $('#routers').prop('required', true).off('change.rsplan').on('change.rsplan', syncRsPlanBinding);
        $('input[name="radius"]').off('change.rsplan').on('change.rsplan', syncRsPlanBinding);
        syncRsPlanBinding();
    });
})();
</script>
HTML);
}

function rs_hotspot_plan_edit_ui_fix()
{
    global $ui;
    $map = rs_hotspot_plan_router_map();
    $mapJson = json_encode($map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $options = '<option value="">Select Router...</option>';
    foreach (array_keys($map) as $name) {
        $safe = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $options .= '<option value="' . $safe . '">' . $safe . '</option>';
    }
    $optionsJson = json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    rs_hotspot_plan_append_xfooter(<<<HTML
<script>
(function () {
    var routerMap = {$mapJson};
    var optionHtml = {$optionsJson};
    $(function () {
        var old = $('#routers');
        var current = old.val() || '';
        $('#routerChoose').removeClass('hidden').show();
        if (old.length) {
            var select = $('<select class="form-control select2" id="routers" name="routers" required></select>');
            select.html(optionHtml).val(current);
            old.replaceWith(select);
            select.select2({theme: 'bootstrap'});
        }

        var device = $('#device');
        function syncEditBinding() {
            var name = $('#routers').val() || '';
            if (routerMap[name] && routerMap[name].wireguard) {
                device.val('Radius');
            }
        }
        $('#routers').on('change.rsplan', syncEditBinding);
        syncEditBinding();
    });
})();
</script>
HTML);
}

function rs_hotspot_plan_add_post()
{
    rs_hotspot_plan_require_admin();

    $name = trim((string) _post('name'));
    $planType = trim((string) _post('plan_type'));
    $typebp = trim((string) _post('typebp'));
    $limitType = trim((string) _post('limit_type'));
    $timeLimit = _post('time_limit');
    $timeUnit = _post('time_unit');
    $dataLimit = _post('data_limit');
    $dataUnit = _post('data_unit');
    $idBw = _post('id_bw');
    $price = _post('price');
    $sharedUsers = _post('sharedusers');
    $validity = _post('validity');
    $validityUnit = _post('validity_unit');
    $routerName = _post('routers');
    if (is_array($routerName)) {
        $routerName = reset($routerName);
    }
    $routerName = trim((string) $routerName);
    $enabled = _post('enabled');
    $prepaid = _post('prepaid');
    $expiredDate = _post('expired_date');

    $msg = '';
    if (!Validator::UnsignedNumber($validity)) {
        $msg .= 'The validity must be a number<br>';
    }
    if (!Validator::UnsignedNumber($price)) {
        $msg .= 'The price must be a number<br>';
    }
    if ($name === '' || $idBw === '' || $price === '' || $validity === '') {
        $msg .= Lang::T('All field is required') . '<br>';
    }
    if ($routerName === '') {
        $msg .= 'Please select the router this Hotspot package belongs to.<br>';
    }

    $router = rs_hotspot_plan_router_by_name($routerName);
    if ($routerName !== '' && !$router) {
        $msg .= 'Selected router was not found.<br>';
    }

    if (ORM::for_table('tbl_plans')->where('name_plan', $name)->where('type', 'Hotspot')->find_one()) {
        $msg .= Lang::T('Name Plan Already Exist') . '<br>';
    }

    if ($msg !== '') {
        r2(getUrl('services/add'), 'e', $msg);
    }

    $managed = rs_hotspot_plan_is_managed_router($router);
    $useRadius = $managed || !empty(_post('radius'));
    $device = $useRadius ? 'Radius' : 'MikrotikHotspot';

    $plan = ORM::for_table('tbl_plans')->create();
    $plan->name_plan = $name;
    $plan->id_bw = $idBw;
    $plan->price = $price;
    $plan->type = 'Hotspot';
    $plan->typebp = $typebp !== '' ? $typebp : 'Unlimited';
    $plan->plan_type = $planType !== '' ? $planType : 'Personal';
    $plan->limit_type = $limitType;
    $plan->time_limit = $timeLimit;
    $plan->time_unit = $timeUnit;
    $plan->data_limit = $dataLimit;
    $plan->data_unit = $dataUnit;
    $plan->validity = $validity;
    $plan->validity_unit = $validityUnit;
    $plan->shared_users = max(1, (int) $sharedUsers);
    $plan->is_radius = $useRadius ? 1 : 0;
    // Critical: RADIUS is still tenant/router scoped. Never erase this value.
    $plan->routers = $routerName;
    $plan->enabled = in_array((string) $enabled, ['0', '1'], true) ? $enabled : 1;
    $plan->prepaid = $prepaid === 'no' ? 'no' : 'yes';
    $plan->device = $device;
    if ($plan->prepaid === 'no') {
        $expiredDate = (int) $expiredDate;
        $plan->expired_date = ($expiredDate >= 1 && $expiredDate <= 28) ? $expiredDate : 20;
    } else {
        $plan->expired_date = 20;
    }
    $plan->save();

    try {
        $deviceFile = Package::getDevice($plan);
        if ($_app_stage != 'demo') {
            if (!file_exists($deviceFile)) {
                throw new RuntimeException(Lang::T('Devices Not Found'));
            }
            require_once $deviceFile;
            $class = (string) $plan['device'];
            if (!class_exists($class)) {
                throw new RuntimeException('Package device class ' . $class . ' is unavailable.');
            }
            (new $class())->add_plan($plan);
        }
    } catch (Throwable $e) {
        $id = $plan->id();
        try {
            $plan->delete();
        } catch (Throwable $ignored) {
        }
        error_log('[hotspot-plan] create failed id=' . (int) $id . ' error=' . $e->getMessage());
        r2(getUrl('services/add'), 'e', 'Package was not created: ' . $e->getMessage());
    }

    r2(getUrl('services/edit/') . $plan->id(), 's', Lang::T('Data Created Successfully'));
}

function rs_hotspot_plan_edit_post()
{
    rs_hotspot_plan_require_admin();

    $id = (int) _post('id');
    $plan = ORM::for_table('tbl_plans')->where('id', $id)->where('type', 'Hotspot')->find_one();
    if (!$plan) {
        r2(getUrl('services/hotspot'), 'e', Lang::T('Data Not Found'));
    }

    $routerName = _post('routers');
    if (is_array($routerName)) {
        $routerName = reset($routerName);
    }
    $routerName = trim((string) $routerName);
    if ($routerName === '') {
        $routerName = trim((string) $plan['routers']);
    }

    $router = rs_hotspot_plan_router_by_name($routerName);
    if ($routerName === '' || !$router) {
        r2(getUrl('services/edit/') . $id, 'e', 'Please select a valid router for this Hotspot package.');
    }

    $managed = rs_hotspot_plan_is_managed_router($router);
    if ($managed) {
        $plan->routers = $routerName;
        $plan->is_radius = 1;
        $plan->device = 'Radius';
        $plan->save();
        $_POST['device'] = 'Radius';
    } else {
        // Preserve the package authentication type, but never lose its router.
        $plan->routers = $routerName;
        if ((int) $plan['is_radius'] === 1) {
            $plan->device = 'Radius';
            $_POST['device'] = 'Radius';
        }
        $plan->save();
    }

    // Reuse the existing, battle-tested edit logic for all remaining fields.
    global $routes, $root_path;
    $routes = [0 => 'services', 1 => 'edit-post'];
    include $root_path . File::pathFixer('system/controllers/services.php');
    exit;
}
