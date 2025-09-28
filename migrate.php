<?php
require 'index.php'; // Include the main script for DB functions

$users_json = @file_get_contents('/var/www/html/users.json');
if (!$users_json) {
    die("Error: users.json not found or empty\n");
}

$users = json_decode($users_json, true);
if (!$users) {
    die("Error: Invalid JSON in users.json\n");
}

$db = initDatabase();
$db->beginTransaction();

try {
    foreach ($users as $chat_id => $user) {
        // Save user to users table
        saveUser($chat_id, [
            'chat_id' => $chat_id,
            'balance' => $user['balance'] ?? 0,
            'referrals' => $user['referrals'] ?? 0,
            'ref_code' => $user['ref_code'] ?? generateRefCode($chat_id),
            'last_ad_watch' => $user['last_ad_watch'] ?? 0,
            'ads_watched_today' => $user['ads_watched_today'] ?? 0,
            'last_daily_reset' => $user['last_daily_reset'] ?? date('Y-m-d'),
            'ton_address' => $user['ton_address'] ?? '',
            'total_earned' => $user['total_earned'] ?? 0,
            'created_at' => $user['created_at'] ?? time(),
            'referred_by' => $user['referred_by'] ?? null,
            'username' => $user['username'] ?? 'Unknown',
            'awaiting_ton_address' => $user['awaiting_ton_address'] ?? 0,
            'ton_address_temp' => $user['ton_address_temp'] ?? null
        ]);

        // Save referral list
        foreach ($user['referral_list'] ?? [] as $ref) {
            $db->prepare("INSERT INTO referral_list (referrer_id, referred_id, username, joined_at, earned_from) VALUES (?, ?, ?, ?, ?)")
               ->execute([
                   $chat_id,
                   $ref['user_id'] ?? 0,
                   $ref['username'] ?? 'Unknown',
                   $ref['joined_at'] ?? time(),
                   $ref['earned_from'] ?? REF_REWARD
               ]);
        }
    }
    $db->commit();
    echo "Migration completed successfully\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
