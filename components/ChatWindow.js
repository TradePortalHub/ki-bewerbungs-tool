// FILE: components/ChatWindow.js
// Verwendet ausschließlich window.React (aus _app.js) und keine weiteren React-Instanzen

const { createElement, useState, useEffect, useRef } = window.React;
console.log('[KIBT] *** ChatWindow.js geladen ***');
// --- Inline-SVG für Mikrofon (outline) ---
const MicrophoneIcon = props =>
  createElement(
    'svg',
    {
      xmlns: 'http://www.w3.org/2000/svg',
      viewBox: '0 0 20 20',
      fill: 'currentColor',
      ...props
    },
    // Pfad aus Heroicons (outline)
    createElement('path', {
      d: 'M8 4a3 3 0 00-3 3v3a3 3 0 006 0V7a3 3 0 00-3-3z'
    }),
    createElement('path', {
      d: 'M5 10v1a5 5 0 0010 0v-1m-5 4v3'
    })
  );

// --- Inline-SVG für Papierflieger (outline) ---
const PaperAirplaneIcon = props =>
  createElement(
    'svg',
    {
      xmlns: 'http://www.w3.org/2000/svg',
      viewBox: '0 0 20 20',
      fill: 'currentColor',
      ...props
    },
    // Pfad aus Heroicons (outline)
    createElement('path', {
      d: 'M2.01 21L23 12 2.01 3 2 10l15 2-15 2z'
    })
  );

export default function ChatWindow({ job, onFinish }) {
  // 0) Robustheit: Falls kein Job-Objekt ankommt
  if (!job || typeof job !== 'object') {
    return createElement(
      'div',
      { className: 'p-4 bg-red-50 text-red-600' },
      createElement('h2', null, 'Fehler'),
      createElement('p', null, 'Job-Daten konnten nicht geladen werden.'),
      createElement('a', {
        href: `?company=${encodeURIComponent(company)}`,
        className: 'text-blue-500 underline',
      }, 'Zurück zur Stellenliste')
    );
  }

  // 0a) Default-Felder
  const {
    id = '',
    title = '–',
    description = '',
    announcement = '',
    questions = [],
    fileUploads = [],
    informationRequested = [],
    companyLogo = '',
    companyName = '',
    companyAddress = '',
    companyWebsite = '',
  } = job;

  // 1) State & Speech Recognition
  const [msgs, setMsgs] = useState([]);
  const [loading, setLoading] = useState(false);
  const [input, setInput] = useState('');
  const [listening, setListening] = useState(false);
  const recognitionRef = useRef(null);

  useEffect(() => {
    const SpeechRecognition =
      window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return;

    const recog = new SpeechRecognition();
    recog.lang = 'de-DE';
    recog.interimResults = false;
    recog.onresult = e => {
      const text = e.results[0][0].transcript;
      setInput(prev => `${prev} ${text}`);
    };
    recog.onend = () => setListening(false);
    recognitionRef.current = recog;
  }, []);

  const startListening = () => {
    if (recognitionRef.current) {
      setListening(true);
      recognitionRef.current.start();
    }
  };

  // 2) sendMessage: User → API → Bot
  const sendMessage = async text => {
    if (!text.trim()) return;
  
    const userMsg  = { role: 'user', content: text.trim() };
    const updated  = [...msgs, userMsg];
    setMsgs(updated);
    setInput('');
    setLoading(true);
  
    /* ►► NEU: REST-URL & Nonce aus KIBT_SETTINGS ◄◄ */
    const { rest_base, nonce } = window.KIBT_SETTINGS ?? {};
  
    try {
      const res  = await fetch(rest_base + 'chat', {
        method : 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce'   : nonce            // ← WP-REST-Auth
        },
        body   : JSON.stringify({ messages: updated, jobId: id })
      });
  
      const data = await res.json();
      if (data?.message?.role) {
        setMsgs(prev => [...prev, data.message]);
      }
    } catch (err) {
      console.error(err);
      setMsgs(prev => [
        ...prev,
        { role: 'system', content: 'Fehler beim Senden.' }
      ]);
    } finally {
      setLoading(false);
    }
  };

  // 3) UI-Tree per createElement
  return createElement(
    'div',
    { className: 'bg-gray-50 min-h-screen py-6' },
    createElement(
      'div',
      {
        className:
          'max-w-3xl mx-auto bg-white shadow-lg rounded-lg p-6 flex flex-col space-y-6',
      },
      // HEADER
      createElement(
        'header',
        { className: 'flex items-center space-x-4' },
        companyLogo &&
          createElement('img', {
            src: companyLogo,
            alt: companyName,
            className: 'h-16 w-16 object-contain',
          }),
        createElement(
          'div',
          null,
          createElement('h2', { className: 'text-2xl font-bold' }, title),
          companyName &&
            createElement('p', { className: 'text-gray-600' }, companyName),
          companyAddress &&
            createElement(
              'p',
              { className: 'text-sm text-gray-500' },
              companyAddress
            ),
          companyWebsite &&
            createElement(
              'a',
              {
                href: companyWebsite,
                target: '_blank',
                rel: 'noopener noreferrer',
                className: 'text-blue-500 hover:underline text-sm',
              },
              companyWebsite
            )
        )
      ),

      // DETAILS …
      createElement(
        'section',
        { className: 'space-y-4 text-gray-800' },
        announcement &&
          createElement(
            'p',
            { className: 'italic text-gray-700' },
            `„${announcement}“`
          ),
        description && createElement('p', null, description),
        questions.length > 0 &&
          createElement(
            'div',
            null,
            createElement('h3', { className: 'font-semibold' }, 'Fragen:'),
            createElement(
              'ul',
              { className: 'list-disc list-inside' },
              ...questions.map((q, i) =>
                createElement('li', { key: i }, q)
              )
            )
          ),
        fileUploads.length > 0 &&
          createElement(
            'div',
            null,
            createElement('h3', { className: 'font-semibold' }, 'Anzufordernde Dateien:'),
            createElement(
              'ul',
              { className: 'list-disc list-inside' },
              ...fileUploads.map((f, i) =>
                createElement('li', { key: i }, `${f.label}${f.required ? ' (Pflicht)' : ''}`)
              )
            )
          ),
        informationRequested.length > 0 &&
          createElement(
            'div',
            null,
            createElement('h3', { className: 'font-semibold' }, 'Zusätzliche Infos:'),
            createElement(
              'ul',
              { className: 'list-disc list-inside' },
              ...informationRequested.map((info, i) =>
                createElement('li', { key: i }, info)
              )
            )
          )
      ),

      // CHAT-BEREICH
      createElement(
        'section',
        { className: 'flex flex-col space-y-4' },

        // Nachrichtenfenster
        createElement(
          'div',
          {
            className:
              'flex-1 h-80 overflow-y-auto border border-gray-200 rounded p-4 flex flex-col space-y-3',
          },
          ...msgs.map((m, i) =>
            createElement(
              'div',
              {
                key: i,
                className: `p-3 rounded-lg ${
                  m.role === 'user'
                    ? 'bg-blue-100 self-end'
                    : 'bg-gray-100 self-start'
                }`,
              },
              createElement('span', { className: 'block font-medium text-sm' }, m.role === 'user' ? 'Sie:' : 'Bot:'),
              createElement('p', { className: 'mt-1' }, m.content)
            )
          ),
          loading && createElement('p', { className: 'text-center text-gray-500' }, '...')
        ),

        // Eingabe & Buttons
        createElement(
          'div',
          { className: 'flex items-center space-x-2' },
          createElement(
            'button',
            {
              type: 'button',
              onClick: startListening,
              disabled: listening,
              className: `p-2 rounded-full border ${
                listening ? 'bg-red-100' : 'bg-white hover:bg-gray-50'
              }`,
            },
            createElement(MicrophoneIcon, { className: 'h-6 w-6 text-gray-600' })
          ),
          createElement('input', {
            type: 'text',
            value: input,
            onChange: e => setInput(e.target.value),
            placeholder: 'Nachricht eingeben …',
            className:
              'flex-1 border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400',
          }),
          createElement(
            'button',
            {
              type: 'button',
              onClick: () => sendMessage(input),
              className: 'p-2 bg-blue-600 rounded-full hover:bg-blue-700',
            },
            createElement(PaperAirplaneIcon, {
              className: 'h-6 w-6 text-white transform rotate-90',
            })
          )
        ),

        // Bewerbung abschließen
        createElement(
          'button',
          {
            onClick: () => onFinish(msgs),
            className:
              'w-full bg-green-600 text-white py-2 rounded hover:bg-green-700',
          },
          'Bewerbung abschließen'
        )
      )
    )
  );
}
