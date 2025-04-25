// FILE: pages/_app.js   (wird mit <script type="module"> eingebunden)

// 1) EINMAL React + ReactDOM von esm.sh importieren
import React      from 'https://esm.sh/react@18';
import ReactDOM   from 'https://esm.sh/react-dom@18';
import { createRoot } from 'https://esm.sh/react-dom@18/client';

// 2) global verfügbar machen, damit main.js darauf zugreifen kann
window.React          = React;
window.ReactDOM       = ReactDOM;
window.ReactDOMClient = { createRoot };
console.log('[KIBT] *** _app.js geladen ***');
// 3) Alles Weitere dynamisch nachladen, sobald React steht
(async () => {
  console.log('[KIBT] *** _app.js geladen ***');
  // 3a) Hero-Icons
  const { MicrophoneIcon, PaperAirplaneIcon } =
    await import('https://esm.sh/@heroicons/react@2.0.18/24/outline');
    
  window.MicrophoneIcon   = MicrophoneIcon;
  window.PaperAirplaneIcon= PaperAirplaneIcon;

  // 3b) Chat-Komponente laden
  const { default: ChatWindow } =
    await import('../components/ChatWindow.js');
  window.ChatWindow = ChatWindow;

  // 3c) Bootstrap-Skript starten
  await import('../src/main.js');
})();
