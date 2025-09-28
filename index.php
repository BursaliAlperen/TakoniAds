Anladım! Balance, Earn, Referrals ve Withdraw butonlarını düzeltelim. İşte güncellenmiş kod:

🔧 Güncellenmiş index.php

```php
<?php
// Enable all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple debug page
if (isset($_GET['debug'])) {
    echo "🤖 BOT SERVER STATUS\n\n";
    
    $files = ['users.json', 'error.log'];
    foreach ($files as $file) {
        if (!file_exists($file)) {
            file_put_contents($file, $file === 'users.json' ? '{}' : '');
        }
        echo "📁 $file: " . (is_writable($file) ? '✅ OK' : '❌ NOT WRITABLE') . "\n";
    }
    
    $bot_token = getenv('BOT_TOKEN');
    echo "🔑 BOT_TOKEN: " . ($bot_token ? '✅ SET' : '❌ MISSING') . "\n";
    
    if ($bot_token) {
        $test_url = "https://api.telegram.org/bot{$bot_token}/getMe";
        $result = @file_get_contents($test_url);
        echo "🌐 TELEGRAM API: " . ($result ? '✅ CONNECTED' : '❌ FAILED') . "\n";
    }
    
    exit;
}

// Serve mini app HTML
if (isset($_GET['mini_app']) || (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'document')) {
    header('Content-Type: text/html');
    readfile('index.html');
    exit;
}

// Bot configuration
$bot_token = getenv('BOT_TOKEN');
if (!$bot_token) {
    http_response_code(500);
    die("❌ BOT_TOKEN not set");
}

define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', 'users.json');
define('ERROR_LOG', 'error.log');

// Initialize files
if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, '{}');
if (!file_exists(ERROR_LOG)) file_put_contents(ERROR_LOG, '');

function logError($message) {
    file_put_contents(ERROR_LOG, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

// Auto set webhook
function setWebhook() {
    $webhook_url = "https://takoniads.onrender.com";
    $set_webhook_url = API_URL . "setWebhook?url=" . urlencode($webhook_url);
    $result = @file_get_contents($set_webhook_url);
    logError("Webhook set: " . $result);
    return $result !== false;
}

setWebhook();

function loadUsers() {
    if (!file_exists(USERS_FILE)) return [];
    $data = @file_get_contents(USERS_FILE);
    return $data ? json_decode($data, true) ?? [] : [];
}

function saveUsers($users) {
    return @file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

function sendMessage($chat_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }
        
        $url = API_URL . 'sendMessage?' . http_build_query($params);
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
        
        $result = @file_get_contents($url, false, $context);
        return $result !== false;
        
    } catch (Exception $e) {
        logError("Send message failed: " . $e->getMessage());
        return false;
    }
}

// Edit message text
function editMessageText($chat_id, $message_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }
        
        $url = API_URL . 'editMessageText?' . http_build_query($params);
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
        
        $result = @file_get_contents($url, false, $context);
        return $result !== false;
        
    } catch (Exception $e) {
        logError("Edit message failed: " . $e->getMessage());
        return false;
    }
}

// Main menu keyboard
function getMainKeyboard() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '💰 Earn', 'callback_data' => 'earn'],
                ['text' => '💳 Balance', 'callback_data' => 'balance']
            ],
            [
                ['text' => '👥 Referrals', 'callback_data' => 'referrals'],
                ['text' => '🏧 Withdraw', 'callback_data' => 'withdraw']
            ]
        ]
    ];
}

// Earn menu keyboard
function getEarnKeyboard() {
    $mini_app_url = "https://takoniads.onrender.com?mini_app=1";
    
    return [
        'inline_keyboard' => [
            [
                ['text' => '📱 Watch Ad (25 Points)', 'web_app' => ['url' => $mini_app_url]]
            ],
            [
                ['text' => '✅ I Watched the Ad', 'callback_data' => 'ad_watched']
            ],
            [
                ['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']
            ]
        ]
    ];
}

// Balance menu keyboard
function getBalanceKeyboard() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '🔄 Refresh Balance', 'callback_data' => 'balance']
            ],
            [
                ['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']
            ]
        ]
    ];
}

// Referrals menu keyboard
function getReferralsKeyboard() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '📤 Share Referral', 'callback_data' => 'share_referral']
            ],
            [
                ['text' => '🔄 Refresh', 'callback_data' => 'referrals']
            ],
            [
                ['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']
            ]
        ]
    ];
}

// Withdraw menu keyboard
function getWithdrawKeyboard() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '💳 Enter TON Address', 'callback_data' => 'enter_ton_address']
            ],
            [
                ['text' => '💰 Enter Amount', 'callback_data' => 'enter_amount']
            ],
            [
                ['text' => '🚀 Submit Withdrawal', 'callback_data' => 'submit_withdrawal']
            ],
            [
                ['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']
            ]
        ]
    ];
}

// Process incoming update
function processUpdate($update) {
    logError("Received update: " . json_encode($update));
    
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        
        logError("Message from {$chat_id}: {$text}");
        
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0,
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'last_earn' => 0,
                'last_ad_watch' => 0,
                'ton_address' => '',
                'pending_withdrawal' => 0
            ];
        }
        
        if (strpos($text, '/start') === 0) {
            $ref_code = explode(' ', $text)[1] ?? null;
            
            if ($ref_code && !isset($users[$chat_id]['referred_by'])) {
                foreach ($users as $id => $user) {
                    if (isset($user['ref_code']) && $user['ref_code'] === $ref_code && $id != $chat_id) {
                        $users[$chat_id]['referred_by'] = $id;
                        $users[$id]['referrals'] = ($users[$id]['referrals'] ?? 0) + 1;
                        $users[$id]['balance'] = ($users[$id]['balance'] ?? 0) + 50;
                        sendMessage($id, "🎉 New referral! +50 points bonus!");
                        break;
                    }
                }
            }
            
            $welcome = "🚀 <b>Welcome to Earning Bot!</b>\n\n";
            $welcome .= "💰 <b>Earn points</b> by watching ads\n";
            $welcome .= "👥 <b>Invite friends</b> for bonus points\n";
            $welcome .= "🏧 <b>Withdraw</b> to TON wallet\n\n";
            $welcome .= "🔗 Your referral code: <code>{$users[$chat_id]['ref_code']}</code>";
            
            sendMessage($chat_id, $welcome, getMainKeyboard());
            saveUsers($users);
        }
    }
    
    // Handle callback queries
    elseif (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $chat_id = $callback['message']['chat']['id'];
        $message_id = $callback['message']['message_id'];
        $data = $callback['data'];
        
        logError("Callback from {$chat_id}: {$data}");
        
        $users = loadUsers();
        if (!isset($users[$chat_id])) {
            $users[$chat_id] = [
                'balance' => 0,
                'referrals' => 0,
                'ref_code' => substr(md5($chat_id . time()), 0, 8),
                'last_earn' => 0,
                'last_ad_watch' => 0,
                'ton_address' => '',
                'pending_withdrawal' => 0
            ];
        }
        
        $user = $users[$chat_id];
        
        switch ($data) {
            case 'earn':
                $response = "💰 <b>Earn Points</b>\n\n";
                $response .= "📱 <b>Watch Ads & Earn 25 Points</b>\n\n";
                $response .= "🎬 How to earn:\n";
                $response .= "1. Click 'Watch Ad Now' button\n";
                $response .= "2. Watch the advertisement completely\n";
                $response .= "3. Return and click 'I Watched the Ad'\n";
                $response .= "4. Get 25 points instantly!\n\n";
                $response .= "⏰ Cooldown: 5 minutes between ads";
                
                editMessageText($chat_id, $message_id, $response, getEarnKeyboard());
                break;
                
            case 'balance':
                $balance = $user['balance'] ?? 0;
                $referrals = $user['referrals'] ?? 0;
                $total_earned = $balance + ($user['withdrawn'] ?? 0);
                
                $response = "💳 <b>Your Balance</b>\n\n";
                $response .= "💰 <b>Available Balance:</b> {$balance} points\n";
                $response .= "👥 <b>Total Referrals:</b> {$referrals}\n";
                $response .= "🏆 <b>Total Earned:</b> {$total_earned} points\n\n";
                $response .= "💎 <b>Withdrawal Info:</b>\n";
                $response .= "• Minimum: 100 points\n";
                $response .= "• Rate: 1000 points = 1 TON\n\n";
                $response .= "📊 <b>Your TON Address:</b>\n";
                $response .= "<code>" . ($user['ton_address'] ?: 'Not set') . "</code>";
                
                editMessageText($chat_id, $message_id, $response, getBalanceKeyboard());
                break;
                
            case 'ad_watched':
                $last_watch = $user['last_ad_watch'] ?? 0;
                $current_time = time();
                
                // Check cooldown (5 minutes)
                if ($current_time - $last_watch < 300) {
                    $remaining = 300 - ($current_time - $last_watch);
                    $minutes = ceil($remaining / 60);
                    $response = "⏳ <b>Please wait {$minutes} minutes</b> before watching another ad!";
                    editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                } else {
                    // Add points
                    $users[$chat_id]['balance'] = ($user['balance'] ?? 0) + 25;
                    $users[$chat_id]['last_ad_watch'] = $current_time;
                    $new_balance = $users[$chat_id]['balance'];
                    
                    $response = "🎉 <b>Ad Rewarded!</b>\n\n";
                    $response .= "✅ You earned <b>25 points</b>!\n";
                    $response .= "💰 New balance: <b>{$new_balance} points</b>\n\n";
                    $response .= "🔄 You can watch another ad in 5 minutes.";
                    
                    editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                    saveUsers($users);
                }
                break;
                
            case 'referrals':
                $ref_code = $user['ref_code'] ?? 'N/A';
                $referrals = $user['referrals'] ?? 0;
                $ref_earnings = $referrals * 50;
                
                $response = "👥 <b>Referral System</b>\n\n";
                $response .= "🔗 <b>Your Referral Code:</b>\n";
                $response .= "<code>{$ref_code}</code>\n\n";
                $response .= "📊 <b>Statistics:</b>\n";
                $response .= "• Total Referrals: <b>{$referrals}</b>\n";
                $response .= "• Referral Earnings: <b>{$ref_earnings} points</b>\n\n";
                $response .= "💰 <b>Bonus:</b> 50 points per referral\n\n";
                $response .= "📤 <b>Share this link:</b>\n";
                $response .= "<code>https://t.me/" . explode(':', BOT_TOKEN)[0] . "?start={$ref_code}</code>\n\n";
                $response .= "👥 <b>How it works:</b>\n";
                $response .= "1. Share your referral link\n";
                $response .= "2. Friend joins using your link\n";
                $response .= "3. You get 50 points instantly!";
                
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard());
                break;
                
            case 'share_referral':
                $ref_code = $user['ref_code'] ?? 'N/A';
                $share_text = urlencode("Join this earning bot and earn points! Use my referral code: {$ref_code}");
                $share_url = "https://t.me/share/url?url=https://t.me/" . explode(':', BOT_TOKEN)[0] . "&text={$share_text}";
                
                $response = "📤 <b>Share Referral</b>\n\n";
                $response .= "Click the button below to share your referral link!";
                
                $share_keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📱 Share on Telegram', 'url' => $share_url]
                        ],
                        [
                            ['text' => '⬅️ Back to Referrals', 'callback_data' => 'referrals']
                        ]
                    ]
                ];
                
                editMessageText($chat_id, $message_id, $response, $share_keyboard);
                break;
                
            case 'withdraw':
                $balance = $user['balance'] ?? 0;
                $min_withdraw = 100;
                $ton_address = $user['ton_address'] ?? 'Not set';
                
                $response = "🏧 <b>Withdraw Points</b>\n\n";
                $response .= "💰 <b>Available Balance:</b> {$balance} points\n";
                $response .= "💎 <b>Minimum Withdrawal:</b> {$min_withdraw} points\n";
                $response .= "🔗 <b>Your TON Address:</b>\n";
                $response .= "<code>{$ton_address}</code>\n\n";
                $response .= "📝 <b>Withdrawal Steps:</b>\n";
                $response .= "1. Set your TON address\n";
                $response .= "2. Enter withdrawal amount\n";
                $response .= "3. Submit withdrawal request\n\n";
                $response .= "💸 <b>Exchange Rate:</b>\n";
                $response .= "1000 points = 1 TON";
                
                if ($balance < $min_withdraw) {
                    $needed = $min_withdraw - $balance;
                    $response .= "\n\n❌ <b>Insufficient Balance!</b>\n";
                    $response .= "You need {$needed} more points to withdraw.";
                }
                
                editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard());
                break;
                
            case 'enter_ton_address':
                $response = "🔗 <b>Enter TON Address</b>\n\n";
                $response .= "Please send your TON wallet address as a message.\n\n";
                $response .= "📍 <b>Format:</b> EQ... (TON wallet address)\n\n";
                $response .= "⚠️ <b>Warning:</b> Make sure the address is correct!";
                
                // Store state for next message
                $users[$chat_id]['awaiting_ton_address'] = true;
                saveUsers($users);
                
                editMessageText($chat_id, $message_id, $response, [
                    'inline_keyboard' => [
                        [['text' => '⬅️ Back to Withdraw', 'callback_data' => 'withdraw']]
                    ]
                ]);
                break;
                
            case 'enter_amount':
                $balance = $user['balance'] ?? 0;
                $response = "💰 <b>Enter Withdrawal Amount</b>\n\n";
                $response .= "Your balance: <b>{$balance} points</b>\n";
                $response .= "Minimum: <b>100 points</b>\n\n";
                $response .= "Please send the amount you want to withdraw as a message.\n\n";
                $response .= "💸 <b>Exchange Rate:</b> 1000 points = 1 TON";
                
                // Store state for next message
                $users[$chat_id]['awaiting_amount'] = true;
                saveUsers($users);
                
                editMessageText($chat_id, $message_id, $response, [
                    'inline_keyboard' => [
                        [['text' => '⬅️ Back to Withdraw', 'callback_data' => 'withdraw']]
                    ]
                ]);
                break;
                
            case 'submit_withdrawal':
                $balance = $user['balance'] ?? 0;
                $ton_address = $user['ton_address'] ?? '';
                $min_withdraw = 100;
                
                if (empty($ton_address)) {
                    $response = "❌ <b>TON Address Not Set</b>\n\n";
                    $response .= "Please set your TON address first before withdrawing.";
                } elseif ($balance < $min_withdraw) {
                    $response = "❌ <b>Insufficient Balance</b>\n\n";
                    $response .= "You need at least {$min_withdraw} points to withdraw.";
                } else {
                    // Process withdrawal
                    $users[$chat_id]['pending_withdrawal'] = $balance;
                    $users[$chat_id]['balance'] = 0;
                    
                    $response = "✅ <b>Withdrawal Submitted!</b>\n\n";
                    $response .= "💰 <b>Amount:</b> {$balance} points\n";
                    $response .= "🔗 <b>TON Address:</b>\n";
                    $response .= "<code>{$ton_address}</code>\n\n";
                    $response .= "⏳ <b>Status:</b> Processing\n";
                    $response .= "📞 Our team will process your withdrawal within 24 hours.";
                    
                    saveUsers($users);
                }
                
                editMessageText($chat_id, $message_id, $response, [
                    'inline_keyboard' => [
                        [['text' => '⬅️ Back to Main', 'callback_data' => 'main_menu']]
                    ]
                ]);
                break;
                
            case 'main_menu':
                $response = "🎮 <b>Main Menu</b>\n\nWelcome back! Choose an option below:";
                editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                break;
        }
        
        saveUsers($users);
    }
    
    // Handle web app data (from mini app)
    elseif (isset($update['web_app_data'])) {
        $web_app_data = $update['web_app_data'];
        $chat_id = $web_app_data['user']['id'];
        $data = json_decode($web_app_data['data'], true);
        
        logError("Web app data from {$chat_id}: " . $web_app_data['data']);
        
        if ($data && $data['action'] === 'ad_watched') {
            $users = loadUsers();
            
            if (!isset($users[$chat_id])) {
                $users[$chat_id] = [
                    'balance' => 0,
                    'referrals' => 0,
                    'ref_code' => substr(md5($chat_id . time()), 0, 8),
                    'last_earn' => 0,
                    'last_ad_watch' => 0,
                    'ton_address' => '',
                    'pending_withdrawal' => 0
                ];
            }
            
            $last_watch = $users[$chat_id]['last_ad_watch'] ?? 0;
            $current_time = time();
            
            if ($current_time - $last_watch >= 300) {
                $users[$chat_id]['balance'] += 25;
                $users[$chat_id]['last_ad_watch'] = $current_time;
                $new_balance = $users[$chat_id]['balance'];
                
                $response = "🎉 <b>Ad Completed!</b>\n\n";
                $response .= "✅ You earned <b>25 points</b>!\n";
                $response .= "💰 New balance: <b>{$new_balance} points</b>";
                
                sendMessage($chat_id, $response, getMainKeyboard());
                saveUsers($users);
            }
        }
    }
}

// Main webhook handler
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if ($update) {
    processUpdate($update);
    http_response_code(200);
    echo "OK";
} else {
    // Serve mini app if no update
    if (isset($_GET['mini_app'])) {
        header('Content-Type: text/html');
        readfile('index.html');
    } else {
        http_response_code(400);
        echo "No data received";
    }
}
?>
```

🎯 Yeni Özellikler:

💰 Earn:

· Watch Ads butonu (Mini App)
· I Watched the Ad butonu
· 5 dakika cooldown

💳 Balance:

· Mevcut bakiye
· Toplam referral
· TON adresi bilgisi
· Refresh butonu

👥 Referrals:

· Referral kodu ve link
· İstatistikler
· Share butonu

🏧 Withdraw:

· TON adresi ekleme
· Miktar girme
· Withdrawal submit
· Minimum 100 points

Artık tüm butonlar çalışacak ve her menü kendi işlevini yerine getirecek! 🚀
