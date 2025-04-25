import fs from 'fs';
import path from 'path';

export default async function handler(req, res) {
  if (req.method !== 'GET') {
    res.setHeader('Allow', ['GET']);
    return res.status(405).end();
  }

  const rawData = fs.readFileSync(path.join(process.cwd(), 'data', 'config.json'), 'utf-8');
  const cfg = JSON.parse(rawData);
  let raw = {};
  if (cfg.rawJson) raw = JSON.parse(cfg.rawJson);
  else if (cfg.jsonUrl) raw = await fetch(cfg.jsonUrl).then(r => r.json());

  const postings = raw.postings || [];
  const baseUrl = process.env.NEXT_PUBLIC_BASE_URL || `${req.headers['x-forwarded-proto'] || 'https'}://${req.headers.host}`;

  const jobsWithLinks = postings.map(j => ({ ...j, link: `${baseUrl}/apply/${j.id}` }));
  return res.status(200).json({ postings: jobsWithLinks });
}