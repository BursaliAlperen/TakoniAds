#!/bin/bash

# Dosyaları oluştur
touch /var/www/html/bot.db
touch /var/www/html/error.log

# İzinleri ayarla
chmod 664 /var/www/html/bot.db /var/www/html/error.log

# Sahipliği ayarla (web sunucusu kullanıcısına göre, örneğin www-data)
chown -R www-data:www-data /var/www/html

# Dizin izinlerini ayarla
chmod -R 775 /var/www/html

echo "Kurulum tamamlandı!"
