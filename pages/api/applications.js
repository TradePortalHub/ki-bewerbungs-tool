import fs from 'fs';
import path from 'path';
import { IncomingForm } from 'formidable';

// Next.js bodyParser global disabled; Formidable übernimmt Multipart Parsing
export const config = { api: { bodyParser: false } };

export default function handler(req, res) {
  const filePath = path.join(process.cwd(), 'data', 'applications.json');

  if (req.method === 'GET') {
    const data = fs.readFileSync(filePath, 'utf-8');
    return res.status(200).json(JSON.parse(data));
  }

  if (req.method === 'POST') {
    const form = new IncomingForm({
      multiples: true,
      uploadDir: path.join(process.cwd(), 'public', 'uploads'),
      keepExtensions: true
    });

    form.parse(req, (err, fields, files) => {
      if (err) return res.status(500).json({ error: err.message });

      const app = {
        id: Date.now(),
        jobId: fields.jobId,
        conversation: JSON.parse(fields.conversation),
        fields,
        files,
        submittedAt: new Date().toISOString()
      };

      const list = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
      list.push(app);
      fs.writeFileSync(filePath, JSON.stringify(list, null, 2));

      res.status(200).json({ status: 'ok' });
    });
    return;
  }

  res.setHeader('Allow', ['GET', 'POST']);
  res.status(405).end();
}