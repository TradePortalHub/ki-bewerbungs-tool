// FILE: pages/apply/[jobId].js
import React from 'react';
import fs from 'fs';
import path from 'path';

/**
 * Ermittelt alle zu exportierenden Pfade aus den JSON-Dateien.
 */
export async function getStaticPaths() {
  const dir = path.join(process.cwd(), 'public', 'companies');
  const ids = new Set();

  if (fs.existsSync(dir)) {
    const files = fs.readdirSync(dir).filter((f) => f.endsWith('.json'));
    for (const file of files) {
      const data = JSON.parse(fs.readFileSync(path.join(dir, file), 'utf8'));

      if (Array.isArray(data.postings)) {
        data.postings.forEach((p) => ids.add(String(p.id)));
      } else if (Array.isArray(data.jobs)) {
        data.jobs.forEach((p) => ids.add(String(p.id)));
      } else if (data.id) {
        ids.add(String(data.id));
      }
    }
  }

  return {
    paths: [...ids].map((id) => ({ params: { jobId: id } })),
    fallback: false,
  };
}

/**
 * Übergibt nur die jobId als Prop.
 */
export async function getStaticProps({ params }) {
  return {
    props: {
      jobId: params.jobId,
    },
  };
}

/**
 * Die Seite selbst liefert nur das Mount-Point-Div
 * und eine unsichtbare H1 für Accessibility/SEO.
 * Sämtliche Tags werden hier mit React.createElement erstellt.
 */
export default function ApplyJob({ jobId }) {
  return React.createElement(
    React.Fragment,
    null,
    React.createElement(
      'h1',
      { className: 'sr-only' },
      `Bewerbung #${jobId}`
    ),
    React.createElement('div', {
      id: 'kibt-app',
      'data-job-id': jobId,
    })
  );
}
