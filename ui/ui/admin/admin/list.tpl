{include file="sections/header.tpl"}
<!-- users -->

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20" style="border-color: #fd7e14;">
            <div class="panel-heading" style="background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%); color: white; border-color: #fd7e14;">
                <i class="ion ion-ios-people"></i> {Lang::T('Manage Administrator')}
                <span class="badge" style="background-color: rgba(255,255,255,0.3); margin-left: 10px;">{$total_count}</span>
            </div>
            <div class="panel-body" style="background-color: #fffaf0;">
                <div class="md-whiteframe-z1 mb20 text-center" style="padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #fd7e14; box-shadow: 0 2px 8px rgba(253, 126, 20, 0.1);">
                    <div class="row">
                        <div class="col-md-8">
                            <form id="site-search" method="post" action="{Text::url('settings/users/')}">
                                <input type="hidden" name="csrf_token" value="{$csrf_token}">
                                <div class="input-group input-group-lg">
                                    <div class="input-group-addon" style="background-color: #fd7e14; border-color: #fd7e14; color: white;">
                                        <span class="fa fa-search"></span>
                                    </div>
                                    <input type="text" name="search" value="{$search}" class="form-control"
                                        placeholder="Search by Username, Name, Email or Phone..." style="border-color: #fd7e14;">
                                    <div class="input-group-btn">
                                        <button class="btn btn-warning" type="submit" style="background-color: #fd7e14; border-color: #fd7e14; color: white;">
                                            <i class="fa fa-search"></i> {Lang::T('Search')}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <a href="{Text::url('settings/users-add')}" class="btn btn-lg btn-block" style="background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%); color: white; border: none; padding: 12px;">
                                <i class="ion ion-android-add"></i> {Lang::T('Add New Administrator')}
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive" style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <table class="table table-bordered table-striped table-hover">
                        <thead style="background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%); color: white;">
                            <tr>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-user"></i> {Lang::T('Username')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-id-card"></i> {Lang::T('Full Name')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-phone"></i> {Lang::T('Phone')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-envelope"></i> {Lang::T('Email')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-user-tag"></i> {Lang::T('Type')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-map-marker"></i> {Lang::T('Location')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-users"></i> {Lang::T('Agent')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-clock-o"></i> {Lang::T('Last Login')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-cogs"></i> {Lang::T('Manage')}
                                </th>
                                <th style="border-color: #e8590c;">
                                    <i class="fa fa-hashtag"></i> ID
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $d as $ds}
                                <tr {if $ds['status'] != 'Active'}class="danger"{/if} style="border-left: {if $ds['status'] != 'Active'}4px solid #dc3545{else}4px solid #28a745{/if};">
                                    <td>
                                        <strong>{$ds['username']}</strong>
                                        {if $ds['status'] != 'Active'}
                                            <span class="badge badge-danger" style="margin-left: 5px;">Inactive</span>
                                        {/if}
                                    </td>
                                    <td>{$ds['fullname']}</td>
                                    <td>
                                        {if $ds['phone']}
                                            <a href="tel:{$ds['phone']}" class="text-primary">
                                                <i class="fa fa-phone"></i> {$ds['phone']}
                                            </a>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $ds['email']}
                                            <a href="mailto:{$ds['email']}" class="text-primary">
                                                <i class="fa fa-envelope"></i> {$ds['email']}
                                            </a>
                                        {/if}
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: {if $ds['user_type'] == 'Admin'}#fd7e14{else}#6c757d{/if}; color: white;">
                                            {$ds['user_type']}
                                        </span>
                                    </td>
                                    <td>
                                        {if $ds['city']}
                                            <small>{$ds['city']}, {$ds['subdistrict']}, {$ds['ward']}</small>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $ds['root']}
                                            <a href="{Text::url('settings/users-view/', $ds['root'])}" class="text-warning">
                                                <i class="fa fa-user-circle"></i> {$admins[$ds['root']]}
                                            </a>
                                        {/if}
                                    </td>
                                    <td>
                                        {if $ds['last_login']}
                                            <span class="badge badge-light" style="border: 1px solid #dee2e6;">
                                                <i class="fa fa-clock-o"></i> {Lang::timeElapsed($ds['last_login'])}
                                            </span>
                                        {/if}
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{Text::url('settings/users-view/',$ds['id'])}"
                                                class="btn btn-sm btn-info" style="border-color: #17a2b8;">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <a href="{Text::url('settings/users-edit/',$ds['id'])}"
                                                class="btn btn-sm btn-warning" style="background-color: #ffc107; border-color: #ffc107;">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            {if ($_admin['id']) neq ($ds['id'])}
                                                <a href="{Text::url('settings/users-delete/',$ds['id'])}" id="{$ds['id']}"
                                                    class="btn btn-sm btn-danger" onclick="return ask(this, '{Lang::T('Delete')}?')">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            {/if}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary">#{$ds['id']}</span>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
                <div class="mt-3" style="padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #fd7e14;">
                    {include file="pagination.tpl"}
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-warning:hover {
        background-color: #e8590c !important;
        border-color: #e8590c !important;
        transform: translateY(-2px);
        transition: all 0.3s ease;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(253, 126, 20, 0.05);
        transform: translateX(5px);
        transition: all 0.3s ease;
    }
    
    .badge {
        font-weight: 500;
    }
    
    .input-group-addon {
        transition: all 0.3s ease;
    }
    
    .input-group-addon:hover {
        background-color: #e8590c !important;
    }
    
    .btn-group .btn {
        margin-right: 2px;
        border-radius: 4px !important;
    }
    
    .panel {
        border-radius: 10px;
        overflow: hidden;
    }
</style>

{include file="sections/footer.tpl"}