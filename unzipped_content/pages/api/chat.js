import { Configuration, OpenAIApi } from 'openai';
import fs from 'fs';
import path from 'path';

export default async function handler(req, res) {
  const { messages, jobId } = req.body;
  const cfgData = fs.readFileSync(path.join(process.cwd(), 'data', 'config.json'), 'utf-8');
  const cfg = JSON.parse(cfgData);

  let raw = {};
  if (cfg.rawJson) raw = JSON.parse(cfg.rawJson);
  else if (cfg.jsonUrl) raw = await fetch(cfg.jsonUrl).then(r => r.json());

  const job = (raw.postings || []).find(j => j.id === jobId);
  if (!job) return res.status(400).json({ error: 'Stelle nicht gefunden.' });

  const systemPrompt = `Du bist ein Interview-Bot für die Stelle "${job.title}" bei ${raw.companyName}.
Du darfst nur Fragen zur Ausschreibung und Firma beantworten.
Bei anderen Fragen antworte: \"Entschuldigung, nur zum Job/Firma.\"`;

  const client = new OpenAIApi(new Configuration({ apiKey: cfg.apiKey }));
  const chat = await client.createChatCompletion({
    model: 'gpt-4',
    messages: [{ role: 'system', content: systemPrompt }, ...messages]
  });

  return res.status(200).json({ message: chat.data.choices[0].message });
}