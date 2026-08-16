<?php
$b64 = trim(file_get_contents(__DIR__ . '/pamnet_safe_reminders.b64.txt'));
$js = '(async () => { const b64 = ' . json_encode($b64)
    . '; const bin = atob(b64); const bytes = new Uint8Array(bin.length);'
    . ' for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);'
    . ' const file = new File([bytes], "pamnet_safe_reminders.zip", { type: "application/zip" });'
    . ' const input = document.querySelector("input[type=file][name=zip_plugin]");'
    . ' if (!input) return { ok: false, err: "no input" };'
    . ' const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;'
    . ' window.ask = function () { return true; };'
    . ' const form = input.closest("form"); form.submit();'
    . ' return { ok: true, size: file.size, name: file.name, head: b64.slice(0, 40) }; })()';
file_put_contents(__DIR__ . '/upload_plugin.js', $js);
echo strlen($js) . PHP_EOL;
