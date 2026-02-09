const express = require("express");
const sqlite3 = require("sqlite3").verbose();
const fs = require("fs");
const cors = require("cors");
const path = require("path");

const app = express();
app.use(express.json());
app.use(cors());

// setup directory
// data directory (sqlite)
const dataDir = path.join(__dirname, "data");
if (!fs.existsSync(dataDir)) {
  fs.mkdirSync(dataDir, { recursive: true });
}

// output directory (order yg di print)
const outputDir = path.join(__dirname, "output");
if (!fs.existsSync(outputDir)) {
  fs.mkdirSync(outputDir, { recursive: true });
}

// setup database
const dbPath = path.join(dataDir, "kiosk.db");

// SQLite creates kiosk.db if it doesn't exist
const db = new sqlite3.Database(dbPath, (err) => {
  if (err) {
    console.error("Failed to open database:", err.message);
  } else {
    console.log("Database ready at:", dbPath);
  }
});

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

// serve static files from public directory
app.use(express.static(path.join(__dirname, "public")));


// routes
// receive input from kiosk
app.post("/submit", (req, res) => {
  const { name, message } = req.body;

  db.run(
    "INSERT INTO entries (name, message) VALUES (?, ?)",
    [name, message],
    function (err) {
      if (err) {
        return res.status(500).json({ error: err.message });
      }

      const orderId = this.lastID;
      const fileName = `order_${orderId}.txt`;
      const filePath = path.join(outputDir, fileName);

      const content =
`Order ID: ${orderId}
Name: ${name}
Message: ${message}
Time: ${new Date().toLocaleString()}
`;

      fs.writeFileSync(filePath, content);

      db.run(
        "UPDATE entries SET printed = 1 WHERE id = ?",
        [orderId]
      );

      res.json({ success: true, id: orderId });
    }
  );
});


// "print" unprinted entries (one file per ID)
app.post("/print", (req, res) => {
  db.all("SELECT * FROM entries WHERE printed = 0", (err, rows) => {
    if (err) {
      return res.status(500).json({ error: err.message });
    }

    if (rows.length === 0) {
      return res.json({ status: "nothing to print" });
    }

    rows.forEach(row => {
      const fileName = `order_${row.id}.txt`;
      const filePath = path.join(outputDir, fileName);

      const content =
`Order ID: ${row.id}
Name: ${row.name}
Message: ${row.message}
Time: ${row.created_at}
`;

      fs.writeFileSync(filePath, content);
    });

    db.run("UPDATE entries SET printed = 1 WHERE printed = 0");

    res.json({ status: "printed", count: rows.length });
  });
});


// start server
app.listen(3000, () => {
  console.log("Server running on port 3000");
});
