<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{$_title} - {$_c['CompanyName']}</title>
    <link rel="shortcut icon" href="{$app_url}/ui/ui/images/logo.png" type="image/x-icon" />

    <script>
        var appUrl = '{$app_url}';
    </script>

    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/bootstrap.min.css">
    <link rel="stylesheet" href="{$app_url}/ui/ui/fonts/ionicons/css/ionicons.min.css">
    <!-- Replace Font Awesome with version 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/modern-AdminLTE.min.css">
    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/select2.min.css" />
    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/select2-bootstrap.min.css" />
    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/sweetalert2.min.css" />
    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/plugins/pace.css" />
    <link rel="stylesheet" href="{$app_url}/ui/ui/summernote/summernote.min.css" />
    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/allxsys.css?2025.2.4" />
    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/7.css" />

    <script src="{$app_url}/ui/ui/scripts/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.5.1/dist/chart.min.js"></script>
    {if isset($xheader)}
        {$xheader}
    {/if}
</head>

<body class="hold-transition modern-skin-dark sidebar-mini {if $_kolaps}sidebar-collapse{/if}">
    <div class="wrapper">
        <header class="main-header">
            <a href="{Text::url('dashboard')}" class="logo">
                <span class="logo-mini"><b>R</b>AY</span>
                <span class="logo-lg">{$_c['CompanyName']}</span>
            </a>
            <nav class="navbar navbar-static-top">
                <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button" onclick="return setKolaps()">
                    <span class="sr-only">Toggle navigation</span>
                    <i class="fas fa-bars"></i>
                </a>
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <div class="wrap">
                            <div class="">
                                <button id="openSearch" class="search"><i class="fas fa-search x2"></i></button>
                            </div>
                        </div>
                        <div id="searchOverlay" class="search-overlay">
                            <div class="search-container">
                                <input type="text" id="searchTerm" class="searchTerm"
                                    placeholder="{Lang::T('Search Users')}" autocomplete="off">
                                <div id="searchResults" class="search-results">
                                    <!-- Search results will be displayed here -->
                                </div>
                                <button type="button" id="closeSearch" class="cancelButton">
                                    <i class="fas fa-times"></i> {Lang::T('Cancel')}
                                </button>
                            </div>
                        </div>
                        <li class="dropdown user user-menu">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <img src="{$app_url}/{$UPLOAD_PATH}{$_admin['photo']}.thumb.jpg"
                                    onerror="this.src='{$app_url}/{$UPLOAD_PATH}/admin.default.png'" class="user-image"
                                    alt="Avatar">
                                <span class="hidden-xs">{$_admin['fullname']}</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li class="user-header">
                                    <img src="{$app_url}/{$UPLOAD_PATH}{$_admin['photo']}.thumb.jpg"
                                        onerror="this.src='{$app_url}/{$UPLOAD_PATH}/admin.default.png'" class="img-circle"
                                        alt="Avatar">
                                    <p>
                                        {$_admin['fullname']}
                                        <small>{Lang::T($_admin['user_type'])}</small>
                                    </p>
                                </li>
                                <li class="user-body">
                                    <div class="row">
                                        <div class="col-xs-7 text-center text-sm">
                                            <a href="{Text::url('settings/change-password')}">
                                                <i class="fas fa-key"></i>
                                                {Lang::T('Change Password')}
                                            </a>
                                        </div>
                                        <div class="col-xs-5 text-center text-sm">
                                            <a href="{Text::url('settings/users-view/', $_admin['id'])}">
                                                <i class="fas fa-user-cog"></i> {Lang::T('My Account')}
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <li class="user-footer">
                                    <div class="pull-right">
                                        <a href="{Text::url('logout')}" class="btn btn-default btn-flat">
                                            <i class="fas fa-sign-out-alt"></i> {Lang::T('Logout')}
                                        </a>
                                    </div>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>
        <aside class="main-sidebar">
            <section class="sidebar">
                <ul class="sidebar-menu" data-widget="tree">
                    <li {if $_system_menu eq 'dashboard' }class="active" {/if}>
                        <a href="{Text::url('dashboard')}">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>{Lang::T('Dashboard')}</span>
                        </a>
                    </li>
                    {$_MENU_AFTER_DASHBOARD}
                    <li class="{if $_routes[0] eq 'onlineusers'}active{/if} treeview">
                        <a href="#">
                            <i class="fas fa-signal"></i> <span>{Lang::T('Online Clients')}</span>
                            <span class="pull-right-container">
                                <i class="fas fa-angle-left pull-right"></i>
                            </span>
                        </a>
                        <ul class="treeview-menu">
                            <li {if $_routes[0] eq 'onlineusers' && $_routes[1] eq 'hotspot'}class="active"{/if}>
                                <a href="{Text::url('onlineusers/hotspot')}"><i class="fas fa-wifi"></i> {Lang::T('Hotspot Client')}</a>
                            </li>
                            <li {if $_routes[0] eq 'onlineusers' && $_routes[1] eq 'pppoe'}class="active"{/if}>
                                <a href="{Text::url('onlineusers/pppoe')}"><i class="fas fa-ethernet"></i> {Lang::T('Pppoe Clients')}</a>
                            </li>
                        </ul>
                    </li>
                    <li {if $_system_menu eq 'customers' }class="active" {/if}>
                        <a href="{Text::url('customers')}">
                            <i class="fas fa-users"></i>
                            <span>{Lang::T('Clients')}</span>
                        </a>
                    </li>
                    {$_MENU_AFTER_CUSTOMERS}
                    {if !in_array($_admin['user_type'],['Report'])}
                        <li class="{if $_routes[0] eq 'plan' || $_routes[0] eq 'coupons'}active{/if} treeview">
                            <a href="#">
                                <i class="fas fa-concierge-bell"></i> <span>{Lang::T('Services')}</span>
                                <span class="pull-right-container">
                                    <i class="fas fa-angle-left pull-right"></i>
                                </span>
                            </a>
                            <ul class="treeview-menu">
                                <li {if $_routes[1] eq 'list' }class="active" {/if}>
                                    <a href="{Text::url('plan/list')}">
                                        <i class="fas fa-user-check"></i> {Lang::T('Active Clients')}
                                    </a>
                                </li>
                                {if $_c['disable_voucher'] != 'yes'}
                                    <li {if $_routes[1] eq 'refill' }class="active" {/if}>
                                        <a href="{Text::url('plan/refill')}">
                                            <i class="fas fa-redo"></i> {Lang::T('Refill Client')}
                                        </a>
                                    </li>
                                {/if}
                                {if $_c['disable_voucher'] != 'yes'}
                                    <li {if $_routes[1] eq 'voucher' }class="active" {/if}>
                                        <a href="{Text::url('plan/voucher')}">
                                            <i class="fas fa-ticket-alt"></i> {Lang::T('Vouchers')}
                                        </a>
                                    </li>
                                {/if}
                                {if $_c['enable_coupons'] == 'yes'}
                                    <li {if $_routes[0] eq 'coupons' }class="active" {/if}>
                                        <a href="{Text::url('coupons')}">
                                            <i class="fas fa-tags"></i> {Lang::T('Coupons')}
                                        </a>
                                    </li>
                                {/if}
                                <li {if $_routes[1] eq 'recharge' }class="active" {/if}>
                                    <a href="{Text::url('plan/recharge')}">
                                        <i class="fas fa-battery-full"></i> {Lang::T('Recharge Client')}
                                    </a>
                                </li>
                                {if $_c['enable_balance'] == 'yes'}
                                    <li {if $_routes[1] eq 'deposit' }class="active" {/if}>
                                        <a href="{Text::url('plan/deposit')}">
                                            <i class="fas fa-wallet"></i> {Lang::T('Refill Balance')}
                                        </a>
                                    </li>
                                {/if}
                                {$_MENU_SERVICES}
                            </ul>
                        </li>
                    {/if}
                    {$_MENU_AFTER_SERVICES}
                    {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                        <li class="{if $_system_menu eq 'services'}active{/if} treeview">
                            <a href="#">
                                <i class="fas fa-wifi"></i> <span>{Lang::T('Internet Plans')}</span>
                                <span class="pull-right-container">
                                    <i class="fas fa-angle-left pull-right"></i>
                                </span>
                            </a>
                            <ul class="treeview-menu">
                                <li {if $_routes[1] eq 'hotspot' }class="active" {/if}>
                                    <a href="{Text::url('services/hotspot')}">
                                        <i class="fas fa-hotdog"></i> Hotspot
                                    </a>
                                </li>
                                <li {if $_routes[1] eq 'pppoe' }class="active" {/if}>
                                    <a href="{Text::url('services/pppoe')}">
                                        <i class="fas fa-ethernet"></i> PPPOE
                                    </a>
                                </li>
                                <li {if $_routes[1] eq 'list' }class="active" {/if}>
                                    <a href="{Text::url('bandwidth/list')}">
                                        <i class="fas fa-tachometer-alt"></i> Bandwidth
                                    </a>
                                </li>
                                {if $_c['enable_balance'] == 'yes'}
                                    <li {if $_routes[1] eq 'balance' }class="active" {/if}>
                                        <a href="{Text::url('services/balance')}">
                                            <i class="fas fa-balance-scale"></i> {Lang::T('Customer Balance')}
                                        </a>
                                    </li>
                                {/if}
                                {$_MENU_PLANS}
                            </ul>
                        </li>
                    {/if}
                    {$_MENU_AFTER_PLANS}
                    <li class="{if $_system_menu eq 'reports'}active{/if} treeview">
                        {if in_array($_admin['user_type'],['SuperAdmin','Admin', 'Report'])}
                            <a href="#">
                                <i class="fas fa-chart-line"></i> <span>{Lang::T('Reports')}</span>
                                <span class="pull-right-container">
                                    <i class="fas fa-angle-left pull-right"></i>
                                </span>
                            </a>
                        {/if}
                        <ul class="treeview-menu">
                            <li {if $_routes[1] eq 'today' }class="active" {/if}>
                                <a href="{Text::url('reports/today')}">
                                    <i class="fas fa-calendar-day text-green"></i> {Lang::T('Daily reports')}
                                </a>
                            </li>
                           
                            <li {if $_routes[1] eq 'activation' }class="active" {/if}>
                                <a href="{Text::url('reports/by-period')}">
                                    <i class="fas fa-history"></i> {Lang::T('Period reports')}
                                </a>
                            </li>
                            {$_MENU_REPORTS}
                        </ul>
                    </li>
                    {$_MENU_AFTER_REPORTS}
                    <li {if $_system_menu eq 'transactions' }class="active" {/if}>
                        <a href="{Text::url('transactions')}">
                            <i class="fas fa-money-check-alt"></i>
                            <span>Payment Transactions</span>
                        </a>
                    </li>
                    <li class="{if $_system_menu eq 'message'}active{/if} treeview">
                        <a href="#">
                            <i class="fas fa-comments"></i> <span>{Lang::T('Send Message')}</span>
                            <span class="pull-right-container">
                                <i class="fas fa-angle-left pull-right"></i>
                            </span>
                        </a>
                        <ul class="treeview-menu">
                            <li {if $_routes[1] eq 'send' }class="active" {/if}>
                                <a href="{Text::url('message/send')}">
                                    <i class="fas fa-user"></i> {Lang::T('Single Client')}
                                </a>
                            </li>
                            <li {if $_routes[1] eq 'send_bulk' }class="active" {/if}>
                                <a href="{Text::url('message/send_bulk')}">
                                    <i class="fas fa-users"></i> {Lang::T('Bulk Clients')}
                                </a>
                            </li>
                            {$_MENU_MESSAGE}
                        </ul>
                    </li>
                    {$_MENU_AFTER_MESSAGE}
                    {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                        <li class="{if $_system_menu eq 'network'}active{/if} treeview">
                            <a href="#">
                                <i class="fas fa-network-wired"></i> <span>{Lang::T('Network')}</span>
                                <span class="pull-right-container">
                                    <i class="fas fa-angle-left pull-right"></i>
                                </span>
                            </a>
                            <ul class="treeview-menu">
                                <li {if $_routes[0] eq 'routers' and $_routes[1] eq '' }class="active" {/if}>
                                    <a href="{Text::url('routers')}">
                                        <i class="fas fa-router"></i> Routers
                                    </a>
                                </li>
                                <li {if $_routes[0] eq 'pool' and $_routes[1] eq 'list' }class="active" {/if}>
                                    <a href="{Text::url('pool/list')}">
                                        <i class="fas fa-list-ol"></i> IP Pool
                                    </a>
                                </li>
                                
                                {$_MENU_NETWORK}
                            </ul>
                        </li>
                        {$_MENU_AFTER_NETWORKS}
                        {if $_c['radius_enable']}
                            <li class="{if $_system_menu eq 'radius'}active{/if} treeview">
                                <a href="#">
                                    <i class="fas fa-server"></i> <span>{Lang::T('Radius')}</span>
                                    <span class="pull-right-container">
                                        <i class="fas fa-angle-left pull-right"></i>
                                    </span>
                                </a>
                                <ul class="treeview-menu">
                                    <li {if $_routes[0] eq 'radius' and $_routes[1] eq 'nas-list' }class="active" {/if}>
                                        <a href="{Text::url('radius/nas-list')}">
                                            <i class="fas fa-database"></i> {Lang::T('Radius NAS')}
                                        </a>
                                    </li>
                                    {$_MENU_RADIUS}
                                </ul>
                            </li>
                        {/if}
                        {$_MENU_AFTER_RADIUS}
                    {/if}
                    {$_MENU_AFTER_PAGES}
                    <li class="{if $_system_menu eq 'settings' || $_system_menu eq 'paymentgateway' }active{/if} treeview">
                        <a href="#">
                            <i class="fas fa-cogs"></i> <span>{Lang::T('Settings')}</span>
                            <span class="pull-right-container">
                                <i class="fas fa-angle-left pull-right"></i>
                            </span>
                        </a>
                        <ul class="treeview-menu">
                            {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                                <li {if $_routes[1] eq 'app' }class="active" {/if}>
                                    <a href="{Text::url('settings/app')}">
                                        <i class="fas fa-sliders-h"></i> {Lang::T('General Settings')}
                                    </a>
                                </li>
                                <li {if $_routes[1] eq 'notifications' }class="active" {/if}>
                                    <a href="{Text::url('settings/notifications')}">
                                        <i class="fas fa-bell"></i> {Lang::T('User Notification')}
                                    </a>
                                </li>
                            {/if}
                            {if in_array($_admin['user_type'],['SuperAdmin','Admin','Agent'])}
                                <li {if $_routes[1] eq 'users' }class="active" {/if}>
                                    <a href="{Text::url('settings/users')}">
                                        <i class="fas fa-user-shield"></i> {Lang::T('Administrator Users')}
                                    </a>
                                </li>
                            {/if}
                            {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}

                                <li {if $_system_menu eq 'paymentgateway' }class="active" {/if}>
                                    <a href="{Text::url('paymentgateway')}">
                                        <i class="fas fa-credit-card"></i> <span class="text">{Lang::T('Payment Gateway')}</span>
                                    </a>
                                </li>
                                {$_MENU_SETTINGS}
                            {/if}
                        </ul>
                    </li>
                    {$_MENU_AFTER_SETTINGS}
                    {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                        <li class="{if $_system_menu eq 'logs' }active{/if} treeview">
                            <a href="#">
                                <i class="fas fa-history"></i> <span>{Lang::T('Logs')}</span>
                                <span class="pull-right-container">
                                    <i class="fas fa-angle-left pull-right"></i>
                                </span>
                            </a>
                            <ul class="treeview-menu">
                                <li {if $_routes[1] eq 'list' }class="active" {/if}>
                                    <a href="{Text::url('logs/allxsys')}">
                                        <i class="fas fa-code"></i> Rayprotech
                                    </a>
                                </li>
                                {if $_c['radius_enable']}
                                    <li {if $_routes[1] eq 'radius' }class="active" {/if}>
                                        <a href="{Text::url('logs/radius')}">
                                            <i class="fas fa-server"></i> Radius
                                        </a>
                                    </li>
                                {/if}
                            </ul>
                        </li>
                    {/if}
                    {$_MENU_AFTER_LOGS}
                    {$_MENU_AFTER_COMMUNITY}
                </ul>
            </section>
        </aside>

        {if $_c['maintenance_mode'] == 1}
            <div class="notification-top-bar">
                <p>
                    <i class="fas fa-tools"></i> {Lang::T('The website is currently in maintenance mode, this means that some or all functionality may be unavailable to regular users during this time.')}
                    <small> &nbsp;&nbsp;
                        <a href="{Text::url('settings/maintenance')}">
                            <i class="fas fa-power-off"></i> {Lang::T('Turn Off')}
                        </a>
                    </small>
                </p>
            </div>
        {/if}

        <div class="content-wrapper">
            <section class="content-header">
                <h1>
                    <i class="fas fa-{if $_title|strpos:'Dashboard' !== false}tachometer-alt{elseif $_title|strpos:'Customer' !== false}users{elseif $_title|strpos:'Report' !== false}chart-line{elseif $_title|strpos:'Settings' !== false}cogs{else}file{/if}"></i>
                    {$_title}
                </h1>
            </section>

            <section class="content">
                {if isset($notify)}
                    <script>
                        // Display SweetAlert toast notification
                        Swal.fire({
                            icon: '{if $notify_t == "s"}success{else}error{/if}',
                            title: '{$notify}',
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 5000,
                            timerProgressBar: true,
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer)
                                toast.addEventListener('mouseleave', Swal.resumeTimer)
                            }
                        });
                    </script>
                {/if}