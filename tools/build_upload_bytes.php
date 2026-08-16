<?php
$bin = file_get_contents(__DIR__ . '/pamnet_safe_reminders.zip');
$nums = implode(',', unpack('C*', $bin));
$js = '(async()=>{const bytes=new Uint8Array([' . $nums . ']);'
    . 'const file=new File([bytes],"pamnet_safe_reminders.zip",{type:"application/zip"});'
    . 'const input=document.querySelector("input[type=file][name=zip_plugin]");'
    . 'if(!input)return{ok:false,err:"no input"};'
    . 'const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;'
    . 'window.ask=()=>true;input.closest("form").submit();'
    . 'return{ok:true,size:file.size};})()';
file_put_contents(__DIR__ . '/upload_bytes.js', $js);
echo 'jslen=' . strlen($js) . ' ziplen=' . strlen($bin) . PHP_EOL;
