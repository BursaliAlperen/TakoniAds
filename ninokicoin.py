#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import json
import time
import random
import re
import requests
from datetime import datetime, timedelta
from threading import Lock
import cloudscraper  # pip install cloudscraper

# Renkler (Termux / terminal için ANSI)
class Colors:
    hitam = "\033[0;30m"
    merah = "\033[0;31m"
    hijau = "\033[0;32m"
    kuning = "\033[0;33m"
    biru = "\033[0;34m"
    cyan = "\033[0;36m"
    putih = "\033[0;37m"
    reset = "\033[0m"
    bg_hitam = "\033[40m"
    bg_merah = "\033[41m"
    bg_hijau = "\033[42m"
    bg_kuning = "\033[43m"
    bg_biru = "\033[44m"
    bg_ungu = "\033[45m"
    bg_cyan = "\033[46m"
    bg_putih = "\033[47m"

c = Colors()

# Sabitler
VERSION = "1.0"
SCRIPT_NAME = "99faucet.com"
HOST = "https://99faucet.com"
API_IN = "https://api.waryono.my.id/in.php"
CONFIG_FILE = "config.json"

# 18 coin (99faucet.com'da mevcut olanlardan örnek)
COINS = [
    "btc", "eth", "ltc", "doge", "dash", "bch",
    "zec", "xrp", "trx", "xlm", "etc", "neo",
    "ada", "matic", "sol", "avax", "ftm", "near"
]

# Küresel değişkenler
config = {}
claim_counts = {}  # coin -> bugünkü claim sayısı
last_reset_date = None
lock = Lock()

def clear():
    os.system('clear' if os.name == 'posix' else 'cls')

def banner_loading():
    clear()
    print(c.putih + "═" * 60)
    print(c.cyan + "         YÜKLENİYOR... LÜTFEN BEKLEYİN")
    print(c.putih + "═" * 60)
    print(c.kuning + "\n   ███╗   ██╗██╗███╗   ██╗ ██████╗ ██████╗ ██╗███╗   ██╗")
    print(c.kuning + "   ████╗  ██║██║████╗  ██║██╔═══██╗██╔══██╗██║████╗  ██║")
    print(c.kuning + "   ██╔██╗ ██║██║██╔██╗ ██║██║   ██║██████╔╝██║██╔██╗ ██║")
    print(c.kuning + "   ██║╚██╗██║██║██║╚██╗██║██║   ██║██╔══██╗██║██║╚██╗██║")
    print(c.kuning + "   ██║ ╚████║██║██║ ╚████║╚██████╔╝██║  ██║██║██║ ╚████║")
    print(c.kuning + "   ╚═╝  ╚═══╝╚═╝╚═╝  ╚═══╝ ╚═════╝ ╚═╝  ╚═╝╚═╝╚═╝  ╚═══╝")
    print(c.cyan + "\n         made by AlperenTHE")
    print(c.putih + "═" * 60)
    time.sleep(2)

def banner_main():
    clear()
    print(c.putih + "═" * 60)
    print(c.cyan + c.bg_putih + "          NINOCOIN          " + c.reset)
    print(c.putih + "═" * 60)
    print(c.kuning + "   ███╗   ██╗██╗███╗   ██╗ ██████╗ ██████╗ ██╗███╗   ██╗")
    print(c.kuning + "   ████╗  ██║██║████╗  ██║██╔═══██╗██╔══██╗██║████╗  ██║")
    print(c.kuning + "   ██╔██╗ ██║██║██╔██╗ ██║██║   ██║██████╔╝██║██╔██╗ ██║")
    print(c.kuning + "   ██║╚██╗██║██║██║╚██╗██║██║   ██║██╔══██╗██║██║╚██╗██║")
    print(c.kuning + "   ██║ ╚████║██║██║ ╚████║╚██████╔╝██║  ██║██║██║ ╚████║")
    print(c.kuning + "   ╚═╝  ╚═══╝╚═╝╚═╝  ╚═══╝ ╚═════╝ ╚═╝  ╚═╝╚═╝╚═╝  ╚═══╝")
    print(c.putih + "═" * 60)

def load_config():
    global config, claim_counts, last_reset_date
    if os.path.exists(CONFIG_FILE):
        with open(CONFIG_FILE, 'r') as f:
            config = json.load(f)
        # Eski formatı kontrol et
        if 'claim_counts' not in config:
            config['claim_counts'] = {}
        if 'last_reset_date' not in config:
            config['last_reset_date'] = None
        claim_counts = config['claim_counts']
        last_reset_date = config['last_reset_date']
        # Tarih kontrolü
        check_reset()
    else:
        config = {}
        claim_counts = {}
        last_reset_date = None
        save_config()

def save_config():
    config['claim_counts'] = claim_counts
    config['last_reset_date'] = last_reset_date
    with open(CONFIG_FILE, 'w') as f:
        json.dump(config, f, indent=2)

def check_reset():
    global last_reset_date, claim_counts
    today = datetime.now().strftime("%Y-%m-%d")
    if last_reset_date != today:
        # Sıfırlama
        claim_counts = {coin: 0 for coin in COINS}
        last_reset_date = today
        save_config()
        print(c.hijau + f"[!] Günlük sayaç sıfırlandı ({today})")

def get_headers(cookie, ua, referer=None):
    headers = {
        "Host": "99faucet.com",
        "User-Agent": ua,
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8",
        "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
        "Connection": "keep-alive",
        "Cookie": cookie,
    }
    if referer:
        headers["Referer"] = referer
    return headers

def fetch_page(url, cookie, ua, referer=None, method='GET', data=None):
    """Sayfa getir, Cloudflare kontrolü yap."""
    scraper = cloudscraper.create_scraper()
    headers = get_headers(cookie, ua, referer)
    try:
        if method.upper() == 'POST':
            resp = scraper.post(url, headers=headers, data=data, timeout=30)
        else:
            resp = scraper.get(url, headers=headers, timeout=30)
        if resp.status_code != 200:
            return None
        # Cloudflare kontrolü (Just a moment)
        if "Just a moment" in resp.text or "cf_clearance" in resp.text:
            return "cloudflare"
        return resp.text
    except Exception as e:
        print(c.merah + f"[!] HTTP Hatası: {e}")
        return None

def solve_captcha(apikey):
    """Slider captcha çözümü (PHP'deki slider fonksiyonu)."""
    headers = {"Content-Type": "application/json"}
    payload = {
        "apikey": apikey,
        "app_id": "1044",
        "methods": "rslider",
        "public_key": "ws1WNm5E0xjtnezLT8r9",
        "version": "v5",
        "referer": "https://99faucet.com/",
        "json": 1
    }
    try:
        resp = requests.post(API_IN, json=payload, headers=headers, timeout=30)
        data = resp.json()
        if 'request' not in data:
            print(c.merah + "[!] Captcha API hata: " + str(data))
            return None
        captcha_id = data['request']
        # Sonucu al
        for _ in range(10):  # max 10 deneme
            time.sleep(2)
            res_url = f"https://api.waryono.my.id/res.php?apikey={apikey}&id={captcha_id}&json=1"
            res = requests.get(res_url, timeout=30)
            res_data = res.json()
            if 'request' in res_data:
                result = res_data['request']
                # Parse rs_token ve rs_res
                match = re.search(r'rs_token:(\d+),rs_res:([^,]+)', result)
                if match:
                    return {"rs_token": match.group(1), "rs_res": match.group(2)}
            elif 'CAPCHA_NOT_READY' in res_data.get('error', ''):
                continue
            else:
                print(c.merah + "[!] Captcha çözüm hatası: " + str(res_data))
                return None
        return None
    except Exception as e:
        print(c.merah + f"[!] Captcha API hatası: {e}")
        return None

def claim_coin(coin, apikey, cookie, ua):
    """Bir coin için claim işlemi."""
    print(c.putih + f"\n[{coin.upper()}] Claim başlatılıyor...")

    # Faucet sayfasını al
    url = f"{HOST}/faucet/{coin}"
    page = fetch_page(url, cookie, ua, referer=f"{HOST}/dashboard")
    if page is None:
        print(c.merah + f"[{coin.upper()}] Sayfa alınamadı.")
        return False
    if page == "cloudflare":
        print(c.merah + f"[{coin.upper()}] Cloudflare tespit edildi, bypass gerekli (otomatik değil).")
        # Burada bypass işlemi yapılabilir, ancak basitçe atlıyoruz.
        return False

    # Token'ı al
    token_match = re.search(r'<input type="hidden" name="token" value="([^"]+)"', page)
    if not token_match:
        print(c.merah + f"[{coin.upper()}] Token bulunamadı.")
        return False
    token = token_match.group(1)

    # Shortlink kontrolü
    if "Shortlinks | 99Faucet" in page:
        print(c.kuning + f"[{coin.upper()}] Shortlink tamamlanması gerekiyor, atlanıyor.")
        return False

    # Captcha çöz
    captcha = solve_captcha(apikey)
    if not captcha:
        print(c.merah + f"[{coin.upper()}] Captcha çözülemedi.")
        return False

    # Claim POST
    data = {
        "ci_csrf_token": "",
        "token": token,
        "currency": coin,
        "captcha": "rscaptchav37",
        "rscaptcha_token": captcha["rs_token"],
        "rscaptcha_response": captcha["rs_res"],
        "uf": random_md5(),
        "utt": "Asia/Jakarta",
        "ls": "id,en-US,en,ms,ru"
    }
    # Kısa bekleme (captcha çözüm süresi zaten geçti)
    time.sleep(1)
    post_url = f"{HOST}/faucet/verify"
    referer = f"{HOST}/faucet/{coin}"
    resp = fetch_page(post_url, cookie, ua, referer=referer, method='POST', data=data)
    if resp is None:
        print(c.merah + f"[{coin.upper()}] Claim POST başarısız.")
        return False
    if "Good job!" in resp:
        # Claim başarılı
        # Mesajı ve bekleme süresini al
        msg_match = re.search(r"text: '([^']+)'", resp)
        if msg_match:
            msg = msg_match.group(1)
        else:
            msg = "Claim başarılı!"
        wait_match = re.search(r"let wait = (\d+)", resp)
        wait_time = int(wait_match.group(1)) if wait_match else 60
        print(c.hijau + f"[{coin.upper()}] {msg}")
        print(c.cyan + f"[{coin.upper()}] Bekleme: {wait_time} saniye")
        # Bekleme (cooldown)
        time.sleep(wait_time)
        return True
    elif "Invalid" in resp or "captcha" in resp.lower():
        print(c.merah + f"[{coin.upper()}] Geçersiz captcha veya hatalı claim.")
        return False
    elif "does not have sufficient funds" in resp:
        print(c.kuning + f"[{coin.upper()}] Yetersiz bakiye.")
        return False
    else:
        print(c.merah + f"[{coin.upper()}] Bilinmeyen hata.")
        return False

def random_md5():
    import hashlib
    return hashlib.md5(str(random.random()).encode()).hexdigest()

def main():
    global claim_counts

    # Loading ekranı
    banner_loading()

    # Ana ekran
    banner_main()

    # Config yükle
    load_config()

    # Kullanıcıdan bilgileri al
    if 'apikey' not in config:
        print(c.putih + "Captcha API Key: " + c.kuning, end='')
        apikey = input().strip()
        config['apikey'] = apikey
    else:
        apikey = config['apikey']
        print(c.hijau + f"[✓] API Key yüklendi: {apikey[:4]}...")

    if 'cookie' not in config:
        print(c.putih + "Cookie: " + c.kuning, end='')
        cookie = input().strip()
        config['cookie'] = cookie
    else:
        cookie = config['cookie']
        print(c.hijau + f"[✓] Cookie yüklendi: {cookie[:20]}...")

    # User-Agent
    if 'user_agent' not in config:
        ua = "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36"
        config['user_agent'] = ua
    else:
        ua = config['user_agent']

    save_config()

    print(c.putih + "\n" + "═" * 60)
    print(c.hijau + "[✓] Hazır, claim başlıyor...")
    print(c.putih + "═" * 60 + "\n")

    # Sonsuz döngü
    while True:
        # Günlük sıfırlama kontrolü
        check_reset()

        # Coinleri dolaş
        for coin in COINS:
            # Bugünkü claim sayısı
            current = claim_counts.get(coin, 0)
            if current >= 500:
                print(c.kuning + f"[{coin.upper()}] Günlük limit 500'e ulaşıldı, atlanıyor.")
                continue

            # Claim et
            success = claim_coin(coin, apikey, cookie, ua)
            if success:
                with lock:
                    claim_counts[coin] = claim_counts.get(coin, 0) + 1
                    save_config()
                print(c.hijau + f"[{coin.upper()}] Toplam claim: {claim_counts[coin]}/500")
            else:
                print(c.merah + f"[{coin.upper()}] Claim başarısız, diğer coine geçiliyor.")
                time.sleep(1)  # hata durumunda kısa bekleme

        # Tüm coinler denendi, kısa bekle
        print(c.putih + "\n[!] Tüm coinler tarandı, 60 saniye bekleniyor...")
        time.sleep(60)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print(c.merah + "\n[!] Kullanıcı tarafından durduruldu.")
        sys.exit(0)
    except Exception as e:
        print(c.merah + f"[!] Beklenmeyen hata: {e}")
        sys.exit(1)
