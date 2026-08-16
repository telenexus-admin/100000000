{include file="sections/header.tpl"}


<div class="row">
    <div class="col-sm-12">
        <div class="panel panel-hovered mb20 panel-primary">
            <div class="panel-heading">{Lang::T('Backup Database')}</div>
            <div class="panel-body">
                <div class="md-whiteframe-z1 mb20 text-center" style="padding: 15px">
                    <div class="col-md-8">
                        <form method="post" action="{$_url}plugin/backup_upload_form" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <div class="input-group">
                                <input class="form-control" type="file" name="file" accept="application/*.sql">
                                <div class="input-group-btn">
                                    <button class="btn btn-success" type="submit"><span class="fa fa-upload">
                                        </span> {Lang::T('Upload')}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="POST" action="{$_url}plugin/backup_add">
                            <input type="hidden" name="csrf_token" value="{$csrf_token}">
                            <input class="btn btn-primary btn-block waves-effect" type="submit" name="createBackup"
                                value="Create Backup">
                        </form>
                    </div>&nbsp;
                </div>
                <div class="table-responsive">
                    {if empty($backupFiles)}
                    <p align="center"><b>{Lang::T('Backup not found.')}</b></p>
                    {else}
                    <table class="table table-bordered table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>{Lang::T('Backup File')}</th>
                                <th>{Lang::T('Date')}</th>
                                <th>{Lang::T('Size')}</th>
                                <th>{Lang::T('Action')}</th>
                            </tr>
                        </thead>
                        <tbody>

                            {foreach $backupFiles as $backup}
                            <tr>
                                <td>{$backup.file}</td>
                                <td>{$backup.creation_date}</td>
                                <td>{$backup.size}</td>
                                <td align="center">
                                    <a href="{$_url}plugin/backup_download&file={$backup.file}&token={$csrf_token}"
                                        style="margin: 0px;" class="btn btn-success btn-xs">{Lang::T('Download')}</a>
                                    <a href="{$_url}plugin/backup_restore&file={$backup.file}&token={$csrf_token}"
                                        style="margin: 0px;"
                                        onclick="return confirm('{Lang::T('Are you Sure you want to Restore this Database?')}')"
                                        class="btn btn-primary btn-xs">{Lang::T('Restore')}</a>
                                    <a href="{$_url}plugin/backup_delete&file={$backup.file}&token={$csrf_token}"
                                        style="margin: 0px;"
                                        onclick="return confirm('{Lang::T('Are you Sure you want to Delete this Database?')}')"
                                        class="btn btn-danger btn-xs">{Lang::T('Delete')}</a>
                                </td>
                            </tr>
                            {/foreach}
                            {/if}

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    function toggleBackupFrequency() {
        const autoBackupCheckbox = document.getElementById('backup_auto');
        const backupFrequencySection = document.getElementById('backup_frequency_section');
        backupFrequencySection.style.display = autoBackupCheckbox.checked ? 'block' : 'none';
    }

    function toggleRetainCount() {
        const autoClearCheckbox = document.getElementById('backup_clear_old');
        const retainCountSection = document.getElementById('retain_count_section');
        retainCountSection.style.display = autoClearCheckbox.checked ? 'block' : 'none';
    }

    function toggleCloudFields() {
        const cloudUploadCheckbox = document.getElementById('cloud_upload');
        const dropBoxFields = document.getElementById('dropbox_fields');
        if (cloudUploadCheckbox.checked) {
            dropBoxFields.style.display = 'block';
        } else {
            dropBoxFields.style.display = 'none';
        }
    }
    toggleBackupFrequency();
    toggleRetainCount();
    toggleCloudFields();
</script>
{include file="sections/footer.tpl"}