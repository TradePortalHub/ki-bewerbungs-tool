// FILE: pages/api/companies.js
import fs from 'fs';
import path from 'path';

export default function companiesHandler(req, res) {
  // 1) Pfad zum Ordner mit deinen Firmen‑JSONs
  const dir = path.join(process.cwd(), 'public', 'companies');

  // 2) Prüfen, ob das Verzeichnis existiert
  if (!fs.existsSync(dir)) {
    console.error(`Companies directory not found at ${dir}`);
    return res
      .status(500)
      .json({ error: `Companies-Verzeichnis nicht gefunden: ${dir}` });
  }

  try {
    // 3) Alle *.json‑Dateien auslesen
    const files = fs.readdirSync(dir).filter(f => f.endsWith('.json'));
    if (files.length === 0) {
      console.warn('Keine JSON‑Dateien im companies-Ordner gefunden');
      return res.status(200).json([]); // einfach leeres Array zurück
    }

    // 4) Jede Datei einlesen und parsen
    const companies = files.map(file => {
      const content = fs.readFileSync(path.join(dir, file), 'utf-8');
      return JSON.parse(content);
    });

    // 5) Erfolgreiche Antwort
    return res.status(200).json(companies);
  } catch (err) {
    // 6) Bei Lesefehlern aussagekräftig loggen und antworten
    console.error('Fehler beim Einlesen des companies-Ordners:', err);
    return res
      .status(500)
      .json({ error: `Fehler beim Lesen der Firmen: ${err.message}` });
  }
}
