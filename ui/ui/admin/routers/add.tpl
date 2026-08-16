{include file="sections/header.tpl"}

<style>
:root {
    --primary: #f97316;
    --primary-dark: #ea580c;
    --primary-light: #fed7aa;
    --primary-soft: #fff7ed;
}

.bg-orange { background-color: var(--primary) !important; }
.text-orange { color: var(--primary) !important; }
.border-orange { border-color: var(--primary) !important; }

.panel.panel-primary {
    border-color: var(--primary) !important;
}

.panel.panel-primary > .panel-heading {
    background: linear-gradient(145deg, var(--primary), var(--primary-dark)) !important;
    border-color: var(--primary-dark) !important;
    color: white !important;
}

.btn-primary {
    background: linear-gradient(145deg, var(--primary), var(--primary-dark)) !important;
    border-color: var(--primary-dark) !important;
    color: white !important;
}

.btn-primary:hover {
    background: linear-gradient(145deg, var(--primary-dark), #c2410c) !important;
    border-color: #c2410c !important;
}

.btn-link {
    color: var(--primary) !important;
}

.btn-link:hover {
    color: var(--primary-dark) !important;
}

.form-control:focus {
    border-color: var(--primary) !important;
    box-shadow: 0 0 5px rgba(249, 115, 22, 0.3) !important;
}

a {
    color: var(--primary);
}

a:hover {
    color: var(--primary-dark);
}

.radio-inline input[type="radio"]:checked + label {
    color: var(--primary);
}

.radio-inline input[type="radio"]:checked {
    border-color: var(--primary);
    background-color: var(--primary);
}

.help-block {
    color: #6c757d;
}

.help-block a {
    color: var(--primary);
}

.help-block a:hover {
    color: var(--primary-dark);
}

.panel-hovered:hover {
    border-color: var(--primary) !important;
    box-shadow: 0 5px 15px rgba(249, 115, 22, 0.2);
}
</style>

<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">{Lang::T('Add Router')}</div>
            <div class="panel-body">

                <div class="alert alert-success" style="display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;">
                    <div>
                        <strong><i class="fa fa-shield"></i> Recommended: Automatic WireGuard + FreeRADIUS Setup</strong><br>
                        <span>Generate one RouterOS 7 script. WireGuard, RouterOS API credentials, RADIUS NAS, accounting and CoA are provisioned automatically.</span>
                    </div>
                    <a class="btn btn-success" href="{Text::url('')}plugin/rs_radius_wireguard_setup">
                        <i class="fa fa-magic"></i> Automatic Setup
                    </a>
                </div>

                <hr>
                <h4 style="margin-bottom:20px;">Manual Router Setup</h4>

                <form class="form-horizontal" method="post" role="form" action="{Text::url('')}routers/add-post">
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Status')}</label>
                        <div class="col-md-10">
                            <label class="radio-inline">
                                <input type="radio" checked name="enabled" value="1" style="accent-color: var(--primary);"> {Lang::T('Enable')}
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="enabled" value="0" style="accent-color: var(--primary);"> {Lang::T('Disable')}
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Router Name / Location')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="name" name="name" maxlength="32">
                            <p class="help-block">{Lang::T('Name of Area that router operated')}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('IP Address')}</label>
                        <div class="col-md-6">
                            <input type="text" placeholder="192.168.88.1:8728" class="form-control" id="ip_address"
                                name="ip_address">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Username')}</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="username" name="username">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Router Secret')}</label>
                        <div class="col-md-6">
                            <input type="password" class="form-control" id="password" name="password"
                            onmouseleave="this.type = 'password'" onmouseenter="this.type = 'text'">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">{Lang::T('Description')}</label>
                        <div class="col-md-6">
                            <textarea class="form-control" id="description" name="description"></textarea>
                            <p class="help-block">{Lang::T('Explain Coverage of router')}</p>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-2 control-label"></label>
                        <div class="col-md-6">
                            <label style="color: var(--primary);">
                                <input type="checkbox" checked name="testIt" value="yes" style="accent-color: var(--primary);"> {Lang::T('Test Connection')}
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary" onclick="return ask(this, '{Lang::T("Continue the process of adding Routers?")}')"
                                type="submit">{Lang::T('Save')}</button>
                            Or <a href="{Text::url('')}routers/list">{Lang::T('Cancel')}</a>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

{include file="sections/footer.tpl"}