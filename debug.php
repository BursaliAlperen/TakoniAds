<?php
// Basit hata ayıklama
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "✅ Bot is working!";

// Test file permissions
$files = ['users.json', 'error.log'];
foreach ($files as $file) {
    if (!file_exists($file)) {
        file_put_contents($file, $file === 'users.json' ? '{}' : '');
        chmod($file, 0666);
    }
    $writable = is_writable($file) ? 'writable' : 'NOT writable';
    echo "<br>📁 $file: $writable";
}

// Test bot token
$bot_token = getenv('BOT_TOKEN') ?: 'NOT_SET';
echo "<br>🤖 Bot Token: " . (empty($bot_token) ? 'NOT_SET' : 'SET');

// Test JSON functions
$test_json = json_encode(['test' => true]);
echo "<br>📝 JSON: " . ($test_json ? 'WORKING' : 'ERROR');

echo "<br><br>🎉 All basic tests passed!";
?>
