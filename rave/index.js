// index.js (Doğru ve Tam Hali)
const express = require('express');
const mysql = require('mysql2'); // <<<---- BAK, BU SATIR ÖNEMLİ İŞTE!
const cors = require('cors');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');

const app = express();
app.use(cors());
app.use(express.json());

// DİKKAT: Buradaki bilgileri VDS'in veritabanı bilgileriyle doldurcan!
const db = mysql.createConnection({
  host: '38.3.137.2', // VDS'nin IP adresi
  user: 'root', // VDS'deki MySQL kullanıcı adın
  password: '', // VDS'deki MySQL şifren
  database: 'effronte13_pck' 
});

db.connect(err => {
  if (err) {
    console.error('HATA: Veritabanına bağlanamadık emmi oğlu! => ', err);
    return;
  }
  console.log('MySQL veritabanına jet gibi bağlandık, sıkıntı yok.');
});

const GIZLI_ANAHTAR = 'senin-cok-gizli-jwt-anahtarin-aga-kimseye-verme';

// --- PANEL KAYIT OLMA (/register) ---
app.post('/register', (req, res) => {
    const { username, password } = req.body;
    bcrypt.hash(password, 10, (err, hash) => {
        if (err) return res.status(500).json({ message: "Şifreleme hatası" });
        const sql = "INSERT INTO panel_users (username, password) VALUES (?, ?)";
        db.query(sql, [username, hash], (err, result) => {
            if (err) return res.status(500).json({ message: "Kullanıcı zaten var veya başka bir hata oldu." });
            res.status(201).json({ message: "Panel hesabı oluşturuldu." });
        });
    });
});

// --- PANEL GİRİŞ YAPMA (/login) ---
app.post('/login', (req, res) => {
    const { username, password } = req.body;
    const sql = "SELECT * FROM panel_users WHERE username = ?";
    db.query(sql, [username], (err, results) => {
        if (err || results.length === 0) {
            return res.status(401).json({ message: "Kullanıcı adı veya şifre yanlış." });
        }
        const user = results[0];
        bcrypt.compare(password, user.password, (err, isMatch) => {
            if (!isMatch) {
                return res.status(401).json({ message: "Kullanıcı adı veya şifre yanlış." });
            }
            const token = jwt.sign(
                { id: user.id, username: user.username, role: user.role },
                GIZLI_ANAHTAR,
                { expiresIn: '8h' }
            );
            res.json({ message: "Giriş başarılı!", token: token });
        });
    });
});

// --- MİDDLEWARE: Token Kontrolü ---
const verifyToken = (req, res, next) => {
    const token = req.headers['authorization']?.split(' ')[1];
    if (!token) {
        return res.status(403).json({ message: "Token lazım gardaş, nerden bileyim kimsin." });
    }
    jwt.verify(token, GIZLI_ANAHTAR, (err, decoded) => {
        if (err) {
            return res.status(401).json({ message: "Tokenin sahte ya da süresi geçmiş." });
        }
        req.user = decoded;
        next();
    });
};

// --- OYUNCU LİSTESİNİ ÇEKME (/players) ---
app.get('/players', verifyToken, (req, res) => {
    const sql = "SELECT citizenid, name, job, money FROM players";
    db.query(sql, (err, results) => {
        if (err) {
            return res.status(500).json({ message: "Oyuncuları çekerken patladık." });
        }
        const players = results.map(p => {
            try {
                return {
                    ...p,
                    job: JSON.parse(p.job),
                    money: JSON.parse(p.money)
                };
            } catch (e) {
                return p;
            }
        });
        res.json(players);
    });
});

const PORT = 3001;
app.listen(PORT, () => {
  console.log(`Backend motoru http://localhost:${PORT} adresinde çalışıyor, gaz vermeye hazır!`);
});