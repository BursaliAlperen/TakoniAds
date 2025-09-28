# Telegram Earning Bot

PHP ile yazılmış Telegram botu - Render.com'da ücretsiz hosting için hazırlanmıştır.

## Özellikler

- 💰 Kullanıcılar puan kazanabilir
- 👥 Referans sistemi
- 🏆 Liderlik tablosu
- 💳 Bakiye görüntüleme
- 🏧 Para çekme işlemleri

## Kurulum

### 1. Render.com'da Deployment

1. Bu repository'yi fork edin veya kopyalayın
2. [Render.com](https://render.com) hesabı oluşturun
3. "New Web Service" seçin
4. GitHub repository'nizi bağlayın
5. Aşağıdaki environment variable'ı ekleyin:
   - `BOT_TOKEN`: Telegram bot token'ınız

### 2. Bot Token Alma

1. Telegram'da @BotFather'a mesaj atın
2. `/newbot` komutunu kullanın
3. Bot adını ve username'ini belirleyin
4. Size verilen token'ı kopyalayın

### 3. Environment Variables

Render.com dashboard'unda:
- `BOT_TOKEN`: your_telegram_bot_token_here

## Dosya Yapısı

- `index.php` - Ana bot kodu
- `users.json` - Kullanıcı veritabanı
- `error.log` - Hata kayıtları
- `Dockerfile` - Docker konfigürasyonu
- `.htaccess` - Apache kuralları
