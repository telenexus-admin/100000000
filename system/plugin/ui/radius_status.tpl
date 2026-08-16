{include file="sections/header.tpl"}

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">RADIUS Service Status</h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-card-icon {if $radius_status.service_status == 'running'}bg-success{else}bg-danger{/if}">
                                <i class="fa fa-server"></i>
                            </div>
                            <div class="info-card-content">
                                <div class="info-card-title">Service Status</div>
                                <div class="info-card-value {if $radius_status.service_status == 'running'}text-success{else}text-danger{/if}">
                                    {if $radius_status.service_status == 'running'}Running{else}Stopped{/if}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-card-icon bg-info">
                                <i class="fa fa-clock-o"></i>
                            </div>
                            <div class="info-card-content">
                                <div class="info-card-title">Last Checked</div>
                                <div class="info-card-value">
                                    {$radius_status.last_checked}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                {if $radius_status.detailed_output}
                <div class="card">
                    <div class="card-header">
                        <h6 class="m-0">Service Details</h6>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3" style="white-space: pre-wrap; font-family: monospace; font-size: 12px;">{$radius_status.detailed_output}</pre>
                    </div>
                </div>
                {/if}
                
                <div class="mt-3">
                    <a href="{$_url}plugin/radius_data_usage" class="btn btn-secondary">
                        <i class="fa fa-arrow-left"></i> Back to Data Usage
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.info-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 0.35rem;
    background-color: white;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    border: 1px solid #e3e6f0;
}

.info-card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    margin-right: 1rem;
    color: white;
}

.info-card-content {
    flex: 1;
}

.info-card-title {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.info-card-value {
    font-size: 1.25rem;
    font-weight: 600;
    color: #333;
}
</style>

{include file="sections/footer.tpl"}