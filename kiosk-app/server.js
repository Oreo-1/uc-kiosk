const express = require("express");
const sqlite3 = require("sqlite3").verbose();
const fs = require("fs");
const cors = require("cors");
const path = require("path");

const app = express();
app.use(express.json());
app.use(cors());

// create db if it doesn't exist
const dataDir = path.join(__dirname, "data");
// create data directory if not exists
if (!fs.existsSync(dataDir)) {
  fs.mkdirSync(dataDir, { recursive: true });
}

const dbPath = path.join(dataDir, "kiosk.db");

// SQLite will create kiosk.db if it doesn't exist
const db = new sqlite3.Database(dbPath, (err) => {
  if (err) {
    console.error("Failed to open database:", err.message);
  } else {
    console.log("Database ready at:", dbPath);
  }
});

app.use(express.static(path.join(__dirname, "public")));

// create table if not exists
db.run(`
  CREATE TABLE IF NOT EXISTS entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    message TEXT,
    printed INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )
`);

// receive input
app.post("/submit", (req, res) => {
  const { name, message } = req.body;

  db.run(
    "INSERT INTO entries (name, message) VALUES (?, ?)",
    [name, message],
    function () {
      res.json({ success: true, id: this.lastID });
    }
  );
});

// "Print" unprinted entries
app.post("/print", (req, res) => {
  db.all("SELECT * FROM entries WHERE printed = 0", (err, rows) => {
    if (!rows.length) return res.json({ status: "nothing to print" });

    let output = "";
    rows.forEach(row => {
      output += `ID: ${row.id}\nName: ${row.name}\nMessage: ${row.message}\n---\n`;
    });

    const outputPath = path.join(__dirname, "output", "order.txt"); // lokasi receipt na nnti, bs di ubah utk print
    fs.appendFileSync(outputPath, output);


    db.run("UPDATE entries SET printed = 1 WHERE printed = 0");
    res.json({ status: "printed", count: rows.length });
  });
});

app.listen(3000, () => {
  console.log("Server running on port 3000");
});
