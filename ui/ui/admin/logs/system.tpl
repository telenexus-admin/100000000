{include file="sections/header.tpl"}
<!-- pool -->
<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">
                {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                    <div class="btn-group pull-right">
                        <a class="btn btn-primary btn-xs" title="save" href="{Text::url('logs/list-csv')}"
                            onclick="return ask(this, '{Lang::T('This will export to CSV')}?')"><span class="glyphicon glyphicon-download"
                                aria-hidden="true"></span> CSV</a>
                    </div>
                {/if}
                {Lang::T('Activity Log')}
            </div>
            <div class="panel-body">
                <div class="text-center" style="padding: 15px">
                    <div class="col-md-4">
                        <form id="site-search" method="post" action="{Text::url('logs/list/')}">
                            <div class="input-group">
                                <div class="input-group-addon">
                                    <span class="fa fa-search"></span>
                                </div>
                                <input type="text" name="q" class="form-control" value="{$q}"
                                    placeholder="{Lang::T('Search by Name')}...">
                                <div class="input-group-btn">
                                    <button class="btn btn-success" type="submit">{Lang::T('Search')}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-8">
                        <form class="form-inline" method="post" action="{Text::url('')}logs/list/">
                            <div class="input-group has-error">
                                <span class="input-group-addon">{Lang::T('Keep Logs')} </span>
                                <input type="text" name="keep" class="form-control" placeholder="90" value="90">
                                <span class="input-group-addon">{Lang::T('Days')}</span>
                            </div>
                            <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return ask(this, '{Lang::T("Clear old logs?")}')">{Lang::T('Clean up Logs')}</button>
                        </form>
                    </div>&nbsp;
                </div>
                <br>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-condensed">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-logs"></th>
                                <th>ID</th>
                                <th>{Lang::T('Date')}</th>
                                <th>{Lang::T('Type')}</th>
                                <th>{Lang::T('IP')}</th>
                                <th>{Lang::T('Description')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $d as $ds}
                                <tr>
                                    <td><input type="checkbox" name="log_ids[]" value="{$ds['id']}"></td>
                                    <td>{$ds['id']}</td>
                                    <td>{Lang::dateTimeFormat($ds['date'])}</td>
                                    <td>{$ds['type']}</td>
                                    <td>{$ds['ip']}</td>
                                    <td style="overflow-x: scroll;">{nl2br($ds['description'])}</td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
                {if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
                    <div class="text-right" style="margin-top: 10px;">
                        <button id="deleteSelectedLogs" class="btn btn-danger btn-sm">
                            <span class="glyphicon glyphicon-trash" aria-hidden="true"></span> {Lang::T('Delete Selected')}
                        </button>
                    </div>
                {/if}
                {include file="pagination.tpl"}
            </div>
        </div>
    </div>
</div>

{if in_array($_admin['user_type'],['SuperAdmin','Admin'])}
    <script>
        (function() {
            var selectAll = document.getElementById('select-all-logs');
            var deleteBtn = document.getElementById('deleteSelectedLogs');

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    var checkboxes = document.querySelectorAll('input[name="log_ids[]"]');
                    for (var checkbox of checkboxes) {
                        checkbox.checked = this.checked;
                    }
                });
            }

            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    var selectedLogs = [];
                    document.querySelectorAll('input[name="log_ids[]"]:checked').forEach(function(checkbox) {
                        selectedLogs.push(checkbox.value);
                    });

                    if (selectedLogs.length === 0) {
                        Swal.fire({
                            title: '{Lang::T('Error!')}',
                            text: '{Lang::T('Please select at least one log to delete.')}',
                            icon: 'error',
                            confirmButtonText: '{Lang::T('OK')}'
                        });
                        return;
                    }

                    Swal.fire({
                        title: '{Lang::T('Are you sure?')}',
                        text: '{Lang::T('This action cannot be undone!')}',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '{Lang::T('Yes, delete it!')}',
                        cancelButtonText: '{Lang::T('Cancel')}'
                    }).then(function(result) {
                        if (!result.isConfirmed) {
                            return;
                        }

                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', '{Text::url('logs/delete-many')}', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            var response = {};
                            try {
                                response = JSON.parse(xhr.responseText || '{}');
                            } catch (e) {}

                            if (xhr.status === 200 && response.status === 'success') {
                                Swal.fire({
                                    title: '{Lang::T('Deleted!')}',
                                    text: response.message || '{Lang::T('Selected logs deleted successfully.')}',
                                    icon: 'success',
                                    confirmButtonText: '{Lang::T('OK')}'
                                }).then(function() {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: '{Lang::T('Error!')}',
                                    text: response.message || '{Lang::T('Failed to delete selected logs.')}',
                                    icon: 'error',
                                    confirmButtonText: '{Lang::T('OK')}'
                                });
                            }
                        };
                        xhr.send('logIds=' + encodeURIComponent(JSON.stringify(selectedLogs)));
                    });
                });
            }
        })();
    </script>
{/if}

{include file="sections/footer.tpl"}
