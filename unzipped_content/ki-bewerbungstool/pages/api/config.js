import fs from 'fs';
import path from 'path';

const cfgFile = path.join(process.cwd(), 'data', 'config.json');
export default function handler(req, res) {
  if (req.method === 'GET') {
    const raw = fs.readFileSync(cfgFile, 'utf-8');
    return res.status(200).json(JSON.parse(raw));
  }
  if (req.method === 'POST') {
    const { apiKey, jsonUrl, rawJson } = req.body;
    const data = { apiKey, jsonUrl, rawJson, config: {} };
    fs.writeFileSync(cfgFile, JSON.stringify(data, null, 2));
    return res.status(200).end();
  }
  res.setHeader('Allow', ['GET', 'POST']);
  res.status(405).end();
}