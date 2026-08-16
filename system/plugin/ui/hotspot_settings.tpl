{include file="sections/header.tpl"}

<section class="content-header">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h6 class="text-success fw-bold display-5 d-flex align-items-center">
            <i class="fa fa-wifi me-3"></i> Hotspot Settings
        </h6>

    </div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-light p-3 rounded">
            <li class="breadcrumb-item"><a href="#">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Hotspot Settings</li>
        </ol>

<!-- Universal Hotspot Login (one page for all devices) -->
<div class="d-flex gap-3 mb-3 flex-wrap">
    <div class="btn-group">
        <button type="button" class="btn btn-success">
            <i class="fa fa-wifi"></i> Universal Login Page
        </button>
        <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown">
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
        </button>
        <ul class="dropdown-menu" role="menu">
            <li>
                <a href="{$app_url}/download.php?preview=1" target="_blank">
                    <i class="fa fa-eye"></i> Preview Login Page
                </a>
            </li>
            <li>
                <a href="{$app_url}/download.php?download=1">
                    <i class="fa fa-download"></i> Download login.html
                </a>
            </li>
        </ul>
    </div>
    <div class="alert alert-info mb-0 py-2 px-3" style="max-width:420px;">
        <small><i class="fa fa-check-circle"></i> One page for Android, iPhone, Smart TV, laptop and all other devices — same design and features.</small>
    </div>
</div>
    </nav>
</section>


<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">

                <div class="show" id="settingsForm">
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label"><i class="fa fa-header"></i> Hotspot Page Title</label>
                                <input type="text" class="form-control" name="hotspot_title" value="{$hotspot_title}"
                                    required placeholder="Hotspot Page Title">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fa fa-sign-in"></i> Hotspot Auto-Login URL</label>
                                <input type="text" class="form-control" name="hotspot_login_url" value="{$hotspot_login_url|default:'http://10.0.0.1/login'}"
                                    placeholder="http://10.0.0.1/login">
                                <small class="text-muted">MikroTik Hotspot login page used after M-Pesa PIN (usually http://10.0.0.1/login).</small>
                            </div>
                            
                                  <br>
                                  
                                   <!-- Faqs-->
            <div class="mb-3" id="faq-section">
                <label class="form-label"><i class="fa fa-question-circle"></i> Extra Information / Solution/ Suggestion</label>
                <input type="text" class="form-control mb-2" name="faq1" value="{$faq1|default:''}" placeholder="FAQ 1 or Info" required>
                
                <input type="text" class="form-control mb-2" name="faq2" value="{$faq2|default:''}" placeholder="FAQ 2 or Info" required>
                
                <input type="text" class="form-control mb-2" name="faq3" value="{$faq3|default:''}" placeholder="FAQ 3 or Info">
            </div>
            
                   <br>
                   
                            <!-- Phone number-->
<div class="mb-3">
    <label class="form-label"><i class="fa fa-phone"></i> Support Phone Number</label>
    <input type="text" class="form-control" name="phone" value="{$phone|default:''}" placeholder="Support Phone Number">
</div>
            
                <br>
                
                                  <!--Select router-->
                            <div class="mb-3">
                                <label class="form-label"><i class="fa fa-wifi"></i> Router</label>
                                <select class="form-control" name="router_id">
                                    <option value="">Select a router</option>
                                    {foreach $routers as $router}
                                        <option value="{$router.id}" {if $router.id eq $selected_router_id}selected{/if}>
                                            {$router.name}</option>
                                    {/foreach}
                                </select>
                            </div>
                            
                    <br>

                            <div class="text-end">
                                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save
                                    Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-info text-white">
                    <h3 class="mb-0"><i class="fa fa-info-circle"></i> Usage Instructions</h3>
                </div>
                <div class="card-body">
                    <ol class="list-group list-group-numbered">
                        <li class="list-group-item">Click "Save Changes" twice for quick upload.</li>
                        <li class="list-group-item">Customize and personalize your settings.</li>
                        <li class="list-group-item">Download the <code>login.html</code> file.</li>
                        <li class="list-group-item">Upload <code>login.html</code> to your MikroTik router.</li>
                        <li class="list-group-item">Upload in folder named hotspot/login.html(delete current one)</strong>.</li>
                        <li class="list-group-item">Ensure the file is named <strong>login.html</strong>.</li>
                        <li class="list-group-item">Add your website URL to the MikroTik walled garden.</li>
                        <div class="relative">
                            <pre id="scriptContent"
                                class="w-full p-3 border rounded-md text-sm bg-gray-50 overflow-auto">

/ip hotspot walled-garden
add dst-host={$_domain}
add dst-host=*.{$_domain}
/ip hotspot walled-garden ip
add action=accept dst-host={$_domain}
add action=accept dst-host={$main_domain}
add action=accept dst-host=*.{$main_domain}
/ip hotspot walled-garden
add dst-host=jsdelivr.com
add dst-host=cdn.tailwindcss.com
add dst-host=cdnjs.cloudflare.com
add dst-host=cdn.jsdelivr.net
add dst-host=sweetalert2.github.io
add dst-host=jsdelivr.com
add dst-host=www.jsdelivr.com
add dst-host=ajax.googleapis.com
add dst-host=sweetalert2.github.io
add dst-host=fonts.googleapis.com
add dst-host=fonts.gstatic.com
add dst-host=unpkg.com
add dst-host=kit.fontawesome.com
add dst-host=code.jquery.com
/ip hotspot walled-garden ip add action=accept dst-host="code.jquery.com"
/ip hotspot walled-garden ip add action=accept dst-host="cdn.jsdelivr.net"
/ip hotspot walled-garden ip add action=accept dst-host="cdnjs.cloudflare.com"
/ip hotspot walled-garden ip add action=accept dst-host="fonts.googleapis.com"
/ip hotspot walled-garden ip add action=accept dst-host="cdn.tailwindcss.com"
/ip hotspot walled-garden ip add action=accept dst-host="ajax.googleapis.com"


                        
                        </pre>


                        <button onclick="copyToClipboard()" class="btn btn-primary mt-4">
                        <i class="fa fa-copy"></i> Copy Script
                    </button>




                        </div>

                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    function copyToClipboard() {
        const scriptContent = document.getElementById("scriptContent").innerText;

        navigator.clipboard.writeText(scriptContent).then(() => {
            Swal.fire({
                icon: "success",
                title: "Copied!",
                text: "Walled garden script copied to clipboard!",
                timer: 2000,
                showConfirmButton: false
            });
        }).catch(err => {
            console.error("Failed to copy: ", err);
        });
    }

    function copyToClipboardSecond() {
        const scriptContent = document.getElementById("scriptContent_2").innerText;

        navigator.clipboard.writeText(scriptContent).then(() => {
            Swal.fire({
                icon: "success",
                title: "Copied!",
                text: "Walled garden script copied to clipboard!",
                timer: 2000,
                showConfirmButton: false
            });
        }).catch(err => {
            console.error("Failed to copy: ", err);
        });
    }
    
</script>


{include file="sections/footer.tpl"}
