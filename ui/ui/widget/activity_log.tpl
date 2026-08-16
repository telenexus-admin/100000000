<div class="panel panel-default panel-hovered mb20 activities">
    <div class="panel-heading clearfix">
        <span>{Lang::T('Recent Activity')}</span>
        <a href="{Text::url('logs/list')}" class="btn btn-xs btn-default pull-right">{Lang::T('View All')}</a>
    </div>
    <div class="panel-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-condensed mb0">
                <tbody>
                    {foreach $dlog as $lg}
                        <tr>
                            <td style="width: 1%; white-space: nowrap; vertical-align: top;">
                                <small class="text-muted">{Lang::dateTimeFormat($lg['date'])}</small>
                            </td>
                            <td style="width: 1%; vertical-align: top;">
                                <span class="label label-default">{$lg['type']}</span>
                            </td>
                            <td style="word-break: break-word; vertical-align: top;">
                                <small>{nl2br($lg['description'])}</small>
                            </td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="3" class="text-muted text-center">{Lang::T('No logs')}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>
