<?php
// Bot configuration
$bot_token = getenv('BOT_TOKEN') ?: 'Place_Your_Token_Here';
define('BOT_TOKEN', $bot_token);
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');
define('USERS_FILE', 'users.json');
define('ERROR_LOG', 'error.log');

// Ensure data files exist and have proper permissions
if (!file_exists(USERS_FILE)) {
    file_put_contents(USERS_FILE, json_encode([]));
    chmod(USERS_FILE, 0666);
}
if (!file_exists(ERROR_LOG)) {
    file_put_contents(ERROR_LOG, '');
    chmod(ERROR_LOG, 0666);
}

// Initialize bot (clear webhook)
function initializeBot() {
    try {
        file_get_contents(API_URL . 'setWebhook?url=');
        return true;
    } catch (Exception $e) {
        logError("Initialization failed: " . $e->getMessage());
        return false;
    }
}

// Error logging
function logError($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(ERROR_LOG, "[$timestamp] $message\n", FILE_APPEND);
}

// Data management
function loadUsers() {
    try {
        if (!file_exists(USERS_FILE)) {
            file_put_contents(USERS_FILE, json_encode([]));
            chmod(USERS_FILE, 0666);
        }
        $data = file_get_contents(USERS_FILE);
        return json_decode($data, true) ?: [];
    } catch (Exception $e) {
        logError("Load users failed: " . $e->getMessage());
        return [];
    }
}

function saveUsers($users) {
    try {
        file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
        return true;
    } catch (Exception $e) {
        logError("Save users failed: " . $e->getMessage());
        return false;
    }
}

// Message sending with inline keyboard
function sendMessage($chat_id, $text, $keyboard = null) {
    try {
        $params = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if ($keyboard) {  
            $params['reply_markup'] = json_encode([  
                'inline_keyboard' => $keyboard  
            ]);  
        }  
          
        $url = API_URL . 'sendMessage?' . http_build_query($params);  
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
        file_get_contents($url, false, $context);  
        return true;  
    } catch (Exception $e) {  
        logError("Send message failed: " . $e->getMessage());  
        return false;  
    }
}

// Main keyboard
function getMainKeyboard() {
    return [
        [['text' => '💰 Earn', 'callback_data' => 'earn'], ['text' => '💳 Balance', 'callback_data' => 'balance']],
        [['text' => '🏆 Leaderboard', 'callback_data' => 'leaderboard'], ['text' => '👥 Referrals', 'callback_data' => 'referrals']],
        [['text' => '🏧 Withdraw', 'callback_data' => 'withdraw'], ['text' => '❓ Help', 'callback_data' => 'help']]
    ];
}

// Process commands and callbacks
function processUpdate($update) {
    $users = loadUsers();

    if (isset($update['message'])) {  
        $chat_id = $update['message']['chat']['id'];  
        $text = trim($update['message']['text'] ?? '');  
          
        // Create new user if doesn't exist  
        if (!isset($users[$chat_id])) {  
            $users[$chat_id] = [  
                'balance' => 0,  
                'last_earn' => 0,  
                'referrals' => 0,  
                'ref_code' => substr(md5($chat_id . time()), 0, 8),  
                'referred_by' => null  
            ];  
        }  
          
        if (strpos($text, '/start') === 0) {  
            $ref = explode(' ', $text)[1] ?? null;  
            if ($ref && !$users[$chat_id]['referred_by']) {  
                foreach ($users as $id => $user) {  
                    if ($user['ref_code'] === $ref && $id != $chat_id) {  
                        $users[$chat_id]['referred_by'] = $id;  
                        $users[$id]['referrals']++;  
                        $users[$id]['balance'] += 50; // Referral bonus  
                        sendMessage($id, "🎉 New referral! +50 points bonus!");  
                        break;  
                    }  
                }  
            }  
              
            $msg = "Welcome to Earning Bot!\nEarn points, invite friends, and withdraw your earnings!\nYour referral code: <b>{$users[$chat_id]['ref_code']}</b>";  
            sendMessage($chat_id, $msg, getMainKeyboard());  
        }  
          
    } elseif (isset($update['callback_query'])) {  
        $chat_id = $update['callback_query']['message']['chat']['id'];  
        $data = $update['callback_query']['data'];  
          
        if (!isset($users[$chat_id])) {  
            $users[$chat_id] = [  
                'balance' => 0,  
                'last_earn' => 0,  
                'referrals' => 0,  
                'ref_code' => substr(md5($chat_id . time()), 0, 8),  
                'referred_by' => null  
            ];  
        }

        switch ($data) {  
            case 'earn':  
                $time_diff = time() - $users[$chat_id]['last_earn'];  
                if ($time_diff < 60) {  
                    $remaining = 60 - $time_diff;  
                    $msg = "⏳ Please wait $remaining seconds before earning again!";  
                } else {  
                    $earn = 10;  
                    $users[$chat_id]['balance'] += $earn;  
                    $users[$chat_id]['last_earn'] = time();  
                    $msg = "✅ You earned $earn points!\nNew balance: {$users[$chat_id]['balance']}";  
                }  
                break;  
                  
            case 'balance':  
                $msg = "💳 Your Balance\nPoints: {$users[$chat_id]['balance']}\nReferrals: {$users[$chat_id]['referrals']}";  
                break;  
                  
            case 'leaderboard':  
                $sorted = array_column($users, 'balance');  
                arsort($sorted);  
                $top = array_slice($sorted, 0, 5, true);  
                $msg = "🏆 Top Earners\n";  
                $i = 1;  
                foreach ($top as $id => $bal) {  
                    $msg .= "$i. User $id: $bal points\n";  
                    $i++;  
                }  
                break;  
                  
            case 'referrals':  
                $msg = "👥 Referral System\nYour code: <b>{$users[$chat_id]['ref_code']}</b>\nReferrals: {$users[$chat_id]['referrals']}\nInvite link: https://t.me/" . explode(':', BOT_TOKEN)[0] . "?start={$users[$chat_id]['ref_code']}\n50 points per referral!";  
                break;  
                  
            case 'withdraw':  
                $min = 100;  
                if ($users[$chat_id]['balance'] < $min) {  
                    $msg = "🏧 Withdrawal\nMinimum: $min points\nYour balance: {$users[$chat_id]['balance']}\nNeed " . ($min - $users[$chat_id]['balance']) . " more points!";  
                } else {  
                    $amount = $users[$chat_id]['balance'];  
                    $users[$chat_id]['balance'] = 0;  
                    $msg = "🏧 Withdrawal of $amount points requested!\nOur team will process it soon.";  
                    // Add actual withdrawal processing here  
                }  
                break;  
                  
            case 'help':  
                $msg = "❓ Help\n💰 Earn: Get 10 points/min\n👥 Refer: 50 points/ref\n🏧 Withdraw: Min 100 points\nUse buttons below to navigate!";  
                break;  
        }  
          
        sendMessage($chat_id, $msg, getMainKeyboard());  
    }  
      
    saveUsers($users);
}

// Main polling loop
function runBot() {
    $offset = 0;
    initializeBot();
    echo "Bot started. Press Ctrl+C to stop.\n";

    while (true) {  
        try {  
            $context = stream_context_create([
                'http' => [
                    'timeout' => 60,
                    'ignore_errors' => true
                ]
            ]);
            $updates = file_get_contents(API_URL . "getUpdates?offset=$offset&timeout=30", false, $context);  
            $updates = json_decode($updates, true);  
              
            if ($updates['ok'] && !empty($updates['result'])) {  
                foreach ($updates['result'] as $update) {  
                    $offset = $update['update_id'] + 1;  
                    processUpdate($update);  
                }  
            }  
              
            usleep(100000);  
              
        } catch (Exception $e) {  
            logError("Polling error: " . $e->getMessage());  
            sleep(1);  
        }  
    }
}

// Webhook handler for Render
function handleWebhook() {
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);
    
    if ($update) {
        processUpdate($update);
        http_response_code(200);
        echo 'OK';
    } else {
        http_response_code(400);
        echo 'Bad Request';
    }
}

// Check if running via webhook or CLI
if (php_sapi_name() === 'cli') {
    // Start bot in polling mode
    try {
        runBot();
    } catch (Exception $e) {
        logError("Fatal error: " . $e->getMessage());
        echo "Bot crashed. Check error.log.\n";
    }
} else {
    // Handle webhook request
    handleWebhook();
}
?>
