<?php
// Enable all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fix file permissions on every request for Render
if (!file_exists('users.json')) {
    @file_put_contents('users.json', '{}');
    @chmod('users.json', 0666);
}
if (!file_exists('error.log')) {
    @file_put_contents('error.log', '');
    @chmod('error.log', 0666);
}

// Ensure files are writable
if (file_exists('users.json') && !is_writable('users.json')) {
    @chmod('users.json', 0666);
}
if (file_exists('error.log') && !is_writable('error.log')) {
    @chmod('error.log', 0666);
}

// Simple debug page
if (isset($_GET['debug'])) {
    echo "🤖 BOT SERVER STATUS\n\n";
    
    $files = ['users.json', 'error.log'];
    foreach ($files as $file) {
        if (!file_exists($file)) {
            file_put_contents($file, $file === 'users.json' ? '{}' : '');
            chmod($file, 0666);
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

// TON Rewards
define('AD_REWARD', 0.0001); // 0.0001 TON per ad
define('REF_REWARD', 0.0005); // 0.0005 TON per referral
define('MIN_WITHDRAW_REF', 5); // ✅ MİNİMUM 5 REFERANS ZORUNLU
define('MIN_WITHDRAW_AMOUNT', 0.01); // Minimum 0.01 TON to withdraw
define('AD_COOLDOWN', 10); // 10 seconds cooldown between ads

// Notification channel
define('NOTIFICATION_CHANNEL', '@TakoniFinance');

function logError($message) {
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
    $result = @file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    if ($result === false) {
        logError("SAVE USERS FAILED - File permissions issue");
        @chmod(USERS_FILE, 0666);
        $result = @file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
    }
    return $result !== false;
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

function sendChannelNotification($text) {
    try {
        $params = [
            'chat_id' => NOTIFICATION_CHANNEL,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
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
        logError("Channel notification failed: " . $e->getMessage());
        return false;
    }
}

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

function isChannelMember($chat_id) {
    return true;
}

function generateRefCode($chat_id) {
    return 'TAK' . substr(md5($chat_id), 0, 7);
}

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

function processUpdate($update) {
    logError("Received update: " . json_encode($update));
    
    $users = loadUsers();
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        
        logError("Message from {$chat_id}: {$text}");
        
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
                'created_at' => time(),
                'withdrawal_history' => [],
                'referred_by' => null,
                'referral_list' => [] // ✅ Yeni: Referans listesi eklendi
            ];
            saveUsers($users);
        }
        
        if (strpos($text, '/start') === 0) {
            $ref_code_param = explode(' ', $text)[1] ?? null;
            $user = $users[$chat_id];
            
            // REFERANS SİSTEMİ - GELİŞMİŞ VERSİYON
            if ($ref_code_param && $ref_code_param !== $user['ref_code'] && !isset($user['referred_by'])) {
                logError("Referral code detected: " . $ref_code_param);
                
                $referrer_found = false;
                $referrer_id = null;
                
                foreach ($users as $id => $u) {
                    if (isset($u['ref_code']) && $u['ref_code'] === $ref_code_param && $id != $chat_id) {
                        $referrer_found = true;
                        $referrer_id = $id;
                        break;
                    }
                }
                
                if ($referrer_found && $referrer_id) {
                    logError("Referrer found: " . $referrer_id);
                    
                    // Mevcut kullanıcıyı güncelle
                    $users[$chat_id]['referred_by'] = $referrer_id;
                    
                    // Referans vereni güncelle - GELİŞMİŞ VERSİYON
                    $users[$referrer_id]['referrals'] = ($users[$referrer_id]['referrals'] ?? 0) + 1;
                    $users[$referrer_id]['balance'] = ($users[$referrer_id]['balance'] ?? 0) + REF_REWARD;
                    $users[$referrer_id]['total_earned'] = ($users[$referrer_id]['total_earned'] ?? 0) + REF_REWARD;
                    
                    // ✅ Yeni: Referans listesine ekle
                    $users[$referrer_id]['referral_list'][] = [
                        'user_id' => $chat_id,
                        'username' => $message['chat']['username'] ?? 'Unknown',
                        'joined_at' => time(),
                        'earned_from' => REF_REWARD
                    ];
                    
                    if (saveUsers($users)) {
                        logError("Referral saved successfully - Referrer: {$referrer_id}, New User: {$chat_id}");
                        
                        // Referans vereni bilgilendir
                        $ref_message = "🎉 <b>New Referral!</b>\n\n";
                        $ref_message .= "👤 New user joined using your referral link!\n";
                        $ref_message .= "💰 You earned: <b>" . REF_REWARD . " TON</b>\n";
                        $ref_message .= "👥 Total referrals: <b>{$users[$referrer_id]['referrals']}</b>\n";
                        $ref_message .= "💳 New balance: <b>" . number_format($users[$referrer_id]['balance'], 6) . " TON</b>";
                        sendMessage($referrer_id, $ref_message);
                        
                        $welcome = "🎉 <b>Welcome via Referral!</b>\n\n";
                        $welcome .= "You joined using a referral link!\n\n";
                    } else {
                        logError("FAILED to save referral data");
                        $welcome = "🚀 <b>Welcome to TAKONI ADS!</b>\n\n";
                    }
                } else {
                    logError("Referrer not found for code: " . $ref_code_param);
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
            $welcome .= "• Per Referral: <b>" . REF_REWARD . " TON</b>\n\n";
            $welcome .= "⚠️ <b>Withdrawal Requirement:</b>\n";
            $welcome .= "• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> needed to withdraw";
            
            sendMessage($chat_id, $welcome, getMainKeyboard());
        }
        
        elseif (isset($users[$chat_id]['awaiting_ton_address'])) {
            $ton_address = trim($text);
            
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
                'created_at' => time(),
                'withdrawal_history' => [],
                'referred_by' => null,
                'referral_list' => []
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
                $ref_needed = max(0, MIN_WITHDRAW_REF - $referrals);
                
                $response = "💳 <b>Your TON Balance</b>\n\n";
                $response .= "💰 <b>Available Balance:</b> " . number_format($balance, 6) . " TON\n";
                $response .= "👥 <b>Total Referrals:</b> {$referrals}/" . MIN_WITHDRAW_REF . "\n";
                $response .= "🏆 <b>Total Earned:</b> " . number_format($total_earned, 6) . " TON\n\n";
                
                if ($referrals < MIN_WITHDRAW_REF) {
                    $response .= "❌ <b>Withdrawal Requirement:</b>\n";
                    $response .= "You need <b>{$ref_needed} more referrals</b> to withdraw\n\n";
                } else {
                    $response .= "✅ <b>Withdrawal Requirement:</b>\n";
                    $response .= "You have enough referrals to withdraw!\n\n";
                }
                
                $response .= "🔗 <b>Your TON Address:</b>\n";
                $response .= "<code>" . ($user['ton_address'] ?: 'Not set') . "</code>";
                
                editMessageText($chat_id, $message_id, $response, getBalanceKeyboard());
                break;
                
            case 'referrals':
                $ref_code = $user['ref_code'];
                $referrals = $user['referrals'] ?? 0;
                $ref_earnings = $referrals * REF_REWARD;
                $ref_needed = max(0, MIN_WITHDRAW_REF - $referrals);
                
                $response = "👥 <b>Referral System</b>\n\n";
                $response .= "🔗 <b>Your Referral Code:</b>\n";
                $response .= "<code>{$ref_code}</code>\n\n";
                $response .= "📊 <b>Your Referral Stats:</b>\n";
                $response .= "• Total Referrals: <b>{$referrals}/" . MIN_WITHDRAW_REF . "</b>\n";
                $response .= "• Referral Earnings: <b>" . number_format($ref_earnings, 6) . " TON</b>\n";
                $response .= "• Needed for withdrawal: <b>{$ref_needed} more</b>\n\n";
                
                // ✅ Referans listesini göster
                if (!empty($user['referral_list'])) {
                    $response .= "📋 <b>Your Referrals:</b>\n";
                    $count = 0;
                    foreach ($user['referral_list'] as $ref) {
                        $count++;
                        $username = $ref['username'] !== 'Unknown' ? "@{$ref['username']}" : "User{$ref['user_id']}";
                        $date = date('d.m.Y', $ref['joined_at']);
                        $response .= "{$count}. {$username} - {$date}\n";
                        if ($count >= 10) break; // İlk 10'u göster
                    }
                    if (count($user['referral_list']) > 10) {
                        $response .= "... and " . (count($user['referral_list']) - 10) . " more\n";
                    }
                    $response .= "\n";
                }
                
                $response .= "💰 <b>How it works:</b>\n";
                $response .= "• Share your referral link\n";
                $response .= "• Earn <b>" . REF_REWARD . " TON</b> per friend\n";
                $response .= "• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> required for withdrawal\n\n";
                $response .= "📱 <b>Your Referral Link:</b>\n";
                $response .= "https://t.me/" . explode(':', BOT_TOKEN)[0] . "?start={$ref_code}";
                
                editMessageText($chat_id, $message_id, $response, getReferralsKeyboard());
                break;
                
            case 'share_referral':
                $ref_code = $user['ref_code'];
                $ref_link = "https://t.me/" . explode(':', BOT_TOKEN)[0] . "?start={$ref_code}";
                
                $share_text = "🎉 Join TAKONI ADS and earn TON cryptocurrency!\n\n";
                $share_text .= "💰 Watch ads and earn " . AD_REWARD . " TON each\n";
                $share_text .= "👥 Use my referral link for bonus: {$ref_link}\n\n";
                $share_text .= "🚀 Start earning now!";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📤 Share via Telegram', 'url' => "https://t.me/share/url?url=" . urlencode($ref_link) . "&text=" . urlencode($share_text)]
                        ],
                        [
                            ['text' => '⬅️ Back to Referrals', 'callback_data' => 'referrals']
                        ]
                    ]
                ];
                
                editMessageText($chat_id, $message_id, "📤 <b>Share Referral Link</b>\n\nClick the button below to share:", $keyboard);
                break;
                
            case 'withdraw':
                $balance = $user['balance'] ?? 0;
                $ton_address = $user['ton_address'] ?? '';
                $referrals = $user['referrals'] ?? 0;
                
                $response = "🏧 <b>Withdraw TON</b>\n\n";
                $response .= "💰 <b>Available Balance:</b> " . number_format($balance, 6) . " TON\n";
                $response .= "👥 <b>Your Referrals:</b> {$referrals}/" . MIN_WITHDRAW_REF . "\n";
                $response .= "🔗 <b>TON Address:</b> " . ($ton_address ? "<code>{$ton_address}</code>" : "Not set") . "\n\n";
                
                // ✅ 3LÜ KONTROL SİSTEMİ
                $errors = [];
                
                if ($balance < MIN_WITHDRAW_AMOUNT) {
                    $errors[] = "❌ Minimum withdrawal: " . MIN_WITHDRAW_AMOUNT . " TON";
                }
                
                if ($referrals < MIN_WITHDRAW_REF) {
                    $needed = MIN_WITHDRAW_REF - $referrals;
                    $errors[] = "❌ Minimum " . MIN_WITHDRAW_REF . " referrals needed (missing: {$needed})";
                }
                
                if (!$ton_address) {
                    $errors[] = "❌ TON address not set";
                }
                
                if (empty($errors)) {
                    $response .= "✅ <b>Ready to withdraw!</b>\n";
                    $response .= "💡 Click 'Submit Withdrawal' to request your TON.";
                } else {
                    $response .= "🚫 <b>Withdrawal Requirements:</b>\n";
                    foreach ($errors as $error) {
                        $response .= "{$error}\n";
                    }
                }
                
                editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard());
                break;
                
            case 'enter_ton_address':
                $users[$chat_id]['awaiting_ton_address'] = true;
                saveUsers($users);
                
                $response = "🔗 <b>Enter TON Wallet Address</b>\n\n";
                $response .= "Please send your TON wallet address.\n";
                $response .= "📍 <b>Format:</b> EQ... or UQ...\n\n";
                $response .= "Example: <code>EQAbc123...xyz</code>\n\n";
                $response .= "📍 <b>Where to find your TON address?</b>\n";
                $response .= "• @wallet bot\n• Tonkeeper app\n• Trust Wallet";
                
                editMessageText($chat_id, $message_id, $response);
                break;
                
            case 'submit_withdrawal':
                $balance = $user['balance'] ?? 0;
                $ton_address = $user['ton_address'] ?? '';
                $referrals = $user['referrals'] ?? 0;
                
                // ✅ 3LÜ KONTROL
                if ($balance < MIN_WITHDRAW_AMOUNT) {
                    $response = "❌ <b>Insufficient Balance</b>\n\n";
                    $response .= "Minimum withdrawal: " . MIN_WITHDRAW_AMOUNT . " TON\n";
                    $response .= "Your balance: " . number_format($balance, 6) . " TON";
                    
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard());
                } 
                elseif ($referrals < MIN_WITHDRAW_REF) {
                    $needed = MIN_WITHDRAW_REF - $referrals;
                    $response = "❌ <b>Insufficient Referrals</b>\n\n";
                    $response .= "Minimum referrals: " . MIN_WITHDRAW_REF . "\n";
                    $response .= "Your referrals: {$referrals}\n";
                    $response .= "You need {$needed} more referrals";
                    
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard());
                }
                elseif (!$ton_address) {
                    $response = "❌ <b>TON Address Not Set</b>\n\n";
                    $response .= "Please set your TON address first.";
                    
                    editMessageText($chat_id, $message_id, $response, getWithdrawKeyboard());
                }
                else {
                    // Çekim işlemi
                    $users[$chat_id]['balance'] = 0;
                    $users[$chat_id]['pending_withdrawal'] = $balance;
                    $users[$chat_id]['withdrawal_history'][] = [
                        'amount' => $balance,
                        'address' => $ton_address,
                        'timestamp' => time(),
                        'status' => 'pending'
                    ];
                    
                    saveUsers($users);
                    
                    $response = "✅ <b>Withdrawal Request Submitted!</b>\n\n";
                    $response .= "💰 <b>Amount:</b> " . number_format($balance, 6) . " TON\n";
                    $response .= "👥 <b>Referrals:</b> {$referrals}/" . MIN_WITHDRAW_REF . " ✅\n";
                    $response .= "🔗 <b>Address:</b> <code>{$ton_address}</code>\n\n";
                    $response .= "⏰ <b>Processing time:</b> 24-48 hours\n";
                    $response .= "📢 You will be notified when sent.";
                    
                    // Admin bildirimi
                    $admin_msg = "🔄 <b>New Withdrawal Request</b>\n\n";
                    $admin_msg .= "👤 User: <code>{$chat_id}</code>\n";
                    $admin_msg .= "💰 Amount: " . number_format($balance, 6) . " TON\n";
                    $admin_msg .= "👥 Referrals: {$referrals}\n";
                    $admin_msg .= "🔗 Address: <code>{$ton_address}</code>";
                    sendChannelNotification($admin_msg);
                    
                    editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                }
                break;
                
            case 'main_menu':
                $response = "🚀 <b>TAKONI ADS</b>\n\n";
                $response .= "💰 Earn TON by watching ads\n";
                $response .= "👥 Invite friends for bonuses\n";
                $response .= "🏧 Withdraw to your wallet\n\n";
                $response .= "💎 <b>Features:</b>\n";
                $response .= "• Watch ads: " . AD_REWARD . " TON each\n";
                $response .= "• Refer friends: " . REF_REWARD . " TON each\n";
                $response .= "• Instant withdrawals\n";
                $response .= "• No channel requirements!\n\n";
                $response .= "⚠️ <b>Withdrawal Requirement:</b>\n";
                $response .= "• Minimum <b>" . MIN_WITHDRAW_REF . " referrals</b> needed";
                
                editMessageText($chat_id, $message_id, $response, getMainKeyboard());
                break;
        }
    }
    
    if (isset($update['web_app_data'])) {
        $web_app_data = $update['web_app_data'];
        $chat_id = $web_app_data['user_id'];
        $data = json_decode($web_app_data['data'], true);
        
        logError("Web app data from {$chat_id}: " . $web_app_data['data']);
        
        $users = loadUsers();
        if (!isset($users[$chat_id])) {
            return;
        }
        
        $user = $users[$chat_id];
        $current_time = time();
        
        if ($current_time - $user['last_ad_watch'] < AD_COOLDOWN) {
            $remaining = AD_COOLDOWN - ($current_time - $user['last_ad_watch']);
            sendMessage($chat_id, "⏰ Please wait {$remaining} seconds before watching another ad.");
            return;
        }
        
        $users[$chat_id]['balance'] += AD_REWARD;
        $users[$chat_id]['total_earned'] += AD_REWARD;
        $users[$chat_id]['last_ad_watch'] = $current_time;
        
        saveUsers($users);
        
        $response = "🎉 <b>Ad Watched Successfully!</b>\n\n";
        $response .= "💰 You earned: <b>" . AD_REWARD . " TON</b>\n";
        $response .= "💳 New balance: <b>" . number_format($users[$chat_id]['balance'], 6) . " TON</b>\n\n";
        $response .= "🔄 Ready for next ad in " . AD_COOLDOWN . " seconds";
        
        sendMessage($chat_id, $response, getEarnKeyboard());
    }
}

// Get update from Telegram
$input = file_get_contents('php://input');
if ($input) {
    $update = json_decode($input, true);
    if ($update) {
        processUpdate($update);
    }
}

echo "🤖 TAKONI ADS BOT IS RUNNING | " . date('Y-m-d H:i:s');
?>
