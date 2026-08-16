<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Error - RAYPROTECH </title>
    <link rel="shortcut icon" href="{$app_url}/ui/ui/images/logo.png" type="image/x-icon" />

    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/bootstrap.min.css">

    <link rel="stylesheet" href="{$app_url}/ui/ui/fonts/ionicons/css/ionicons.min.css">
    <link rel="stylesheet" href="{$app_url}/ui/ui/fonts/font-awesome/css/font-awesome.min.css">

    <link rel="stylesheet" href="{$app_url}/ui/ui/styles/modern-AdminLTE.min.css">

    <style>
        ::-moz-selection {
            /* Code for Firefox */
            color: red;
            background: yellow;
        }

        ::selection {
            color: red;
            background: yellow;
        }
    </style>

</head>

<body class="hold-transition skin-blue">
    <div class="container">

        <section class="content">
            <div class="row">
                <div class="col-md-10 col-md-offset-1">
                    <div class="box box-danger box-solid">
                        <section class="content-header">
                            <h1 class="text-center">
                                {$error_title}
                            </h1>
                        </section>
                        <div class="box-body" style="font-size: larger;">
                            <center>
                            <img src="{$app_url}/ui/ui/images/error.png" class="img-responsive hidden-sm hidden-xs"></center>
                            <br>

                            {if isset($error_message) && $error_message != ''}
                            <details open style="margin-top:15px;">
                                <summary style="cursor:pointer; font-weight:bold; color:#c9302c; font-size:14px; padding:8px; background:#f9f2f2; border:1px solid #ebccd1; border-radius:4px;">
                                    <i class="fa fa-bug"></i> Debug Details (click to collapse)
                                </summary>
                                <div style="margin-top:10px; padding:12px; background:#1e1e1e; color:#f8f8f2; border-radius:4px; font-family:Consolas,'Courier New',monospace; font-size:12px; line-height:1.5; max-height:500px; overflow:auto;">
                                    {$error_message}
                                </div>
                                <p style="margin-top:8px; font-size:12px; color:#666;">
                                    <i class="fa fa-info-circle"></i>
                                    File: <code>system/boot.php</code> &middot; Time: {date('Y-m-d H:i:s')}
                                </p>
                            </details>
                            {/if}

                            <br>
                                                   </div>

                            <a href="javascript::history.back()" onclick="history.back()"
                                class="btn btn-warning btn-block">{Lang::T('Back')}</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <img src="{$app_url}/ui/ui/images/error.png" class="img-responsive hidden-md hidden-lg">
                </div>
            </div>
        </section>
    </div>
</body>

</html>
