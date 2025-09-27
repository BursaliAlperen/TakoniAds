import { useEffect, useState } from "react";
import { doc, getDoc } from "firebase/firestore";
import { db } from "./firebase";
import UserPanel from "./UserPanel";
import AdminPanel from "./AdminPanel";

export default function App() {
  const [user, setUser] = useState(null);
  const [isAdmin, setIsAdmin] = useState(false);

  useEffect(() => {
    const tg = window.Telegram.WebApp;
    tg.ready();
    const u = tg.initDataUnsafe.user;
    setUser(u);

    const checkAdmin = async () => {
      const adminRef = doc(db, "admins", String(u.id));
      const snap = await getDoc(adminRef);
      setIsAdmin(snap.exists());
    };

    checkAdmin();
  }, []);

  if (!user) return <p className="text-center mt-4">Yükleniyor...</p>;
  return isAdmin ? <AdminPanel user={user} /> : <UserPanel user={user} />;
}
