<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$_title}</title>
    <link rel="shortcut icon" type="image/x-icon" href="{$app_url}/ui/ui/images/favicon.ico">
    <style>
        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --primary-light: #fed7aa;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 10px;
        }

        .invoice {
            width: 100%;
            max-width: 70mm;
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
            border: 1px solid var(--primary-light);
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
            color: var(--primary-dark);
        }
        
        .header p {
            margin: 5px 0 0;
            font-size: 12px;
            color: #1e293b;
        }

        .details {
            margin-bottom: 15px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 10px;
            text-align: left;
        }

        .details div {
            margin: 5px 0;
            font-weight: bold;
            color: #1e293b;
        }
        
        .details div strong {
            color: var(--primary-dark);
            min-width: 70px;
            display: inline-block;
        }

        .invoice-info {
            margin: 15px 0;
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-info th,
        .invoice-info td {
            padding: 8px 5px;
            text-align: left;
            color: #1e293b;
            font-weight: bold;
            border-bottom: 1px dashed var(--primary-light);
        }

        .invoice-info th {
            background-color: var(--primary);
            color: white;
            border-radius: 5px 5px 0 0;
        }
        
        .invoice-info tr:last-child td {
            border-bottom: none;
        }
        
        .invoice-info td:first-child {
            color: var(--primary-dark);
        }

        .footer {
            margin-top: 15px;
            text-align: left;
            border-top: 2px solid var(--primary);
            padding-top: 10px;
        }
        
        .footer p {
            margin: 0;
            color: #1e293b;
            font-size: 11px;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                font-size: 12px;
            }

            .invoice {
                width: 70mm;
                padding: 5px;
                box-shadow: none;
                border: 1px solid #000;
            }

            .details {
                margin-bottom: 5px;
                text-align: left;
            }

            .invoice-info th,
            .invoice-info td {
                padding: 5px;
                text-align: left;
                font-weight: bold;
                color: black;
                border-bottom: 1px dashed #000;
            }
            
            .invoice-info th {
                background-color: #f97316;
                color: white;
            }

            hr {
                border: 1px solid #000;
            }

            .btn {
                display: none;
            }
        }
        
        .btn-success {
            background: linear-gradient(145deg, #f97316, #ea580c);
            border: none;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        
        pre {
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            border: none !important;
            background-color: transparent !important;
        }
    </style>
    <script type="text/javascript">
        function printpage() {
            window.print();
        }
    </script>
</head>

<body {if !$nuxprint} onload="printpage()" {/if}>
    <div class="container">
        <div class="invoice">
            {if $content}
                <pre style="border-style: none; background-color: white; color: #1e293b;">{$content}</pre>
            {else}
                <div class="header">
                    <h1>{Lang::pad($_c['CompanyName'], ' ', 2)}</h1>
                    <p>{Lang::pad($_c['address'], ' ', 2)} | {Lang::pad($_c['phone'], ' ', 2)}</p>
                </div>
                <div class="details">
                    <div><strong>{Lang::pad(Lang::T('Invoice'), ' ', 2)}:</strong> {$in['invoice']}</div>
                    <div><strong>{Lang::pad(Lang::T('Date'), ' ', 2)}:</strong> {$date}</div>
                    <div><strong>{Lang::pad(Lang::T('Sales'), ' ', 2)}:</strong> {Lang::pad($_admin['fullname'], ' ', 2)}</div>
                </div>

                <table class="invoice-info">
                    <tr>
                        <th>{Lang::pad(Lang::T('Type'), ' ', 2)}</th>
                        <td>{$in['type']}</td>
                    </tr>
                    <tr>
                        <th>{Lang::pad(Lang::T('Package Name'), ' ', 2)}</th>
                        <td>{$in['plan_name']}</td>
                    </tr>
                    <tr>
                        <th>{Lang::pad(Lang::T('Package Price'), ' ', 2)}</th>
                        <td>{Lang::moneyFormat($in['price'])}</td>
                    </tr>
                    <tr>
                        <th>{Lang::pad(Lang::T('Username'), ' ', 2)}</th>
                        <td>{$in['username']}</td>
                    </tr>
                    <tr>
                        <th>{Lang::pad(Lang::T('Password'), ' ', 2)}</th>
                        <td>**********</td>
                    </tr>
                    <tr>
                        <th>{Lang::pad(Lang::T('Payment Method'), ' ', 2)}</th>
                        <td>{$in['method']}</td>
                    </tr>
                    {if $in['type'] != 'Balance'}
                        <tr>
                            <th>{Lang::pad(Lang::T('Created On'), ' ', 2)}</th>
                            <td>{Lang::dateAndTimeFormat($in['recharged_on'], $in['recharged_time'])}</td>
                        </tr>
                        <tr>
                            <th>{Lang::pad(Lang::T('Expires On'), ' ', 2)}</th>
                            <td>{Lang::dateAndTimeFormat($in['expiration'], $in['time'])}</td>
                        </tr>
                    {/if}
                </table>

                <hr style="border: 2px solid #f97316; margin-top: 10px;">

                <div class="footer">
                    <p>{Lang::pad($_c['note'], ' ', 2)}</p>
                    {if $nuxprint}
                        <a href="{$nuxprint}" class="btn btn-success" name="nux" value="print">
                            <i class="glyphicon glyphicon-print"></i> Nux Print
                        </a>
                    {/if}
                </div>
            {/if}
        </div>
    </div>

    <script src="{$app_url}/ui/ui/scripts/jquery.min.js"></script>
    <script src="{$app_url}/ui/ui/scripts/bootstrap.min.js"></script>
    {if isset($xfooter)} {$xfooter} {/if}
</body>

</html>