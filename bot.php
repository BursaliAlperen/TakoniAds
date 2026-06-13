<?php
error_reporting(0);
date_default_timezone_set('Asia/Jakarta');

$configFile = "config.json";

// AAA Renk Paleti (Pembe, Mor, Gri, Yeşil, Kırmızı)
const pink   = "\033[38;5;213m";
const purple = "\033[38;5;129m";
const gray   = "\033[38;5;244m";
const green  = "\033[38;5;46m";
const red    = "\033[38;5;196m";
const reset  = "\033[0m";
const bold   = "\033[1m";

const version     = "2.0";
const script_name = "NINOCOIN";
const host        = "https://99faucet.com";
const api_in      = "https://api.waryono.my.id/in.php";

function clear() {
    (PHP_OS == "Linux" || PHP_OS == "Darwin") ? system('clear') : pclose(popen('cls', 'w'));
}

function uf() {
    return md5(uniqid(mt_rand(), true));
}

function zone() {
    return date_default_timezone_get();
}

function banner() {
    echo purple . bold . "  _   _ ______ _____  _____  _   _ " . reset . "\n";
    echo purple . bold . " | \ | || ___ \_   _|/  __ \| | | |" . reset . "\n";
    echo pink  . bold . " |  \| || |_/ / | |  | /  \/| |_| |" . reset . "\n";
    echo pink  . bold . " | . ` || ___ \ | |  | |    |  _  |" . reset . "\n";
    echo purple. bold . " | |\  || |_/ / | |  | \__/\| | | |" . reset . "\n";
    echo pink  . bold . " \_| \_/\____/  \_/   \____/\_| |_/" . reset . "\n";
    echo gray  . "          [ AAA Multi-Coin Claimer ]         " . reset . "\n";
    echo gray  . "-----------------------------------------------------" . reset . "\n";
}

function fast_timer($seconds, $prefix = "[*] Waiting") {
    $wait_time = (int)$seconds;
    while ($wait_time > 0) {
        $hours = floor($wait_time / 3600);
        $minutes = floor(($wait_time % 3600) / 60);
        $secs = $wait_time % 60;
        $time_formatted = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);        echo gray . $prefix . " " . pink . $time_formatted . gray . "...\r" . reset;
        sleep(1);
        $wait_time--;
    }
    echo str_repeat(" ", 60) . "\r";
}

function skibidixxx($url, $method = 'GET', $data = [], $headers = []) {
    $ch = curl_init();
    $final_headers = [];
    foreach ($headers as $header) {
        $final_headers[] = $header;
    }
    $options = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => $final_headers,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 15
    ];
    if (strtoupper($method) === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $data;
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode >= 200 && $httpCode < 400) {
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        return substr($response, $header_size);
    }
    return "ngelek";
}

function slider($app_id, $public_key, $version, $reff, $apikey) {
    $headers = ["Content-Type: application/json"];
    $body = json_encode([
        "apikey"     => $apikey,
        "app_id"     => $app_id,
        "methods"    => "rslider",
        "public_key" => $public_key,
        "version"    => $version,
        "referer"    => $reff,
        "json"       => 1    ]);
    
    $request = skibidixxx(api_in, "POST", $body, $headers);
    if (strpos($request, "ERROR") !== false || strpos($request, "error") !== false) {
        return false;
    }
    
    $json = json_decode($request, true);
    if (!isset($json["request"])) return false;
    $id = $json["request"];
    
    $max_retries = 25; // Hızlı claim için 25 * 2s = 50s max bekleme
    for ($i = 0; $i < $max_retries; $i++) {
        echo gray . "[*] Solving Captcha... (" . ($i + 1) . "/$max_retries)\r" . reset;
        sleep(2);
        $url = "https://api.waryono.my.id/res.php?apikey=" . $apikey . "&id=" . $id . "&json=1";
        $result = skibidixxx($url, "GET", []);
        
        if (strpos($result, "CAPCHA_NOT_READY") !== false || strpos($result, "ERROR_SOLVE_PENDING") !== false) {
            continue;
        }
        if (strpos($result, "ERROR") !== false) {
            return false;
        }
        
        $json_res = json_decode($result, true);
        if (isset($json_res["request"])) {
            $res = $json_res["request"];
            if (preg_match('/rs_token:(\d+),rs_res:([^,]+)/', $res, $match)) {
                echo green . "[+] Captcha Solved Successfully!            \n" . reset;
                return ["rs_token" => $match[1], "rs_res" => $match[2]];
            }
        }
    }
    echo red . "\n[-] Captcha Timeout!                        \n" . reset;
    return false;
}

function bypassCloudflare(&$config, $configFile, $target) {
    echo yellow . "[!] Cloudflare Detected. Bypassing..." . reset . "\n";
    $python_cmd = "python exec.py " . escapeshellarg($target) . " 2>/dev/null";
    $output = exec($python_cmd);
    $data_bypass = json_decode($output, true);
    
    if (isset($data_bypass['cf_clearance']) && !empty($data_bypass['cf_clearance'])) {
        $full_new_cf = $data_bypass['cf_clearance'];
        $new_ua = $data_bypass['user_agent'];
        $old_cookie = $config['cookie'] ?? '';
        
        $new_token_value = strpos($full_new_cf, '=') !== false ? explode('=', $full_new_cf)[1] : $full_new_cf;        $pattern = '/cf_clearance=[^;]+/';
        $replacement = "cf_clearance=" . $new_token_value;
        
        if (preg_match($pattern, $old_cookie)) {
            $new_cookie_str = preg_replace($pattern, $replacement, $old_cookie);
        } else {
            $new_cookie_str = rtrim($old_cookie, "; ") . (empty($old_cookie) ? "" : "; ") . $replacement;
        }
        
        $config['cookie'] = $new_cookie_str;
        $config['user_agent'] = $new_ua;
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        
        echo green . "[+] Cloudflare Bypassed Successfully!" . reset . "\n";
        sleep(1);
        return true;
    }
    echo red . "[-] Cloudflare Bypass Failed!" . reset . "\n";
    return false;
}

function getConfig($configFile) {
    if (!file_exists($configFile)) {
        echo purple . "API Key: " . pink;
        $apikey = trim(fgets(STDIN));
        echo purple . "Cookie: " . pink;
        $coki = trim(fgets(STDIN));
        $data = ["apikey" => $apikey, "cookie" => $coki];
        file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT));
        echo green . "[+] Saved to $configFile\n" . reset;
        sleep(2);
        return $data;
    }
    return json_decode(file_get_contents($configFile), true);
}

// --- ANA DÖNGÜ ---
login:
clear();
banner();
$config = getConfig($configFile);
$apikey = $config['apikey'] ?? '';
$coki   = $config['cookie'] ?? '';
$ua     = $config['user_agent'] ?? "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36";

if (empty($apikey) || empty($coki)) {
    echo red . "[!] Invalid config. Deleting and restarting..." . reset . "\n";
    @unlink($configFile);
    sleep(2);
    goto login;}

dash:
clear();
banner();
echo gray . "[*] Fetching dashboard..." . reset . "\n";

$headers = [
    "host: 99faucet.com",
    "user-agent: " . $ua,
    "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
    "referer: " . host . "/faucet/pepe",
    "cookie: " . $coki
];

$dash = skibidixxx(host . "/dashboard", "GET", [], $headers);

if ($dash == "ngelek" || strpos($dash, "Just a moment") !== false) {
    if (!bypassCloudflare($config, $configFile, host . "/dashboard")) {
        echo red . "[!] Critical Bypass Failed. Check connection." . reset . "\n";
        sleep(5);
        goto dash;
    }
    $coki = $config['cookie'];
    $ua   = $config['user_agent'];
    $headers = ["host: 99faucet.com", "user-agent: " . $ua, "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8", "referer: " . host . "/faucet/pepe", "cookie: " . $coki];
    $dash = skibidixxx(host . "/dashboard", "GET", [], $headers);
}

if (strpos($dash, "Dashboard | 99Faucet") !== false) {
    preg_match_all('/<a href="https:\/\/99faucet\.com\/faucet\/([^"]+)" class="">/', $dash, $matches);
    $currencies = array_unique($matches[1]);
    
    if (empty($currencies)) {
        echo red . "[!] No currencies found. Cookie might be expired." . reset . "\n";
        sleep(3);
        @unlink($configFile);
        goto login;
    }
    
    echo green . "[+] Found " . count($currencies) . " currencies!" . reset . "\n";
    echo gray . "-----------------------------------------------------" . reset . "\n";
    
    // MULTI-COIN DÖNGÜSÜ
    foreach ($currencies as $index => $currency) {
        $memek = strtolower($currency);
        echo purple . bold . "\n  >> CLAIMING: " . pink . strtoupper($memek) . purple . " (" . ($index + 1) . "/" . count($currencies) . ")" . reset . "\n";
        echo gray . "-----------------------------------------------------" . reset . "\n";
        
        $faucet_headers = [            "host: 99faucet.com",
            "user-agent: " . $ua,
            "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
            "referer: " . host . "/dashboard",
            "cookie: " . $coki
        ];
        
        $url = host . "/faucet/" . $memek;
        $faucet = skibidixxx($url, "GET", [], $faucet_headers);
        
        if ($faucet == "ngelek" || strpos($faucet, "Just a moment") !== false) {
            if (bypassCloudflare($config, $configFile, $url)) {
                $coki = $config['cookie'];
                $ua   = $config['user_agent'];
                $faucet_headers = ["host: 99faucet.com", "user-agent: " . $ua, "accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8", "referer: " . host . "/dashboard", "cookie: " . $coki];
                $faucet = skibidixxx($url, "GET", [], $faucet_headers);
            } else {
                echo red . "[-] Bypass Failed. Skipping " . strtoupper($memek) . reset . "\n";
                continue;
            }
        }
        
        if (strpos($faucet, "Shortlinks | 99Faucet") !== false) {
            echo yellow . "[!] Shortlink required. Skipping " . strtoupper($memek) . reset . "\n";
            continue;
        }
        
        $token = explode('"', explode('<input type="hidden" name="token" value="', $faucet)[1])[0] ?? '';
        if (empty($token)) {
            echo red . "[-] Token not found. Skipping " . strtoupper($memek) . reset . "\n";
            continue;
        }
        
        $bypass = slider("1044", "ws1WNm5E0xjtnezLT8r9", "v5", "https://99faucet.com/", $apikey);
        
        if (is_array($bypass)) {
            $post_headers = [
                "host: 99faucet.com",
                "origin: " . host,
                "content-type: application/x-www-form-urlencoded",
                "user-agent: " . $ua,
                "accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
                "referer: " . host . "/faucet/" . $memek,
                "cookie: " . $coki
            ];
            
            $data = http_build_query([
                "ci_csrf_token" => "",
                "token" => $token,
                "currency" => $memek,                "captcha" => "rscaptchav37",
                "rscaptcha_token" => $bypass["rs_token"],
                "rscaptcha_response" => $bypass["rs_res"],
                "uf" => uf(),
                "utt" => zone(),
                "ls" => "id,en-US,en,ms,ru"
            ]);
            
            $claim = skibidixxx(host . "/faucet/verify", "POST", $data, $post_headers);
            
            if (strpos($claim, "Good job!") !== false) {
                $msg = explode("'", explode("text: '", $claim)[1])[0] ?? "Success";
                $timer_val = (int)(explode(' -', explode('let wait = ', $claim)[1])[0] ?? 60);
                echo green . "[+] Claim Successful: " . $msg . reset . "\n";
                if ($timer_val > 0) {
                    fast_timer($timer_val, "[*] Next claim in");
                }
            } elseif (strpos($claim, "Invalid") !== false) {
                echo red . "[-] Invalid captcha or claim." . reset . "\n";
            } elseif (strpos($claim, "sufficient funds") !== false) {
                echo yellow . "[!] Faucet empty for " . strtoupper($memek) . reset . "\n";
            } else {
                echo red . "[-] Unknown error." . reset . "\n";
            }
        } else {
            echo red . "[-] Captcha solving failed." . reset . "\n";
        }
    }
    
    echo green . bold . "\n[+] All coins processed! Restarting loop in 10 seconds..." . reset . "\n";
    fast_timer(10, "[*] Restarting in");
    goto dash;
    
} else {
    echo red . "[!] Login failed or session expired. Re-login..." . reset . "\n";
    @unlink($configFile);
    sleep(3);
    goto login;
}
?>
