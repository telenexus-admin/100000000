<?php
require_once dirname(__DIR__) . '/init.php';

echo "============================================\n";
echo "SMS FORMAT FIX\n";
echo "============================================\n\n";

// Current SMS URL
$sms_url = $config['sms_url'] ?? '';
echo "Current SMS URL: $sms_url\n\n";

// Test with different message formats
$phone = '254759924977';
$testMessages = [
    'Simple text' => 'Test SMS from Voky',
    'No emoji' => 'Router Alert: Test message',
    'Plain text' => 'Voky Alert: Test message',
    'URL encoded' => urlencode('Test SMS from Voky'),
];

echo "Testing different message formats:\n";
echo str_repeat("-", 60) . "\n";

foreach ($testMessages as $format => $message) {
    echo "\n📱 Testing format: $format\n";
    
    // Build URL
    $testUrl = str_replace('[number]', urlencode($phone), $sms_url);
    $testUrl = str_replace('[text]', $message, $testUrl); // Try without urlencode first
    
    echo "   URL: $testUrl\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "   Response: $response\n";
    
    if (strpos($response, 'Success') !== false || strpos($response, 'OK') !== false) {
        echo "   ✅ WORKING FORMAT FOUND!\n";
        
        // Save the working format
        echo "\n✅ Use this URL format:\n";
        echo str_replace('[text]', '[message]', $testUrl) . "\n";
        echo "Where [message] should be plain text without special characters\n";
    }
}

echo "\n============================================\n";
echo "Recommended SMS Function Update:\n";
echo "============================================\n";
echo "
Replace your sendSms function with:

function sendSms(\$phone, \$message) {
    global \$config;
    
    // Clean the message - remove emojis and special chars
    \$message = preg_replace('/[^\\x00-\\x7F]/', '', \$message); // Remove emojis
    \$message = trim(\$message);
    
    \$smsUrl = str_replace('[number]', urlencode(\$phone), \$config['sms_url']);
    \$smsUrl = str_replace('[text]', urlencode(\$message), \$smsUrl);
    
    \$ch = curl_init();
    curl_setopt(\$ch, CURLOPT_URL, \$smsUrl);
    curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(\$ch, CURLOPT_TIMEOUT, 30);
    \$response = curl_exec(\$ch);
    curl_close(\$ch);
    
    return \$response;
}
";
