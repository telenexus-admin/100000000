{include file="sections/header.tpl"}

<section class="content-header">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h6 class="text-success fw-bold display-5 d-flex align-items-center">
            <i class="fa fa-user-tag me-3"></i> Assign PPPoE Packages
        </h6>
    </div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item"><a href="{$_url}dashboard">Home</a></li>
            <li class="breadcrumb-item"><a href="#">Network</a></li>
            <li class="breadcrumb-item active" aria-current="page">PPPoE Assign</li>
        </ol>
    </nav>
</section>

<section class="content">
    <div class="box box-success">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-filter"></i> Filter &amp; Search</h3>
        </div>
        <div class="box-body">
            <form method="get" action="{$_url}plugin/pppoe_assign" class="row g-2 align-items-end">
                <input type="hidden" name="_route" value="plugin/pppoe_assign">

                <div class="col-md-4 form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control"
                           placeholder="username, pppoe_username, fullname or phone"
                           value="{$search|escape}">
                </div>

                <div class="col-md-3 form-group">
                    <label for="router_id">Router</label>
                    <select id="router_id" name="router_id" class="form-control">
                        <option value="">All routers</option>
                        {foreach from=$routers item=r}
                            <option value="{$r.id}" {if $router_id == $r.id}selected{/if}>{$r.name|escape}</option>
                        {/foreach}
                    </select>
                </div>

                <div class="col-md-3 form-group">
                    <button type="submit" class="btn btn-success"><i class="fa fa-search"></i> Apply</button>
                    <a href="{$_url}plugin/pppoe_assign" class="btn btn-default">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="box box-success">
        <div class="box-header with-border d-flex justify-content-between align-items-center">
            <h3 class="box-title"><i class="fa fa-users"></i>
                PPPoE Customers <small class="text-muted">({$total} total)</small>
            </h3>
            <div>
                <button type="button" id="bulkAssignBtn" class="btn btn-primary" disabled>
                    <i class="fa fa-bolt"></i> Assign to Selected (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="selectAll"></th>
                        <th>Username</th>
                        <th>PPPoE Username</th>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Router</th>
                        <th>Current Plan</th>
                        <th>Expires</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {if empty($customers)}
                        <tr><td colspan="9" class="text-center text-muted py-4">No PPPoE customers match this filter.</td></tr>
                    {/if}
                    {foreach from=$customers item=c}
                        <tr data-customer-id="{$c.id}"
                            data-username="{$c.username|escape}"
                            data-router-id="{$c.router_id}"
                            data-router-name="{$c.router_name|escape}">
                            <td><input type="checkbox" class="rowSelect" value="{$c.id}"></td>
                            <td><strong>{$c.username|escape}</strong></td>
                            <td>{$c.pppoe_username|default:'—'|escape}</td>
                            <td>{$c.fullname|escape}</td>
                            <td>{$c.phonenumber|escape}</td>
                            <td><span class="label label-info">{$c.router_name|escape}</span></td>
                            <td>
                                {if $c.current_plan}
                                    {$c.current_plan.namebp|escape}
                                {else}
                                    <em class="text-muted">none</em>
                                {/if}
                            </td>
                            <td>
                                {if $c.current_plan}
                                    {if $c.current_plan.is_expired}
                                        <span class="label label-danger">Expired</span>
                                        <small>{$c.current_plan.expires_human|escape}</small>
                                    {else}
                                        <span class="label label-success">Active</span>
                                        <small>{$c.current_plan.expires_human|escape}</small>
                                    {/if}
                                {else}
                                    <span class="label label-default">—</span>
                                {/if}
                            </td>
                            <td>
                                <button type="button" class="btn btn-xs btn-primary assignBtn"
                                        data-customer-id="{$c.id}"
                                        data-username="{$c.username|escape}"
                                        data-router-id="{$c.router_id}">
                                    <i class="fa fa-plug"></i> Assign
                                </button>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>

        {if $total_pages > 1}
        <div class="box-footer">
            <nav>
                <ul class="pagination">
                    {foreach from=$page_links item=p}
                        <li class="{if $p == $page}active{/if}">
                            <a href="{$_url}plugin/pppoe_assign&search={$search|escape:'url'}&router_id={$router_id|escape:'url'}&page={$p}">{$p}</a>
                        </li>
                    {/foreach}
                </ul>
            </nav>
        </div>
        {/if}
    </div>
</section>

<!-- Assign modal -->
<div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fa fa-plug"></i> Assign PPPoE Package</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" id="assignTargetInfo">Loading...</div>

                <div class="form-group">
                    <label for="assignPlan">Package</label>
                    <select id="assignPlan" class="form-control"></select>
                    <small class="help-block" id="assignPlanHint">Only plans that belong to the selected router are listed.</small>
                </div>

                <div class="form-group">
                    <label for="assignNote">Note (optional)</label>
                    <input type="text" id="assignNote" class="form-control"
                           placeholder="e.g. Corporate account, manual override">
                </div>

                <div id="assignResult" class="mt-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" id="doAssignBtn" class="btn btn-success">
                    <i class="fa fa-check"></i> Confirm Assign
                </button>
            </div>
        </div>
    </div>
</div>

<script>window.__PPPOE_ASSIGN_URL = '{$_url}plugin/pppoe_assign';</script>
{literal}
<script>
// jQuery/Bootstrap are loaded in the shared footer AFTER this inline script,
// so we can't run the bindings immediately. Wait for the window load event
// (after all deferred scripts finish) before wiring anything up.
function __pppoe_assign_boot() {
    if (typeof window.jQuery === 'undefined') {
        // Poll briefly in case load event fires before jQuery is ready.
        return setTimeout(__pppoe_assign_boot, 50);
    }
    (function ($) {
    var PLUGIN_URL = window.__PPPOE_ASSIGN_URL;
    var currentTargets = [];   // array of objects: id, username, router_id, router_name
    var planCacheByRouter = {}; // router_id -> plans[]

    // -----------------------------------------------------------------
    // Row selection
    // -----------------------------------------------------------------
    var $rowSelect = $('.rowSelect');
    var $selectAll = $('#selectAll');
    var $bulkBtn   = $('#bulkAssignBtn');
    var $count     = $('#selectedCount');

    function refreshSelection() {
        var n = $('.rowSelect:checked').length;
        $count.text(n);
        $bulkBtn.prop('disabled', n === 0);
    }
    $rowSelect.on('change', refreshSelection);
    $selectAll.on('change', function () {
        $rowSelect.prop('checked', $selectAll.is(':checked'));
        refreshSelection();
    });

    // -----------------------------------------------------------------
    // Per-row "Assign" click
    // -----------------------------------------------------------------
    $('.assignBtn').on('click', function () {
        var $tr = $(this).closest('tr');
        currentTargets = [{
            id:          $tr.data('customer-id'),
            username:    $tr.data('username'),
            router_id:   $tr.data('router-id'),
            router_name: $tr.data('router-name')
        }];
        openAssignModal();
    });

    // -----------------------------------------------------------------
    // Bulk "Assign to Selected"
    // -----------------------------------------------------------------
    $bulkBtn.on('click', function () {
        currentTargets = [];
        $('.rowSelect:checked').each(function () {
            var $tr = $(this).closest('tr');
            currentTargets.push({
                id:          $tr.data('customer-id'),
                username:    $tr.data('username'),
                router_id:   $tr.data('router-id'),
                router_name: $tr.data('router-name')
            });
        });
        if (currentTargets.length === 0) return;
        openAssignModal();
    });

    // -----------------------------------------------------------------
    // Modal open: show target(s), load plans for router
    // -----------------------------------------------------------------
    function openAssignModal() {
        $('#assignResult').empty();
        $('#assignNote').val('');
        $('#assignPlan').empty().append('<option>Loading plans...</option>');

        // Header text
        if (currentTargets.length === 1) {
            var t = currentTargets[0];
            $('#assignTargetInfo').html(
                'Assigning package to <strong>' + escapeHtml(t.username) + '</strong>' +
                ' on router <strong>' + escapeHtml(t.router_name || '—') + '</strong>'
            );
        } else {
            // Bulk path: group by router so we can warn if mixed
            var routerIds = currentTargets.map(function (t) { return String(t.router_id || ''); });
            var uniqueRouters = routerIds.filter(function (v, i, a) { return a.indexOf(v) === i; });
            if (uniqueRouters.length > 1) {
                $('#assignTargetInfo').html(
                    '<strong>' + currentTargets.length + ' customers</strong> selected across <strong>' +
                    uniqueRouters.length + ' routers</strong>. Only plans that exist on <em>every</em> selected router will be shown.'
                );
            } else {
                $('#assignTargetInfo').html(
                    'Assigning to <strong>' + currentTargets.length + ' customers</strong> on router <strong>' +
                    escapeHtml(currentTargets[0].router_name || '—') + '</strong>'
                );
            }
        }

        // Fetch plans for each distinct router, then intersect by plan id.
        var distinctRouters = {};
        currentTargets.forEach(function (t) { distinctRouters[t.router_id] = true; });
        var routerIds = Object.keys(distinctRouters);

        Promise.all(routerIds.map(loadPlansForRouter)).then(function (listOfPlansArrays) {
            var intersection = intersectPlans(listOfPlansArrays);
            var $sel = $('#assignPlan').empty();
            if (!intersection.length) {
                $sel.append('<option value="">No plan is available on all selected routers</option>');
            } else {
                $sel.append('<option value="">-- Select a plan --</option>');
                intersection.forEach(function (p) {
                    $sel.append($('<option>').attr('value', p.id).text(
                        p.name + '  (' + p.validity + ', ' + p.price + ')'
                    ));
                });
            }
        });

        $('#assignModal').modal('show');
    }

    function loadPlansForRouter(routerId) {
        if (planCacheByRouter[routerId]) {
            return Promise.resolve(planCacheByRouter[routerId]);
        }
        return $.get(PLUGIN_URL + '&act=get_plans&router_id=' + encodeURIComponent(routerId))
            .then(function (data) {
                if (data && data.success && Array.isArray(data.plans)) {
                    planCacheByRouter[routerId] = data.plans;
                    return data.plans;
                }
                return [];
            });
    }

    function intersectPlans(lists) {
        if (!lists.length) return [];
        if (lists.length === 1) return lists[0];
        // Keep only plans whose id exists in every list.
        var idSets = lists.map(function (lst) {
            var s = {};
            lst.forEach(function (p) { s[p.id] = true; });
            return s;
        });
        return lists[0].filter(function (p) {
            return idSets.every(function (s) { return !!s[p.id]; });
        });
    }

    // -----------------------------------------------------------------
    // Confirm assign
    // -----------------------------------------------------------------
    $('#doAssignBtn').on('click', function () {
        var planId = parseInt($('#assignPlan').val() || 0, 10);
        var note   = ($('#assignNote').val() || '').trim();
        if (!planId) {
            $('#assignResult').html('<div class="alert alert-warning">Please select a plan.</div>');
            return;
        }

        var ids = currentTargets.map(function (t) { return t.id; });
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Assigning...');
        $('#assignResult').empty();

        $.ajax({
            url: PLUGIN_URL + '&act=do_assign',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ plan_id: planId, customer_ids: ids, note: note }),
            dataType: 'json'
        })
        .done(function (res) {
            if (!res || !res.success) {
                $('#assignResult').html('<div class="alert alert-danger">' + escapeHtml((res && res.message) || 'Unexpected error') + '</div>');
                return;
            }
            var okCount   = res.assigned || 0;
            var failCount = res.failed   || 0;
            var html = '<div class="alert alert-' + (failCount ? 'warning' : 'success') + '">' +
                '<strong>' + okCount + '</strong> assigned' +
                (failCount ? ', <strong>' + failCount + '</strong> failed' : '') + '.</div>';
            if (failCount && res.details && res.details.fail) {
                html += '<ul class="list-unstyled">';
                res.details.fail.forEach(function (f) {
                    html += '<li>&bull; ' + escapeHtml(f.username || ('#' + f.id)) + ' &mdash; ' + escapeHtml(f.reason) + '</li>';
                });
                html += '</ul>';
            }
            $('#assignResult').html(html);
            if (okCount > 0) {
                setTimeout(function () { window.location.reload(); }, 1600);
            }
        })
        .fail(function (xhr) {
            $('#assignResult').html('<div class="alert alert-danger">HTTP ' + xhr.status + ': ' + escapeHtml(xhr.statusText) + '</div>');
        })
        .always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Confirm Assign');
        });
    });

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    })(window.jQuery);
}
if (document.readyState === 'complete') {
    __pppoe_assign_boot();
} else {
    window.addEventListener('load', __pppoe_assign_boot);
}
</script>
{/literal}

{include file="sections/footer.tpl"}
