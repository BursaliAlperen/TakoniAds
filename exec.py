#!/usr/bin/env python3
"""
NINOCOIN - Cloudflare Bypass Module
Author: NINOCOIN Team
Version: 2.0
"""

import sys
import json
import time
import os
import warnings

warnings.filterwarnings('ignore')
os.environ["PYTHONWARNINGS"] = "ignore"


def log(msg):
    """Log mesajını stderr'e yaz"""
    print(f"[NINOCOIN] {msg}", file=sys.stderr)


def bypass_with_curl_cffi(url):
    """curl_cffi ile Cloudflare bypass (en güçlü yöntem)"""
    try:
        from curl_cffi import requests as cf_requests
        
        session = cf_requests.Session(impersonate="chrome124")
        
        # Warm-up request
        session.get("https://99faucet.com", timeout=15)
        time.sleep(2)
        
        # Hedef sayfa
        response = session.get(url, timeout=20)
        cookies = session.cookies.get_dict()
        cf_clearance = cookies.get('cf_clearance', '')
        uagent = session.headers.get('User-Agent', 
            'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 '
            '(KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36')
        
        if cf_clearance:
            return {
                "cf_clearance": f"cf_clearance={cf_clearance}",
                "user_agent": uagent
            }
    except ImportError:
        log("curl_cffi yüklü değil")
    except Exception as e:
        log(f"curl_cffi hatası: {str(e)}")    
    return None


def bypass_with_cloudscraper(url):
    """cloudscraper ile Cloudflare bypass"""
    try:
        import cloudscraper
        
        scraper = cloudscraper.create_scraper(
            browser={'browser': 'chrome', 'platform': 'android', 'desktop': False}
        )
        
        scraper.get("https://99faucet.com", timeout=15)
        time.sleep(2)
        response = scraper.get(url, timeout=20)
        
        cookies = scraper.cookies.get_dict()
        cf_clearance = cookies.get('cf_clearance', '')
        
        if cf_clearance:
            return {
                "cf_clearance": f"cf_clearance={cf_clearance}",
                "user_agent": 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 '
                             '(KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36'
            }
    except ImportError:
        log("cloudscraper yüklü değil")
    except Exception as e:
        log(f"cloudscraper hatası: {str(e)}")
    
    return None


def bypass_with_requests(url):
    """requests ile Cloudflare bypass"""
    try:
        import requests
        
        headers = {
            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 '
                         '(KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.9',
            'Connection': 'keep-alive',
        }
        
        session = requests.Session()
        session.get("https://99faucet.com", headers=headers, timeout=15)
        time.sleep(3)
        response = session.get(url, headers=headers, timeout=20, allow_redirects=True)
        
        cookies = session.cookies.get_dict()
        cf_clearance = cookies.get('cf_clearance', '')
        
        if cf_clearance:
            return {
                "cf_clearance": f"cf_clearance={cf_clearance}",
                "user_agent": headers['User-Agent']
            }
    except ImportError:
        log("requests yüklü değil")
    except Exception as e:
        log(f"requests hatası: {str(e)}")
    
    return None


def bypass_with_httpx(url):
    """httpx ile Cloudflare bypass"""
    try:
        import httpx
        
        headers = {
            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 '
                         '(KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        }
        
        with httpx.Client(headers=headers, follow_redirects=True, timeout=20) as client:
            client.get("https://99faucet.com")
            time.sleep(2)
            response = client.get(url)
            
            cookies = dict(client.cookies)
            cf_clearance = cookies.get('cf_clearance', '')
            
            if cf_clearance:
                return {
                    "cf_clearance": f"cf_clearance={cf_clearance}",
                    "user_agent": headers['User-Agent']
                }
    except ImportError:
        log("httpx yüklü değil")
    except Exception as e:
        log(f"httpx hatası: {str(e)}")
    
    return None

def main():
    """Ana fonksiyon"""
    if len(sys.argv) < 2:
        log("Kullanım: python exec.py <url>")
        print(json.dumps({"cf_clearance": "", "user_agent": ""}))
        sys.exit(1)
    
    target_url = sys.argv[1]
    result = None
    
    # Deneme sırası: en güçlüden zayıfa
    log("curl_cffi deneniyor...")
    result = bypass_with_curl_cffi(target_url)
    if result:
        log("curl_cffi başarılı!")
        print(json.dumps(result))
        sys.exit(0)
    
    log("cloudscraper deneniyor...")
    result = bypass_with_cloudscraper(target_url)
    if result:
        log("cloudscraper başarılı!")
        print(json.dumps(result))
        sys.exit(0)
    
    log("requests deneniyor...")
    result = bypass_with_requests(target_url)
    if result:
        log("requests başarılı!")
        print(json.dumps(result))
        sys.exit(0)
    
    log("httpx deneniyor...")
    result = bypass_with_httpx(target_url)
    if result:
        log("httpx başarılı!")
        print(json.dumps(result))
        sys.exit(0)
    
    # Tüm yöntemler başarısız
    log("Tüm bypass yöntemleri başarısız!")
    print(json.dumps({
        "cf_clearance": "",
        "user_agent": "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 "
                     "(KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36"
    }))


if __name__ == "__main__":
    main()
