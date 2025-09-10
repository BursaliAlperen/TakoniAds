from flask import Flask, render_template, request, jsonify
from dotenv import load_dotenv
import os
from ref_system import save_user

load_dotenv()
app = Flask(__name__)

@app.route("/")
def index():
    return render_template("index.html")

@app.route("/save_user", methods=["POST"])
def api_save_user():
    data = request.json
    telegram_id = data.get("telegram_id")
    username = data.get("username")
    fullname = data.get("fullname")
    referrer_id = data.get("referrer_id")

    save_user(
        {
            "id": telegram_id,
            "username": username,
            "first_name": fullname.split()[0],
            "last_name": fullname.split()[1] if len(fullname.split()) > 1 else None
        },
        referrer_id
    )
    return jsonify({"status": "ok"})

if __name__ == "__main__":
    from ref_system import run_bot
    import threading
    threading.Thread(target=run_bot).start()  # Bot ayrı thread
    app.run(host="0.0.0.0", port=5000)
