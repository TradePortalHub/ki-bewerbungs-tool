// FILE: src/main.js  
// Wird per wp_enqueue_script als klassisches <script> geladen

console.log('[KIBT] *** main.js geladen ***');

/* ------------------------------------------------------------------ */
/* 0) JobScout deaktivieren                                            */
/* ------------------------------------------------------------------ */
if (window.jobscout && typeof window.jobscout.init === 'function') {
  console.warn('[KIBT] jobscout.init disabled to prevent conflicts');
  window.jobscout.init = function () {};
}

/* ------------------------------------------------------------------ */
/* 0a) Dummy <h1> für Route-Announcer                                 */
/* ------------------------------------------------------------------ */
(function ensureH1() {
  if (!document.querySelector('h1')) {
    const h1 = document.createElement('h1');
    h1.className = 'screen-reader-text';
    h1.textContent = 'Stellenangebote';
    document.body.prepend(h1);
    console.info('[KIBT] Dummy <h1> eingefügt');
  }
})();

/* ------------------------------------------------------------------ */
/* 1) Bootstrap                                                        */
/* ------------------------------------------------------------------ */
async function bootstrap() {
  try {
    // ─── 1.0 Guard: Einstellungen vorhanden? ─────────────────────────
    if (
      typeof window.KIBT_SETTINGS !== 'object' ||
      !window.KIBT_SETTINGS.rest_base
    ) {
      console.warn(
        '[KIBT] window.KIBT_SETTINGS fehlt oder ist unvollständig – bootstrap wird abgebrochen'
      );
      return;
    }
    const { rest_base, nonce, company } = window.KIBT_SETTINGS;

    // ─── 1.1 Container & Slug prüfen ─────────────────────────────────
    const container = document.getElementById('kibt-app');
    if (!container) {
      console.info('[KIBT] Kein <div id="kibt-app"> gefunden – nichts zu tun');
      return;
    }
    if (!company) {
      console.warn('[KIBT] Kein company-Slug gesetzt – bootstrap wird abgebrochen');
      return;
    }

    // ─── 1.2 Job-ID (kann leer sein) ─────────────────────────────────
    const jobId = container.dataset.jobId;

    // ─── 1.3 Config + Jobarray laden ────────────────────────────────
    const cfgRes = await fetch(`${rest_base}config/${company}`, {
      headers: { 'X-WP-Nonce': nonce },
    });
    if (!cfgRes.ok) throw new Error('Config-Laden fehlgeschlagen');
    const cfg = await cfgRes.json();
    const jobs = Array.isArray(cfg.jobs) ? cfg.jobs : [];

    // ─── 2) Job-Übersicht: keine jobId → Liste rendern ─────────────
    if (!jobId) {
      let html = '<ul class="kibt-job-list">';
      jobs.forEach(job => {
        html += `
          <li>
            <a href="?company=${encodeURIComponent(company)}&job_id=${encodeURIComponent(
          job.id
        )}">
              ${job.title}
            </a>
          </li>`;
      });
      html += '</ul>';
      container.innerHTML = html;
      return;
    }

    // ─── 3) Debug in Konsole ────────────────────────────────────────
    console.group('[KIBT DEBUG]');
    console.log('company:', company);
    console.log('jobId:', jobId);
    console.log('jobs.length:', jobs.length);
    console.table(jobs.map(j => ({ id: j.id, title: j.title })));
    console.groupEnd();

    // ─── 4) Job-Detail suchen ───────────────────────────────────────
    const job = jobs.find(
      j =>
        String(j.id).trim().toLowerCase() === String(jobId).trim().toLowerCase()
    );
    if (!job) {
      container.innerHTML = '<p>Stelle nicht gefunden.</p>';
      return;
    }

    // ─── 5) <h1> auf Job-Titel setzen ───────────────────────────────
    const h1 = document.querySelector('h1');
    if (h1) h1.textContent = job.title || 'Stellenangebot';

    // ─── 6) ChatWindow rendern (React 18 / Fallback ReactDOM) ───────
    const root = window.ReactDOMClient
      ? window.ReactDOMClient.createRoot(container)
      : null;
    const chatEl = React.createElement(
      window.ChatWindow,
      {
        job,
        company,
        onFinish: async msgs => {
          try {
            await fetch(`${rest_base}apply/${company}`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
              },
              body: JSON.stringify({ jobId, company, messages: msgs }),
            });
            alert('Bewerbung erfolgreich!');
          } catch (err) {
            console.error('[KIBT] Error sending application:', err);
            alert('Fehler beim Abschicken');
          }
        },
      },
      null
    );
    if (root && root.render) {
      root.render(chatEl);
    } else {
      ReactDOM.render(chatEl, container);
    }
  } catch (err) {
    console.error('[KIBT] bootstrap error:', err);
    const msg = document.createElement('div');
    msg.style = 'color:red;padding:1em;font-weight:bold;background:#fee;';
    msg.textContent = 'KIBT-Fehler: ' + err.message;
    document.body.prepend(msg);
  }
}

/* ------------------------------------------------------------------ */
/* 2) Ausführen, sobald DOM bereit                                      */
/* ------------------------------------------------------------------ */
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
  bootstrap();
}
