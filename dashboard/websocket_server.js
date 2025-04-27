const express = require('express');
const app = express();
const http = require('http').createServer(app);
const io = require('socket.io')(http, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    }
});

// WebSocket kapcsolatok kezelése
io.on('connection', (socket) => {
    console.log('Kliens csatlakozott');

    socket.on('disconnect', () => {
        console.log('Kliens lecsatlakozott');
    });
});

// Munka frissítés esemény küldése minden kliensnek
function notifyWorkUpdate() {
    io.emit('workUpdated');
}

// Express route a munka frissítés triggerelésére
app.post('/notify-work-update', (req, res) => {
    notifyWorkUpdate();
    res.json({ success: true });
});

// Szerver indítása
const PORT = 3000;
http.listen(PORT, () => {
    console.log(`WebSocket szerver fut: http://localhost:${PORT}`);
});
