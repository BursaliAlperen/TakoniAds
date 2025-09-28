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

// TON Rewards - UPDATED AMOUNTS
define('AD_REWARD', 0.0001); // 0.0001 TON per ad
define('REF_REWARD', 0.0005); // 0.0005 TON per referral
define('MIN_WITHDRAW_REF', 5); // Minimum 5 referrals to withdraw
define('MIN_WITHDRAW_AMOUNT', 0.01); // Minimum 0.01 TON to withdraw (UPDATED)
define('AD_COOLDOWN', 10); // 10 seconds cooldown between ads

// Initialize files with proper error handling
if (!file_exists(USERS_FILE)) {
    @file_put_contents(USERS_FILE, '{}');
}
if (!file_exists(ERROR_LOG)) {
    @file_put_contents(ERROR_LOG, '');
}

// Fix file permissions on first run
if (file_exists(USERS_FILE) && !is_writable(USERS_FILE)) {
    @chmod(USERS_FILE, 0666);
}
if (file_exists(ERROR_LOG) && !is_writable(ERROR_LOG)) {
    @chmod(ERROR_LOG, 0666);
}

function logError($message) {
    // Safe error logging with permission check
    if (is_writable(ERROR_LOG) || (!file_exists(ERROR_LOG) && is_writable(dirname(ERROR_LOG)))) {
        @file_put_contents(ERROR_LOG, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND | LOCK_EX);
    }
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
    if (!file_exists(USERS_FILE)) {
        @file_put_contents(USERS_FILE, '{}');
        return [];
    }
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

// Generate unique referral code based on user ID (always the same)
function generateRefCode($chat_id) {
    return 'TAK' . substr(md5($chat_id), 0, 7);
}

// Main menu keyboard
function getMainKeyboard() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '💰 Earn TON', 'callback_data' => 'earn'],
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
                ['text' => '📱 Watch Ad (' . AD_REWARD . ' TON)', 'web_app' => ['url' => $mini_app_url]]
            ],
            [
                ['text' => '🔄 Check Balance', 'callback_data' => 'balance']
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
                ['text' => '📱 Watch Another Ad', 'callback_data' => 'earn']
            ],
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
        
        // Check if user exists, if not create with permanent ref code
        if (!isset($users[$chat_id])) {
            $ref_code = generateRefCode($chat_id);
            $users[$chat_id] = [
                'balance' => 0,
                'referrals' => 0,
                'ref_code' => $ref_code,
                'last_ad_watch' => 0,
                'ton_address' => '',
                'pending_withdrawal' => 0,
                'total_earned' => 0,
                'created_at' => time()
            ];
            saveUsers($users);
        }
        
        if (strpos($text, '/start') === 0) {
            $ref_code_param = explode(' ', $text)[1] ?? null;
            $user = $users[$chat_id];
            
            // Handle referral registration - FIXED
            if ($ref_code_param && $ref_code_param !== $user['ref_code'] && !isset($user['referred_by'])) {
                $referrer_found = false;
                
                foreach ($users as $id => $u) {
                    if (isset($u['ref_code']) && $u['ref_code'] === $ref_code_param && $id != $chat_id) {
                        // Register referral - IMMEDIATE SAVE
                        $users[$chat_id]['referred_by'] = $id;
                        $users[$id]['referrals'] = ($users[$id]['referrals'] ?? 0) + 1;
                        $users[$id]['balance'] = ($users[$id]['balance'] ?? 0) + REF_REWARD;
                        $users[$id]['total_earned'] = ($users[$id]['total_earned'] ?? 0) + REF_REWARD;
                        
                        // Save users immediately after referral
                        saveUsers($users);
                        
                        // Notify referrer - with updated data
                        $ref_message = "🎉 <b>New Referral!</b>\n\n";
                        $ref_message .= "👤 New user joined using your referral link!\n";
                        $ref_message .= "💰 You earned: <b>" . REF_REWARD . " TON</b>\n";
                        $ref_message .= "👥 Total referrals: <b>{$users[$id]['referrals']}</b>\n";
                        $ref_message .= "💳 New balance: <b>" . number_format($users[$id]['balance'], 6) . " TON</b>";
                        sendMessage($id, $ref_message);
                        
                        $referrer_found = true;
                        break;
                    }
                }
                
                if ($referrer_found) {
                    $welcome = "🎉 <b>Welcome via Referral!</b>\n\n";
                    $welcome .= "You joined using a referral link!\n\n";
                } else {
                    $welcome = "🚀 <b>Welcome to TAKONI ADS!</b>\n\n";
                }
            } else {
                $welcome = "🚀 <b>Welcome to TAKONI ADS!</b>\n\n";
            }
            
            $welcome .= "💰 <b>Earn TON</b> by watching ads\n";
            $welcome .= "👥 <b>Invite friends</b> for bonus TON\n";
            $welcome .= "🏧 <b>Withdraw</b> to TON wallet\n\n";
            $welcome .= "🔗 <b>Your permanent referral code:</b>\n";
            $welcome .= "<code>{$users[$chat_id]['ref_code']}</code>\n\n";
            $welcome .= "📊 <b>Rewards:</b>\n";
            $welcome .= "• Watch Ad: <b>" . AD_REWARD . " TON</b>\n";
            $welcome .= "• Per Referral: <b>" . REF_REWARD . " TON</b>";
            
            sendMessage($chat_id, $welcome, getMainKeyboard());
        }
        
        // Handle TON address input
        elseif (isset($users[$chat_id]['awaiting_ton_address'])) {
            $ton_address = trim($text);
            
            // Basic TON address validation
            if (strlen($ton_address) >= 10 && (strpos($ton_address, 'EQ') === 0 || strpos($ton_address, 'UQ') === 0)) {
                $users[$chat_id]['ton_address'] = $ton_address;
                unset($users[$chat_id]['awaiting_ton_address']);
                
                $response = "✅ <b>TON Address Saved!</b>\n\n";
                $response .= "🔗 Your TON address:\n";
                $response .= "<code>{$ton_address}</code>\n\n";
                $response .= "You can now submit withdrawal requests.";
                
                sendMessage($chat_id, $response, getWithdrawKeyboard());
                saveUsers($users);
            } else {
                $response = "❌ <b>Invalid TON Address</b>\n\n";
                $response .= "Please enter a valid TON wallet address.\n";
                $response .= "📍 <b>Format:</b> EQ... or UQ...\n\n";
                $response .= "Please try again:";
                
                sendMessage($chat_id, $response);
            }
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
            $ref_code = generateRefCode($chat_id);
            $users[$chat_id] = [
                'balance' => 0,
                'referrals' => 0,
                'ref_code' => $ref_code,
                'last_ad_watch' => 0,
                'ton_address' => '',
                'pending_withdrawal' => 0,
                'total_earned' => 0,
                'created_at' => time()
            ];
            saveUsers($users);
        }
        
        $user = $users[$chat_id];
        
        switch ($data) {
            case 'earn':
                $response = "💰 <b>Earn TON</b>\n\n";
                $response .= "📱 <b>Watch Ads & Earn " . AD_REWARD . " TON Each</b>\n\n";
                $response .= "🎬 How to earn:\n";
                $response .= "1. Click 'Watch Ad Now' button\n";
                $response .= "2. Watch the advertisement completely\n";
                $response .= "3. Get " . AD_REWARD . " TON automatically!\n\n";
                $response .= "⏰ Cooldown: " . AD_COOLDOWN . " seconds between ads\n\n";
                $response .= "👥 <b>Referral Bonus:</b> " . REF_REWARD . " TON per friend";
                
                editMessageText($chat_id, $message_id, $response, getEarnKeyboard());
                break;
                
            case 'balance':
                $balance = $user['balance'] ?? 0;
                $referrals = $user['referrals'] ?? 0;
                $total_earned = $user['total_earned'] ?? $balance;
                
                $response = "💳 <b>Your TON Balance</b>\n\n";
                $response .= "💰 <b>Available Balance:</b> " . number_format($balance, 6) . " TON\n";
                $response .= "👥 <b>Total Referrals:</b> {$referrals}\n";
                $response .= "🏆 <b>Total Earned:</b> " . number_format($total_earned, 6) . " TON\n\n";
                $response .= "💎 <b>Withdrawal Requirements:</b>\n";
                $response .= "• Minimum " . MIN_WITHDRAW_REF . " referrals\n";
                $response .= "• Minimum " . MIN_WITHDRAW_AMOUNT . " TON balance\n\n";
                $response .= "🔗 <b>Your TON Address:</b>\n";
                $response .= "<code>" . ($user['ton_address'] ?: 'Not set') . "</code>";
                
                editMessageText($chat_id, $message_id, $response, getBalanceKeyboard());
                break;
                
            case 'referrals':
                $ref_code = $user['ref_code'] ?? 'N/A';
                $referrals = $user['referrals'] ?? 0;
                $ref_earnings = $referrals * REF_REWARD;
                
                $response = "👥 <b>Referral System</b>\n\n";
                $response .= "🔗 <b>Your Permanent Referral Code:</b>\n";
                $response .= "<code>{$ref_code}</code>\n\n";
                $response .= "📊 <b>Statistics:</b>\n";
                $response .= "• Total Referrals: <b>{$referrals}</b>\n";
                $response .= "• Referral Earnings: <b>" . number_format($ref_earnings, 6) . " TON</b>\n\n";
                $response .= "💰 <b>Bonus:</b> " . REF_REWARD . " TON per referral\n\n";
                $response .= "📤 <b>Share this link:</b>\n";
                $response .= "<code>https://t.me/takoniAdsBot?start={$ref_code}</code>\n\n";
                $response .= "👥 <b>How it works:</b>\n";
                $response .= "1. Share your referral link\n";
                $response .= "2. Friend joins using your link\n";
                $response .= "3. You get " . REF_REWARD . " TON instantly!";
                
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard());
                break;
                
            case 'share_referral':
                $ref_code = $user['ref_code'] ?? 'N/A';
                $share_text = urlencode("🚀 Join TAKONI ADS and earn TON coins by watching ads! Use my referral code: {$ref_code} - https://t.me/takoniAdsBot?start={$ref_code}");
                $share_url = "https://t.me/share/url?url=https://t.me/takoniAdsBot&text={$share_text}";
                
                $response = "📤 <b>Share Referral</b>\n\n";
                $response .= "Click the button below to share your referral link on Telegram!";
                
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
                $referrals = $user['referrals'] ?? 0;
                $ton_address = $user['ton_address'] ?? 'Not set';
                
                $response = "🏧 <b>Withdraw TON</b>\n\n";
                $response .= "💰 <b>Available Balance:</b> " . number_format($balance, 6) . " TON\n";
                $response .= "👥 <b>Your Referrals:</b> {$referrals}/" . MIN_WITHDRAW_REF . "\n";
                $response .= "🔗 <b>Your TON Address:</b>\n";
                $response .= "<code>{$ton_address}</code>\n\n";
                
                // Check requirements
                $can_withdraw = true;
                $requirements = [];
                
                if ($referrals < MIN_WITHDRAW_REF) {
                    $can_withdraw = false;
                    $requirements[] = "❌ Need " . (MIN_WITHDRAW_REF - $referrals) . " more referrals";
                }
                
                if ($balance < MIN_WITHDRAW_AMOUNT) {
                    $can_withdraw = false;
                    $needed = MIN_WITHDRAW_AMOUNT - $balance;
                    $requirements[] = "❌ Need " . number_format($needed, 6) . " more TON";
                }
                
                if (empty($ton_address) || $ton_address === 'Not set') {
                    $can_withdraw = false;
                    $requirements[] = "❌ TON address not set";
                }
                
                if ($can_withdraw) {
                    $response .= "✅ <b>All requirements met!</b>\n\n";
                    $response .= "You can submit your withdrawal request.";
                } else {
                    $response .= "📋 <b>Withdrawal Requirements:</b>\n";
                    $response .= "• Minimum " . MIN_WITHDRAW_REF . " referrals\n";
                    $response .= "• Minimum " . MIN_WITHDRAW_AMOUNT . " TON balance\n";
                    $response .= "• TON address must be set\n\n";
                    $response .= "❌ <b>Missing Requirements:</b>\n";
                    $response .= implode("\n", $requirements);
                }
                
                editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard());
                break;
                
            case 'enter_ton_address':
                $response = "🔗 <b>Enter TON Address</b>\n\n";
                $response .= "Please send your TON wallet address as a message.\n\n";
                $response .= "📍 <b>Format:</b> EQ... or UQ... (TON wallet address)\n\n";
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
                
            case 'submit_withdrawal':
                $balance = $user['balance'] ?? 0;
                $referrals = $user['referrals'] ?? 0;
                $ton_address = $user['ton_address'] ?? '';
                
                // Validate requirements
                if (empty($ton_address)) {
                    $response = "❌ <b>TON Address Not Set</b>\n\n";
                    $response .= "Please set your TON address first before withdrawing.";
                } elseif ($referrals < MIN_WITHDRAW_REF) {
                    $response = "❌ <b>Insufficient Referrals</b>\n\n";
                    $response .= "You need " . MIN_WITHDRAW_REF . " referrals to withdraw.\n";
                    $response .= "Current: {$referrals}/" . MIN_WITHDRAW_REF;
                } elseif ($balance < MIN_WITHDRAW_AMOUNT) {
                    $response = "❌ <b>Insufficient Balance</b>\n\n";
                    $response .= "You need at least " . MIN_WITHDRAW_AMOUNT . " TON to withdraw.\n";
                    $response .= "Current: " . number_format($balance, 6) . " TON";
                } else {
                    // Process withdrawal
                    $users[$chat_id]['pending_withdrawal'] = $balance;
                    $users[$chat_id]['balance'] = 0;
                    
                    $response = "✅ <b>Withdrawal Submitted!</b>\n\n";
                    $response .= "💰 <b>Amount:</b> " . number_format($balance, 6) . " TON\n";
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
    }
    
    // Handle web app data (from mini app) - AUTOMATIC REWARD
    elseif (isset($update['web_app_data'])) {
        $web_app_data = $update['web_app_data'];
        $chat_id = $web_app_data['user']['id'];
        $data = json_decode($web_app_data['data'], true);
        
        logError("Web app data from {$chat_id}: " . $web_app_data['data']);
        
        if ($data && $data['action'] === 'ad_watched' && $data['verified'] === true) {
            $users = loadUsers();
            
            if (!isset($users[$chat_id])) {
                $ref_code = generateRefCode($chat_id);
                $users[$chat_id] = [
                    'balance' => 0,
                    'referrals' => 0,
                    'ref_code' => $ref_code,
                    'last_ad_watch' => 0,
                    'ton_address' => '',
                    'pending_withdrawal' => 0,
                    'total_earned' => 0,
                    'created_at' => time()
                ];
            }
            
            $last_watch = $users[$chat_id]['last_ad_watch'] ?? 0;
            $current_time = time();
            
            // Check cooldown (10 seconds)
            if ($current_time - $last_watch < AD_COOLDOWN) {
                $remaining = AD_COOLDOWN - ($current_time - $last_watch);
                $response = "⏳ <b>Please wait {$remaining} seconds</b> before watching another ad!";
            } else {
                // Add TON reward automatically
                $old_balance = $users[$chat_id]['balance'];
                $users[$chat_id]['balance'] += AD_REWARD;
                $users[$chat_id]['total_earned'] = ($users[$chat_id]['total_earned'] ?? 0) + AD_REWARD;
                $users[$chat_id]['last_ad_watch'] = $current_time;
                $new_balance = $users[$chat_id]['balance'];
                
                logError("User {$chat_id} earned " . AD_REWARD . " TON. Old: {$old_balance}, New: {$new_balance}");
                
                $response = "🎉 <b>Ad Completed Successfully!</b>\n\n";
                $response .= "✅ You earned <b>" . AD_REWARD . " TON</b>!\n";
                $response .= "💰 New balance: <b>" . number_format($new_balance, 6) . " TON</b>\n\n";
                $response .= "🔄 You can watch another ad in " . AD_COOLDOWN . " seconds.";
                
                saveUsers($users);
            }
            
            sendMessage($chat_id, $response, getMainKeyboard());
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
