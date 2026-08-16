{include file="sections/header.tpl"}

<style>
    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        /* Make modal responsive */
        .modal-dialog {
            width: 95% !important;
            max-width: none !important;
            margin: 10px auto !important;
        }
        
        /* Stack voucher details and SMS vertically on mobile */
        #voucher-content .row {
            display: flex;
            flex-direction: column;
        }
        
        #voucher-content .col-md-9,
        #voucher-content .col-md-3 {
            width: 100% !important;
            float: none !important;
        }
        
        /* Reduce padding and font sizes for mobile */
        .modal-body {
            padding: 10px !important;
        }
        
        .panel-body {
            padding: 10px !important;
        }
        
        #voucher-print-content {
            font-size: 10px !important;
            padding: 10px !important;
            max-height: 200px !important;
        }
        
        /* Make buttons and inputs more touch-friendly */
        .btn {
            min-height: 44px;
        }
        
        .form-control {
            min-height: 44px;
        }
        
        /* Reduce table font size */
        .table {
            font-size: 12px;
        }
        
        .table th, .table td {
            padding: 4px !important;
        }
        
        /* Make filters more compact */
        .form-control, .input-group-addon {
            font-size: 12px;
        }
        
        /* Reduce panel title sizes */
        .panel-title {
            font-size: 14px !important;
        }
        
        h4.panel-title {
            font-size: 14px !important;
        }
        
        /* Keep tabs side by side on mobile */
        #voucherTabs {
            display: flex !important;
            flex-wrap: nowrap !important;
        }
        
        #voucherTabs li {
            flex: 1 !important;
            text-align: center !important;
        }
        
        #voucherTabs li a {
            padding: 8px 4px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
        }
        
        /* Fix action buttons to be consistent and side by side */
        .table .btn {
            min-height: 28px !important;
            padding: 2px 6px !important;
            font-size: 10px !important;
            margin: 1px !important;
        }
        
        .table .btn-xs {
            min-height: 24px !important;
            padding: 1px 4px !important;
            font-size: 9px !important;
        }
        
        /* Make sure buttons stay side by side */
        .table td:last-child {
            white-space: nowrap !important;
        }
        
        /* Fix delete selected button */
        .row .btn-danger {
            font-size: 12px !important;
            padding: 6px 12px !important;
        }
        
        /* Fix delete button in panel header */
        .panel-heading .btn-group {
            margin-top: -5px !important;
        }
        
        .panel-heading .btn-xs {
            font-size: 11px !important;
            padding: 8px 12px !important;
            white-space: nowrap !important;
            min-height: 36px !important;
            line-height: 1.2 !important;
            border-radius: 4px !important;
        }
        
        /* Specific mobile styling for header buttons */
        .panel-heading .btn-group .btn {
            margin: 2px !important;
        }
        
        /* Make panel title more responsive */
        .panel-title {
            flex-direction: column !important;
            align-items: stretch !important;
        }
        
        .panel-title > div:first-child {
            margin-bottom: 8px !important;
        }
        
        .panel-title .btn-group {
            margin-left: 0 !important;
            width: 100% !important;
        }
        
        .panel-title .btn-group .btn {
            flex: 1 !important;
        }
        
        /* Hide mobile text on large screens */
        .delete-btn-mobile {
            display: none !important;
        }
        
        /* Show mobile text on small screens */
        @media (max-width: 767px) {
            .delete-btn-desktop {
                display: none !important;
            }
            
            .delete-btn-mobile {
                display: inline !important;
            }
        }
        
        /* Mobile filter layout: search + one dropdown, then two dropdowns, then buttons */
        @media (max-width: 767px) {
            .mobile-filter-row1 .col-lg-2:nth-child(1),
            .mobile-filter-row1 .col-lg-2:nth-child(2) {
                width: 50% !important;
                float: left !important;
            }
            
            .mobile-filter-row1 .col-lg-2:nth-child(3),
            .mobile-filter-row1 .col-lg-2:nth-child(4),
            .mobile-filter-row1 .col-lg-2:nth-child(5) {
                display: none !important;
            }
            
            .mobile-filter-row2,
            .mobile-filter-row3 {
                display: block !important;
                margin-top: 5px !important;
            }
            
            .mobile-filter-row2 > div,
            .mobile-filter-row3 > div {
                width: 50% !important;
                float: left !important;
                padding-left: 2px !important;
                padding-right: 2px !important;
            }
            
            .mobile-filter-row3 > div:first-child {
                width: 100% !important;
            }
        }
        
        @media (min-width: 768px) {
            .mobile-filter-row2,
            .mobile-filter-row3 {
                display: none !important;
            }
        }
    }
    
    /* Voucher Tab Styling */
    #voucherTabs {
        border-bottom: 2px solid #ddd;
        margin-bottom: 15px;
    }
    
    #voucherTabs li a {
        color: #555;
        border: none;
        border-radius: 0;
        padding: 12px 20px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    #voucherTabs li.active a {
        color: #337ab7;
        border-bottom: 3px solid #337ab7;
        background: transparent;
    }
    
    #voucherTabs li a:hover {
        background: #f5f5f5;
        border-color: transparent;
    }
    
    .badge {
        font-size: 10px;
        padding: 2px 6px;
        margin-left: 5px;
    }
    
    .badge-success {
        background-color: #5cb85c;
    }
    
    .badge-danger {
        background-color: #d9534f;
    }
    
    /* Table row transition */
    #datatable tbody tr {
        transition: opacity 0.2s ease;
    }
    
    /* Empty state styling */
    .empty-state-row td {
        font-style: italic;
        color: #999;
    }
    
    /* Status column hover effect */
    td[style*="background-color: black"] {
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    /* SMS Input Validation Styles */
    .valid-input {
        border-color: #5cb85c !important;
        box-shadow: 0 0 8px rgba(92, 184, 92, 0.4) !important;
        background-color: #f0fff0 !important;
    }
    
    .invalid-input {
        border-color: #d9534f !important;
        box-shadow: 0 0 8px rgba(217, 83, 79, 0.4) !important;
        background-color: #fff5f5 !important;
    }
    
    /* Enhanced Phone Input Styling */
    #sms-phone:focus {
        border-color: #4a90e2 !important;
        box-shadow: 0 0 15px rgba(74, 144, 226, 0.5) !important;
        background-color: white !important;
        transform: scale(1.02);
    }
    
    /* Send Button Hover Effects */
    #send-sms-btn:hover {
        background: linear-gradient(135deg, #286090, #337ab7) !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(51, 122, 183, 0.6) !important;
    }
    
    #send-sms-btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 8px rgba(51, 122, 183, 0.4) !important;
    }
    
    #send-sms-btn:disabled {
        background: #bbb !important;
        transform: none !important;
        box-shadow: none !important;
        cursor: not-allowed !important;
    }
    
    /* Country Display Animation */
    .country-display:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(51, 122, 183, 0.5) !important;
    }
    
    /* SMS Panel Styling - Blue System Theme */
    .panel-primary .panel-heading {
        background-color: #337ab7;
        border-color: #2e6da4;
        color: white;
    }
    
    .panel-primary .panel-title {
        font-size: 16px;
        font-weight: 600;
        color: white;
    }
    
    .input-group-lg .form-control {
        font-size: 16px;
        font-weight: 500;
        border: 2px solid #337ab7;
        transition: all 0.3s ease;
    }
    
    .input-group-lg .form-control:focus {
        border-color: #337ab7;
        box-shadow: 0 0 8px rgba(51, 122, 183, 0.3);
        outline: none;
    }
    
    .input-group-addon {
        font-weight: bold;
        font-size: 16px;
        border: 2px solid #337ab7;
    }
    
    .btn-primary {
        background-color: #337ab7;
        border-color: #2e6da4;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        background-color: #286090;
        border-color: #204d74;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    
    .btn-primary:focus {
        background-color: #286090;
        border-color: #204d74;
        box-shadow: 0 0 8px rgba(51, 122, 183, 0.4);
    }
    
    /* Modal sizing */
    .modal-xl {
        width: 95%;
        max-width: 1000px;
    }
    
    /* Enhanced alert styling */
    .alert-info {
        background-color: #d9edf7;
        border: 1px solid #bce8f1;
        border-radius: 6px;
    }
    
    /* Voucher details styling */
    .panel-default .panel-heading {
        background-color: #f5f5f5;
        border-bottom: 1px solid #ddd;
    }
    
    /* Professional spacing */
    .panel-body {
        background-color: #fafafa;
    }
    
    /* Input group styling improvements */
    .input-group-addon {
        border-right: none;
    }
    
    .input-group .form-control:first-child {
        border-left: 2px solid #337ab7;
    }
    
    /* Voucher code visibility styles */
    .voucher-code-hidden {
        background-color: black !important;
        color: black !important;
        transition: all 0.3s ease;
    }
    
    .voucher-code-visible {
        background-color: white !important;
        color: black !important;
        font-weight: bold !important;
        border: 1px solid #337ab7 !important;
        transition: all 0.3s ease;
    }
    
    .voucher-code-hidden:hover {
        background-color: white !important;
        color: black !important;
    }
    
    /* Toggle button styling */
    #toggle-voucher-codes {
        transition: all 0.3s ease;
    }
    
    #toggle-voucher-codes:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    /* Voucher panel header responsive layout */
    .voucher-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .header-text {
        flex: 1;
        min-width: 200px;
    }
    
    .header-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
    }
    
    .header-btn {
        white-space: nowrap;
        font-size: 12px;
        padding: 6px 10px;
        min-height: 32px;
        line-height: 1.3;
    }
    
    /* Mobile responsive header */
    @media (max-width: 767px) {
        .voucher-panel-header {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        
        .header-text {
            text-align: center;
            margin-bottom: 5px;
        }
        
        .header-buttons {
            width: 100%;
            justify-content: center;
            gap: 8px;
            flex-wrap: nowrap;
        }
        
        .header-btn {
            flex: 1;
            min-height: 44px !important;
            font-size: 12px !important;
            padding: 10px 6px !important;
            font-weight: 500;
            border-radius: 6px;
            white-space: nowrap;
        }
        
        .header-btn .glyphicon {
            font-size: 14px;
            margin-right: 3px;
        }
    }
    
    /* Desktop header button styling */
    @media (min-width: 768px) {
        .header-buttons {
            margin-left: 10px;
        }
        
        .header-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }
    }
</style>

<!-- voucher -->
<div class="row" style="padding: 5px">
    <div class="col-lg-3 col-lg-offset-9">
        <div class="btn-group btn-group-justified" role="group">
            {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                <div class="btn-group" role="group">
                    <a href="{Text::url('')}plan/add-voucher" class="btn btn-primary"><i class="ion ion-android-add"></i>
                        Create Voucher</a>
                </div>
            {/if}
            {if !in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                <div class="btn-group" role="group">
                    <a href="{Text::url('')}plan/refill" class="btn btn-success"><i class="ion ion-android-share"></i>
                        {Lang::T('Distribute Voucher')}</a>
                </div>
            {/if}
        </div>
    </div>
</div>
<div class="panel panel-hovered mb20 panel-primary">
    <div class="panel-heading">
        <div class="panel-title voucher-panel-header">
            <div class="header-text">
                {Lang::T('Voucher Management')}
                {if !in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                    <small class="text-muted">({$_admin['user_type']} - View & Distribute Only)</small>
                {/if}
            </div>
            <div class="header-buttons">
                <button class="btn btn-info btn-xs header-btn" id="toggle-voucher-codes" title="Show/Hide all voucher codes">
                    <span class="glyphicon glyphicon-eye-open" aria-hidden="true" id="toggle-icon"></span> 
                    <span id="toggle-text">Show All Codes</span>
                </button>
                {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                    <a class="btn btn-danger btn-xs header-btn" title="Remove used Voucher" href="{Text::url('')}plan/remove-voucher"
                        onclick="return ask(this, 'Delete all used voucher code more than 1 month?')">
                        <span class="glyphicon glyphicon-trash" aria-hidden="true"></span> {Lang::T('Delete')} &gt; 
                        <span class="delete-btn-desktop">1 Months</span>
                        <span class="delete-btn-mobile">1M</span>
                    </a>
                {/if}
            </div>
        </div>
    </div>
    
    <!-- Voucher Status Tabs -->
    <div class="panel-body" style="padding-bottom: 0;">
        <ul class="nav nav-tabs" id="voucherTabs" role="tablist">
            <li role="presentation" class="active">
                <a href="#not-used-vouchers" aria-controls="not-used-vouchers" role="tab" data-toggle="tab" onclick="showVoucherTab('not-used')">
                    <i class="fa fa-clock-o"></i> {Lang::T('Not Used Vouchers')} 
                    <span class="badge badge-success" id="not-used-count">0</span>
                </a>
            </li>
            <li role="presentation">
                <a href="#used-vouchers" aria-controls="used-vouchers" role="tab" data-toggle="tab" onclick="showVoucherTab('used')">
                    <i class="fa fa-check-circle"></i> {Lang::T('Used Vouchers')} 
                    <span class="badge badge-danger" id="used-count">0</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="panel-body">
        <!-- Tab Content -->
        <div class="tab-content" id="voucherTabContent">
            <!-- Not Used Vouchers Tab -->
            <div role="tabpanel" class="tab-pane active" id="not-used-vouchers">
                <form id="site-search" method="post" action="{Text::url('')}plan/voucher/">
                    <input type="hidden" name="status" value="0" id="search-status">
                    <div class="row mobile-filter-row1" style="padding: 5px">
                        <div class="col-lg-2">
                            <div class="input-group">
                                <div class="input-group-addon">
                                    <span class="fa fa-search"></span>
                                </div>
                                <input type="text" name="search" class="form-control" placeholder="{Lang::T('Code Voucher')}"
                                    value="{$search}" id="voucher-search">
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <select class="form-control" id="router" name="router">
                                <option value="">{Lang::T('Routers')}</option>
                                {foreach $routers as $r}
                                    <option value="{$r}" {if $router eq $r }selected{/if}>{$r}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <select class="form-control" id="plan" name="plan">
                                <option value="">{Lang::T('Plan Name')}</option>
                                {foreach $plans as $p}
                                    <option value="{$p['id']}" {if $plan eq $p['id'] }selected{/if}>{$p['name_plan']}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <select class="form-control" id="customer" name="customer">
                                <option value="">{Lang::T('Customer')}</option>
                                {foreach $customers as $c}
                                    <option value="{$c['user']}" {if $customer eq $c['user'] }selected{/if}>{$c['user']}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <div class="btn-group btn-group-justified" role="group">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-success btn-block" type="button" onclick="filterVouchers()"><span
                                            class="fa fa-search"></span></button>
                                </div>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-warning btn-block" type="button" onclick="clearFilters()" title="Clear Search Query">
                                        <span class="glyphicon glyphicon-remove-circle"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mobile-only second row: Plan + Customer -->
                    <div class="row mobile-filter-row2" style="padding: 2px; display: none;">
                        <div class="col-xs-6">
                            <select class="form-control" id="plan-mobile" name="plan">
                                <option value="">{Lang::T('Plan Name')}</option>
                                {foreach $plans as $p}
                                    <option value="{$p['id']}" {if $plan eq $p['id'] }selected{/if}>{$p['name_plan']}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-xs-6">
                            <select class="form-control" id="customer-mobile" name="customer">
                                <option value="">{Lang::T('Customer')}</option>
                                {foreach $customers as $c}
                                    <option value="{$c['user']}" {if $customer eq $c['user'] }selected{/if}>{$c['user']}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    
                    <!-- Mobile-only third row: Buttons -->
                    <div class="row mobile-filter-row3" style="padding: 2px; display: none;">
                        <div class="col-xs-12">
                            <div class="btn-group btn-group-justified" role="group">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-success" type="button" onclick="filterVouchersMobile()"><span
                                            class="fa fa-search"></span> Search</button>
                                </div>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-warning" type="button" onclick="clearFiltersMobile()" title="Clear">
                                        <span class="glyphicon glyphicon-remove-circle"></span> Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Used Vouchers Tab -->
            <div role="tabpanel" class="tab-pane" id="used-vouchers">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> 
                    <strong>Used Vouchers:</strong> These vouchers have already been redeemed and cannot be used again.
                    Use the same search filters to find specific used vouchers.
                </div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <div style="margin-left: 5px; margin-right: 5px;">&nbsp;
            <table id="datatable" class="table table-bordered table-striped table-condensed">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>ID</th>
                        <th>{Lang::T('Type')}</th>
                        <th>{Lang::T('Routers')}</th>
                        <th>{Lang::T('Plan Name')}</th>
                        <th>{Lang::T('Code Voucher')}</th>
                        <th>{Lang::T('Status Voucher')}</th>
                        <th>{Lang::T('Customer')}</th>
                        <th>{Lang::T('Create Date')}</th>
                        <th>{Lang::T('Used Date')}</th>
                        <th>{Lang::T('Generated By')}</th>
                        <th>{Lang::T('Manage')}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $d as $ds}
                        <tr {if $ds['status'] eq '1' }class="danger" {/if}>
                            <td><input type="checkbox" name="voucher_ids[]" value="{$ds['id']}"></td>
                            <td>{$ds['id']}</td>
                            <td>{$ds['type']}</td>
                            <td>{$ds['routers']}</td>
                            <td>{$ds['name_plan']}</td>
                            <td class="voucher-code-cell voucher-code-hidden" data-code="{$ds['code']}"
                                onmouseleave="if(!window.voucherCodesVisible) this.className = this.className.replace('voucher-code-visible', 'voucher-code-hidden');"
                                onmouseenter="if(!window.voucherCodesVisible) this.className = this.className.replace('voucher-code-hidden', 'voucher-code-visible');">
                                {$ds['code']}</td>
                            <td>{if $ds['status'] eq '0'} <label class="btn-tag btn-tag-success"> Not Use
                                </label> {else} <label class="btn-tag btn-tag-danger">Used</label>
                                {/if}</td>
                            <td>{if $ds['user'] eq '0'} -
                                {else}<a href="{Text::url('')}customers/viewu/{$ds['user']}">{$ds['user']}</a>
                                {/if}</td>
                            <td>{if $ds['created_at']}{Lang::dateTimeFormat($ds['created_at'])}{/if}</td>
                            <td>{if $ds['used_date']}{Lang::dateTimeFormat($ds['used_date'])}{/if}</td>
                            <td>{if $ds['generated_by']}
                                    <a
                                        href="{Text::url('')}settings/users-view/{$ds['generated_by']}">{$admins[$ds['generated_by']]}</a>
                                {else} -
                                {/if}
                            </td>
                            <td>
                                {if $ds['status'] neq '1'}
                                    <button onclick="viewVoucherDetails({$ds['id']})" id="{$ds['id']}" style="margin: 0px;"
                                        class="btn btn-success btn-xs">&nbsp;&nbsp;{Lang::T('View')}&nbsp;&nbsp;</button>
                                {/if}
                                {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                                    <a href="{Text::url('')}plan/voucher-delete/{$ds['id']}" id="{$ds['id']}"
                                        class="btn btn-danger btn-xs" onclick="return ask(this, '{Lang::T('Delete')}?')"><i
                                            class="glyphicon glyphicon-trash"></i></a>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Voucher Details Modal -->
<div class="modal fade" id="voucherDetailsModal" tabindex="-1" role="dialog" aria-labelledby="voucherDetailsModalLabel">
    <div class="modal-dialog modal-xl" role="document" style="width: 95%; max-width: 1200px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="voucherDetailsModalLabel">
                    <i class="fa fa-ticket"></i> {Lang::T('Voucher Details')}
                </h4>
            </div>
            <div class="modal-body">
                <div id="voucher-loading" class="text-center" style="padding: 30px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p class="text-muted">Loading voucher details...</p>
                </div>
                <div id="voucher-content" style="display: none;">
                    <div class="row">
                        <div class="col-md-9">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title"><i class="fa fa-ticket"></i> Voucher Details</h4>
                                </div>
                                <div class="panel-body" style="padding: 20px;">
                                    <pre id="voucher-print-content" style="background: #f8f8f8; border: 1px solid #ddd; padding: 20px; border-radius: 6px; font-size: 12px; line-height: 1.4; max-height: 300px; overflow-y: auto; font-family: 'Courier New', monospace;"></pre>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title"><i class="fa fa-mobile"></i> Send SMS</h4>
                                </div>
                                <div class="panel-body" style="padding: 15px;">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label for="sms-phone" style="font-weight: 500; margin-bottom: 0;">Enter Phone Number:</label>
                                        </div>
                                    </div>
                                    <div class="row" style="margin-top: 8px;">
                                        <div class="col-md-12">
                                            <input type="tel" class="form-control" id="sms-phone" 
                                                   placeholder="0712345678" 
                                                   maxlength="10">
                                        </div>
                                    </div>
                                    <div class="row" style="margin-top: 10px;">
                                        <div class="col-md-12">
                                            <button type="button" class="btn btn-primary btn-block btn-sm" id="send-sms-btn">
                                                <i class="fa fa-paper-plane"></i> Send SMS
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

{if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
<div class="row" style="padding: 5px">
    <div class="col-lg-3 col-lg-offset-9">
        <div class="btn-group btn-group-justified" role="group">
            <div class="btn-group" role="group">
                <button id="deleteSelectedVouchers" class="btn btn-danger">{Lang::T('Delete
                Selected')}</button>
            </div>
        </div>
    </div>
</div>
{else}
<div class="row" style="padding: 5px">
    <div class="col-lg-12">
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> 
            <strong>Note:</strong> As a {$_admin['user_type']}, you can view and distribute vouchers but cannot create or delete them. 
            Contact your administrator for voucher creation needs.
        </div>
    </div>
</div>
{/if}
{include file="pagination.tpl"}

<script>
    // Global variables for voucher management
    let currentTab = 'not-used';
    let allVouchers = [];
    window.voucherCodesVisible = false; // Track visibility state
    
    // Initialize the voucher management system
    document.addEventListener('DOMContentLoaded', function() {
        initializeVoucherTabs();
        loadVoucherData();
        initializeVoucherCodeToggle();
    });
    
    function initializeVoucherTabs() {
        // Set default tab
        showVoucherTab('not-used');
        updateVoucherCounts();
    }
    
    function showVoucherTab(tabType) {
        currentTab = tabType;
        
        // Update search form status
        document.getElementById('search-status').value = tabType === 'not-used' ? '0' : '1';
        
        // Filter and display vouchers based on tab
        filterVoucherRows();
        
        // Update tab appearance
        updateTabAppearance(tabType);
    }
    
    function updateTabAppearance(activeTab) {
        // Remove active class from all tabs
        document.querySelectorAll('#voucherTabs li').forEach(function(tab) {
            tab.classList.remove('active');
        });
        
        // Add active class to current tab
        if (activeTab === 'not-used') {
            document.querySelector('#voucherTabs li:first-child').classList.add('active');
        } else {
            document.querySelector('#voucherTabs li:last-child').classList.add('active');
        }
    }
    
    function filterVoucherRows() {
        const table = document.getElementById('datatable');
        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            const statusCell = row.cells[6]; // Status column
            const isUsed = statusCell.textContent.trim().includes('Used');
            
            if (currentTab === 'not-used' && !isUsed) {
                row.style.display = '';
                visibleCount++;
            } else if (currentTab === 'used' && isUsed) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update empty state
        updateEmptyState(visibleCount);
    }
    
    function updateEmptyState(visibleCount) {
        const table = document.getElementById('datatable');
        let emptyRow = table.querySelector('.empty-state-row');
        
        // Remove existing empty state
        if (emptyRow) {
            emptyRow.remove();
        }
        
        // Add empty state if no vouchers visible
        if (visibleCount === 0) {
            const tbody = table.querySelector('tbody');
            const emptyRow = tbody.insertRow();
            emptyRow.className = 'empty-state-row';
            const emptyCell = emptyRow.insertCell();
            emptyCell.colSpan = 12;
            emptyCell.className = 'text-center text-muted';
            emptyCell.style.padding = '20px';
            emptyCell.innerHTML = '<i class="fa fa-info-circle"></i> No ' + (currentTab === 'not-used' ? 'unused' : 'used') + ' vouchers found.';
        }
    }
    
    function updateVoucherCounts() {
        const table = document.getElementById('datatable');
        const rows = table.querySelectorAll('tbody tr:not(.empty-state-row)');
        let notUsedCount = 0;
        let usedCount = 0;
        
        rows.forEach(function(row) {
            const statusCell = row.cells[6]; // Status column
            const isUsed = statusCell.textContent.trim().includes('Used');
            
            if (isUsed) {
                usedCount++;
            } else {
                notUsedCount++;
            }
        });
        
        // Update badges
        document.getElementById('not-used-count').textContent = notUsedCount;
        document.getElementById('used-count').textContent = usedCount;
    }
    
    function filterVouchers() {
        // Get filter values
        const search = document.getElementById('voucher-search').value.toLowerCase();
        const router = document.getElementById('router').value;
        const plan = document.getElementById('plan').value;
        const customer = document.getElementById('customer').value;
        
        const table = document.getElementById('datatable');
        const rows = table.querySelectorAll('tbody tr:not(.empty-state-row)');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            const statusCell = row.cells[6]; // Status column
            const isUsed = statusCell.textContent.trim().includes('Used');
            
            // Check if row matches current tab
            const matchesTab = (currentTab === 'not-used' && !isUsed) || (currentTab === 'used' && isUsed);
            
            if (!matchesTab) {
                row.style.display = 'none';
                return;
            }
            
            // Apply additional filters
            let matches = true;
            
            // Search filter (voucher code)
            if (search && !row.cells[5].textContent.toLowerCase().includes(search)) {
                matches = false;
            }
            
            // Router filter
            if (router && row.cells[3].textContent !== router) {
                matches = false;
            }
            
            // Plan filter
            if (plan && !row.cells[4].textContent.includes(plan)) {
                matches = false;
            }
            
            // Customer filter
            if (customer && !row.cells[7].textContent.includes(customer)) {
                matches = false;
            }
            
            if (matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        updateEmptyState(visibleCount);
    }
    
    function clearFilters() {
        // Clear all filter inputs
        document.getElementById('voucher-search').value = '';
        document.getElementById('router').selectedIndex = 0;
        document.getElementById('plan').selectedIndex = 0;
        document.getElementById('customer').selectedIndex = 0;
        
        // Clear mobile filters too
        if (document.getElementById('plan-mobile')) {
            document.getElementById('plan-mobile').selectedIndex = 0;
        }
        if (document.getElementById('customer-mobile')) {
            document.getElementById('customer-mobile').selectedIndex = 0;
        }
        
        // Reapply tab filter only
        filterVoucherRows();
    }
    
    // Mobile filter functions
    function filterVouchersMobile() {
        // Sync mobile values to main filters
        var planMobile = document.getElementById('plan-mobile');
        var customerMobile = document.getElementById('customer-mobile');
        
        if (planMobile) {
            document.getElementById('plan').value = planMobile.value;
        }
        if (customerMobile) {
            document.getElementById('customer').value = customerMobile.value;
        }
        
        // Use the main filter function
        filterVouchers();
    }
    
    function clearFiltersMobile() {
        // Clear all filters including mobile ones
        clearFilters();
    }
    
    function loadVoucherData() {
        // Initialize counts and filters
        updateVoucherCounts();
        filterVoucherRows();
    }

    function deleteVouchers(voucherIds) {
        if (voucherIds.length > 0) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'You won\'t be able to revert this!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '{Text::url('')}plan/voucher-delete-many', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            var response = JSON.parse(xhr.responseText);

                            if (response.status === 'success') {
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload(); // Reload the page after confirmation
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to delete vouchers. Please try again.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    };
                    xhr.send('voucherIds=' + JSON.stringify(voucherIds));
                }
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: 'No vouchers selected to delete.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    }

    // Example usage for selected vouchers
    document.getElementById('deleteSelectedVouchers').addEventListener('click', function() {
        var selectedVouchers = [];
        document.querySelectorAll('input[name="voucher_ids[]"]:checked').forEach(function(checkbox) {
            selectedVouchers.push(checkbox.value);
        });

        if (selectedVouchers.length > 0) {
            deleteVouchers(selectedVouchers);
        } else {
            Swal.fire({
                title: 'Error!',
                text: 'Please select at least one voucher to delete.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });

    document.querySelectorAll('.delete-voucher').forEach(function(button) {
        button.addEventListener('click', function() {
            var voucherId = this.getAttribute('data-id');
            deleteVouchers([voucherId]);
        });
    });


    // Select or deselect all checkboxes
    document.getElementById('select-all').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="voucher_ids[]"]');
        for (var checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });

    // Voucher details modal functionality
    function viewVoucherDetails(voucherId) {
        // Show modal and loading state
        $('#voucherDetailsModal').modal('show');
        $('#voucher-loading').show();
        $('#voucher-content').hide();
        
        // Make AJAX request to get voucher details
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '{Text::url('')}plan/voucher-details-ajax/' + voucherId, true);
        xhr.onload = function() {
            $('#voucher-loading').hide();
            
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    
                    if (response.status === 'success') {
                        // Populate modal with voucher data
                        document.getElementById('voucher-print-content').textContent = response.content;
                        
                        // Update country code display if provided
                        if (response.country_code) {
                            window.currentCountryCode = response.country_code;
                        } else {
                            window.currentCountryCode = '254'; // Default to Kenya
                        }
                        
                        // Store voucher data for SMS sending
                        window.currentVoucherData = {
                            id: response.voucher_id,
                            content: response.content
                        };
                        
                        $('#voucher-content').show();
                    } else {
                        showErrorInModal(response.message || 'Failed to load voucher details');
                    }
                } catch (e) {
                    showErrorInModal('Invalid response from server');
                }
            } else {
                showErrorInModal('Failed to load voucher details. Please try again.');
            }
        };
        
        xhr.onerror = function() {
            $('#voucher-loading').hide();
            showErrorInModal('Network error. Please check your connection and try again.');
        };
        
        xhr.send();
    }
    
    function showErrorInModal(message) {
        $('#voucher-content').html('<div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> ' + message + '</div>').show();
    }
    
    // SMS and modal event listeners
    $(document).ready(function() {
        // SMS sending functionality
        $('#send-sms-btn').on('click', function() {
            var phone = $('#sms-phone').val().trim();
            var button = $(this);
            
            if (!phone) {
                showSMSAlert('error', 'Please enter a phone number');
                $('#sms-phone').focus();
                return;
            }
            
            // Validate phone number format (must start with 07 or 01 and be 10 digits)
            if (!/^0[17][0-9]{8}$/.test(phone)) {
                showSMSAlert('error', 'Phone number must be 10 digits starting with 07 or 01 (e.g., 0712345678)');
                $('#sms-phone').focus();
                return;
            }
            
            if (!window.currentVoucherData) {
                showSMSAlert('error', 'No voucher data available');
                return;
            }
            
            // Convert phone number format for sending (remove 0, add country code)
            var countryCode = window.currentCountryCode || '254';
            var phoneWithoutZero = phone.substring(1); // Remove leading 0
            var fullPhoneNumber = '+' + countryCode + phoneWithoutZero;
            
            // Disable button and show loading state
            button.prop('disabled', true);
            var originalHtml = button.html();
            button.html('<i class="fa fa-spinner fa-spin"></i> Sending SMS...');
            
            // Send AJAX request
            $.ajax({
                url: '{Text::url('')}plan/send-voucher-sms',
                method: 'POST',
                data: {
                    voucher_id: window.currentVoucherData.id,
                    phone: fullPhoneNumber,
                    custom_message: '' // Using default message
                },
                success: function(response) {
                    if (response.status === 'success') {
                        showSMSAlert('success', 'SMS sent successfully to ' + phone);
                        $('#sms-phone').val(''); // Clear phone input
                    } else {
                        showSMSAlert('error', response.message || 'Failed to send SMS');
                    }
                },
                error: function(xhr, status, error) {
                    showSMSAlert('error', 'Network error: Unable to send SMS');
                },
                complete: function() {
                    // Re-enable button
                    button.prop('disabled', false);
                    button.html(originalHtml);
                }
            });
        });
        
        // Phone number input validation and formatting
        $('#sms-phone').on('input', function() {
            var value = this.value;
            
            // Remove any non-digit characters
            value = value.replace(/[^0-9]/g, '');
            
            // Limit to 10 digits
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            // Ensure it starts with 0 if user enters something
            if (value.length > 0 && value.charAt(0) !== '0') {
                value = '0' + value;
            }
            
            // Ensure second digit is 7 or 1 if we have at least 2 digits
            if (value.length >= 2 && value.charAt(1) !== '7' && value.charAt(1) !== '1') {
                if (value.charAt(1) === '2' || value.charAt(1) === '3' || value.charAt(1) === '4') {
                    // Likely trying to enter 07... format
                    value = '07' + value.substring(2);
                } else {
                    // Default to 07
                    value = '07' + value.substring(2);
                }
            }
            
            this.value = value;
            
            // Visual feedback for validation
            if (value.length === 10 && /^0[17][0-9]{8}$/.test(value)) {
                $(this).removeClass('invalid-input').addClass('valid-input');
            } else if (value.length > 0) {
                $(this).removeClass('valid-input').addClass('invalid-input');
            } else {
                $(this).removeClass('valid-input invalid-input');
            }
        });
        
        // Allow Enter key to send SMS
        $('#sms-phone').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                $('#send-sms-btn').click();
            }
        });
    });
    
    // Function to show SMS alerts
    function showSMSAlert(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">' +
                       '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                       '<span aria-hidden="true">&times;</span>' +
                       '</button>' +
                       '<i class="fa ' + iconClass + '"></i> ' + message +
                       '</div>';
        
        // Insert alert at the top of modal body
        $('.modal-body').prepend(alertHtml);
        
        // Auto-remove alert after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Initialize voucher code toggle functionality
    function initializeVoucherCodeToggle() {
        const toggleButton = document.getElementById('toggle-voucher-codes');
        const toggleIcon = document.getElementById('toggle-icon');
        const toggleText = document.getElementById('toggle-text');
        
        if (toggleButton) {
            toggleButton.addEventListener('click', function() {
                window.voucherCodesVisible = !window.voucherCodesVisible;
                toggleVoucherCodes(window.voucherCodesVisible);
                
                // Update button appearance
                if (window.voucherCodesVisible) {
                    toggleIcon.className = 'glyphicon glyphicon-eye-close';
                    toggleText.textContent = 'Hide All Codes';
                    toggleButton.className = toggleButton.className.replace('btn-info', 'btn-warning');
                    toggleButton.title = 'Click to hide all voucher codes';
                } else {
                    toggleIcon.className = 'glyphicon glyphicon-eye-open';
                    toggleText.textContent = 'Show All Codes';
                    toggleButton.className = toggleButton.className.replace('btn-warning', 'btn-info');
                    toggleButton.title = 'Click to show all voucher codes';
                }
            });
        }
    }
    
    function toggleVoucherCodes(visible) {
        const voucherCells = document.querySelectorAll('.voucher-code-cell');
        
        voucherCells.forEach(function(cell) {
            if (visible) {
                // Show all codes
                cell.className = cell.className.replace('voucher-code-hidden', 'voucher-code-visible');
                cell.onmouseenter = null;
                cell.onmouseleave = null;
            } else {
                // Hide all codes
                cell.className = cell.className.replace('voucher-code-visible', 'voucher-code-hidden');
                cell.onmouseenter = function() {
                    if (!window.voucherCodesVisible) {
                        this.className = this.className.replace('voucher-code-hidden', 'voucher-code-visible');
                    }
                };
                cell.onmouseleave = function() {
                    if (!window.voucherCodesVisible) {
                        this.className = this.className.replace('voucher-code-visible', 'voucher-code-hidden');
                    }
                };
            }
        });
    }
</script>
{include file="sections/footer.tpl"}