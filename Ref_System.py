import telebot
import os
from db import get_connection, init_db

BOT_TOKEN = os.environ.get("BOT_TOKEN")
bot = telebot.TeleBot(BOT_TOKEN)

# Kullanıcı kaydetme ve referans bonusu
def save_user(user, referrer_id=None):
    conn = get_connection()
    cur = conn.cursor()
    photo_url = None
    try:
        photos = bot.get_user_profile_photos(user.id, limit=1)
        if photos.total_count > 0:
            file_id = photos.photos[0][0].file_id
            file_info = bot.get_file(file_id)
            photo_url = f"https://api.telegram.org/file/bot{BOT_TOKEN}/{file_info.file_path}"
    except:
        pass

    cur.execute("""
        INSERT INTO users (telegram_id, username, fullname, photo_url, referrer_id)
        VALUES (%s, %s, %s, %s, %s)
        ON CONFLICT (telegram_id) DO NOTHING
    """, (user.id, user.username, f"{user.first_name} {user.last_name or ''}", photo_url, referrer_id))

    # Referans bonusu
    if referrer_id:
        cur.execute("UPDATE users SET balance = balance + 0.1 WHERE telegram_id = %s", (referrer_id,))

    conn.commit()
    cur.close()
    conn.close()

# /start komutu
@bot.message_handler(commands=['start'])
def start(message):
    args = message.text.split()
    referrer_id = int(args[1]) if len(args) > 1 and args[1].isdigit() else None
    save_user(message.from_user, referrer_id)
    bot.reply_to(message, "✅ Hesabın oluşturuldu!")
    if referrer_id:
        bot.send_message(referrer_id, f"🎉 Yeni bir kullanıcı senin referansınla katıldı: @{message.from_user.username}")

def run_bot():
    init_db()
    bot.polling(non_stop=True)
