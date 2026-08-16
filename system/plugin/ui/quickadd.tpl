{include file="sections/header.tpl"}

<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-default mb20">
            <div class="panel-heading">
                <i class="fa fa-user-plus"></i> {Lang::T('Add_Customer')}
                <small class="text-muted" style="font-weight: normal;"> — {Lang::T('Create_new_Hotspot_or_PPPoE_customer')}</small>
            </div>
        </div>
    </div>
</div>

<form class="form-horizontal" method="post" role="form" action="{$_url}plugin/quickadd/add{if $routes['2'] == 'pppoe'}/pppoe{/if}">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <ul class="nav nav-tabs" style="margin: 0; padding: 10px 10px 0 10px; background: #f5f5f5; border-radius: 4px 4px 0 0;">
                    <li role="presentation" {if $routes['2'] eq ''}class="active"{/if}>
                        <a href="{$_url}plugin/quickadd"><i class="fa fa-wifi"></i> {Lang::T('Hotspot')}</a>
                    </li>
                    <li role="presentation" {if $routes['2'] eq 'pppoe'}class="active"{/if}>
                        <a href="{$_url}plugin/quickadd/pppoe"><i class="fa fa-plug"></i> {Lang::T('PPPoE')}</a>
                    </li>
                </ul>
                <div class="panel-heading">
                    <i class="fa fa-edit"></i> {Lang::T('Add_New_customer')}
                </div>
                <div class="panel-body">
				<input type="hidden" name="account_type" value="Hotspot">
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Username')}</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                {if $_c['country_code_phone'] != ''}
                                    <span class="input-group-addon" id="basic-addon1"><i
                                            class="glyphicon glyphicon-phone-alt"></i></span>
                                {else}
                                    <span class="input-group-addon" id="basic-addon1"><i
                                            class="glyphicon glyphicon-user"></i></span>
                                {/if}
                                <input type="text" class="form-control" name="username" required
                                    placeholder="{if $_c['country_code_phone']!= ''}{$_c['country_code_phone']} {Lang::T('Phone_Number')}{else}{Lang::T('Username')}{/if}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Full_Name')}</label>
                        <div class="col-md-9">
                            <input type="text" required class="form-control" id="fullname" name="fullname">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Email')}</label>
                        <div class="col-md-9">
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Phone_Number')}</label>
                        <div class="col-md-9">
                            <div class="input-group">
                                {if $_c['country_code_phone']!= ''}
                                    <span class="input-group-addon" id="basic-addon1">+</span>
                                {else}
                                    <span class="input-group-addon" id="basic-addon1"><i
                                            class="glyphicon glyphicon-phone-alt"></i></span>
                                {/if}
                                <input type="text" class="form-control" name="phonenumber"
                                    placeholder="{if $_c['country_code_phone']!= ''}{$_c['country_code_phone']}{/if} {Lang::T('Phone_Number')}">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Password')}</label>
                        <div class="col-md-9">
                            <input type="password" class="form-control" autocomplete="off" required id="password"
                                value="{rand(000000,999999)}" name="password" onmouseleave="this.type = 'password'"
                                onmouseenter="this.type = 'text'">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Address')}</label>
                        <div class="col-md-9">
                            <textarea name="address" id="address" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-3 control-label">{Lang::T('Select_Package')}</label>
                        <div class="col-md-9">
                            <select class="form-control select2"
                                name="ppln" style="width: 100%" data-placeholder="{Lang::T('Select_Package')}...">
						{foreach $plans as $plan}
                        <option value="{$plan['id']}">{$plan['name_plan']} &bull; {Lang::moneyFormat($plan['price'])}{if $plan['routers']} &bull; {$plan['routers']}{/if}</option>
						{/foreach}
                            </select>
                        </div>
                    </div>
                    
                    {if $routes['2'] eq 'pppoe'}
                        <div class="panel-heading"><i class="fa fa-plug"></i> {Lang::T('PPPoE_Configuration')}</div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label class="col-md-3 control-label">{Lang::T('Usernames')} <span class="label label-danger"
                                        id="warning_pppoe_username"></span></label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="pppoe_username" name="pppoe_username"
                                        onkeyup="checkUsername(this, 0)" placeholder="{Lang::T('PPPoE Username')}">
                                    <span class="help-block">{Lang::T('Not Working with Freeradius Mysql')}</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-md-3 control-label">{Lang::T('Password')}</label>
                                <div class="col-md-9">
                                    <input type="password" class="form-control" id="pppoe_password" name="pppoe_password"
                                        placeholder="{Lang::T('PPPoE_Password')}" onmouseleave="this.type = 'password'"
                                        onmouseenter="this.type = 'text'">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-md-3 control-label">{Lang::T('Remote_IP')} <span class="label label-danger"
                                        id="warning_ip"></span></label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="pppoe_ip" name="pppoe_ip"
                                        onkeyup="checkIP(this, 0)" placeholder="{Lang::T('Remote_IP')}">
                                    <span class="help-block">{Lang::T('Not Working with Freeradius Mysql')}</span>
                                </div>
                            </div>
                        </div>
                    {/if}
                    <div class="form-group">
                            <label class="col-md-3 control-label">{Lang::T('Send_Welcome_Message')}</label>
                            <div class="col-md-9">
                                <label class="switch">
                                    <input type="checkbox" id="send_welcome_message" value="1" name="send_welcome_message">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group" id="method" style="display: none;">
                            <label class="col-md-3 control-label">{Lang::T('Method')}</label>
                            <label class="col-md-3 control-label"><input type="checkbox" name="sms" value="1">
                                {Lang::T('SMS')}</label>
                            <label class="col-md-2 control-label"><input type="checkbox" name="wa" value="1">
                                {Lang::T('WA')}</label>
                            <label class="col-md-2 control-label"><input type="checkbox" name="mail" value="1">
                                {Lang::T('Email')}</label>
                        </div>
                <input type="text" id="service_type" name="service_type" value="{if $routes['2'] eq 'pppoe'}PPPoE{else}hotspot{/if}" hidden>
            </div>
    <center>
        <button class="btn btn-success" type="submit" onclick="return ask(this, '{Lang::T('Continue_the_process_of_adding_Customer_Data')}?')">
                <i class="fa fa-user-plus"></i> {Lang::T('Create_Account')}
            </button>
            <a href="{$_url}customers" class="btn btn-default"><i class="fa fa-times"></i> {Lang::T('Cancel')}</a>
    </center>
                </div>
            </div>
        </div>
    </div>
</form>

{literal}
    <script>
        // Fungsi dari edit.tpl untuk validasi Username PPPoE (onkeyup)
        function checkUsername(obj, id) {
            var username = obj.value;
            $.post(APP_URL + '/customers/checkUsername', {
                username: username,
                id: id
            }).done(function(data) {
                var warningSpan = document.getElementById('warning_pppoe_username');
                if (data == 'true') {
                    warningSpan.textContent = " {Lang::T('Username has been used')}";
                    warningSpan.style.display = 'inline';
                } else {
                    warningSpan.textContent = "";
                    warningSpan.style.display = 'none';
                }
            }).fail(function(e) {
                console.log(e);
            });
        }

        // Fungsi dari edit.tpl untuk validasi Remote IP (onkeyup)
        function checkIP(obj, id) {
            var ip = obj.value;
            $.post(APP_URL + '/customers/checkIP', {
                ip: ip,
                id: id
            }).done(function(data) {
                var warningSpan = document.getElementById('warning_ip');
                if (data == 'true') {
                    warningSpan.textContent = " {Lang::T('IP has been used')}";
                    warningSpan.style.display = 'inline';
                } else {
                    warningSpan.textContent = "";
                    warningSpan.style.display = 'none';
                }
            }).fail(function(e) {
                console.log(e);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            var sendWelcomeCheckbox = document.getElementById('send_welcome_message');
            var methodSection = document.getElementById('method');

            function toggleMethodSection() {
                if (sendWelcomeCheckbox.checked) {
                    methodSection.style.display = 'block';
                } else {
                    methodSection.style.display = 'none';
                }
            }

            toggleMethodSection();

            sendWelcomeCheckbox.addEventListener('change', toggleMethodSection);
            document.querySelector('form').addEventListener('submit', function(event) {
                if (sendWelcomeCheckbox.checked) {
                    var methodCheckboxes = methodSection.querySelectorAll('input[type="checkbox"]');
                    var oneChecked = Array.from(methodCheckboxes).some(function(checkbox) {
                        return checkbox.checked;
                    });

                    if (!oneChecked) {
                        event.preventDefault();
                        alert('Please choose at least one method.');
                        methodSection.focus();
                    }
                }
                
                // Pengecekan tambahan: jika ada warning IP atau Username PPPoE
                var warningIp = document.getElementById('warning_ip');
                var warningPppoeUsername = document.getElementById('warning_pppoe_username');

                if ((warningIp && warningIp.textContent.trim() !== "") || (warningPppoeUsername && warningPppoeUsername.textContent.trim() !== "")) {
                    event.preventDefault();
                    alert('Please fix the warnings for PPPoE Username or Remote IP before continuing.');
                }
            });
        });
    </script>
{/literal}

{include file="sections/footer.tpl"}
