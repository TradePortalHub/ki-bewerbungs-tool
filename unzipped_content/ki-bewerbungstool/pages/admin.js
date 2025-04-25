import React, { useState, useEffect } from 'react';

export default function Admin() {
  const [apiKey, setApiKey] = useState('');
  const [jsonUrl, setJsonUrl] = useState('');
  const [rawJson, setRawJson] = useState('');
  const [companies, setCompanies] = useState([]);
  const [msg, setMsg] = useState('');

  useEffect(() => {
    // Load API settings
    fetch('/api/config')
      .then(res => res.json())
      .then(d => {
        setApiKey(d.apiKey);
        setJsonUrl(d.jsonUrl);
        setRawJson(d.rawJson);
      });
    // Load all company JSONs via our new endpoint
    fetch('/api/companies')
      .then(res => res.json())
      .then(data => setCompanies(data));
  }, []);

  const save = async () => {
    await fetch('/api/config', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ apiKey, jsonUrl, rawJson })
    });
    setMsg('Gespeichert.');
  };

  return React.createElement(
    'div',
    { className: 'p-4 max-w-3xl mx-auto' },
    // API Settings Section
    React.createElement(
      'h1',
      { className: 'text-2xl font-bold mb-4' },
      'Admin Settings'
    ),
    React.createElement('label', { className: 'block font-medium' }, 'OpenAI API Key:'),
    React.createElement('input', {
      className: 'border p-2 w-full mb-2',
      value: apiKey,
      onChange: e => setApiKey(e.target.value),
      placeholder: 'OpenAI API Key'
    }),
    React.createElement('label', { className: 'block font-medium' }, 'JSON Quelle (URL):'),
    React.createElement('input', {
      className: 'border p-2 w-full mb-2',
      value: jsonUrl,
      onChange: e => setJsonUrl(e.target.value),
      placeholder: 'JSON URL'
    }),
    React.createElement('label', { className: 'block font-medium' }, 'ODER JSON Text:'),
    React.createElement('textarea', {
      className: 'border p-2 w-full mb-4',
      rows: 6,
      value: rawJson,
      onChange: e => setRawJson(e.target.value),
      placeholder: 'Raw JSON'
    }),
    React.createElement(
      'button',
      {
        className: 'bg-blue-600 text-white px-4 py-2 rounded mb-4',
        onClick: save
      },
      'Speichern'
    ),
    msg && React.createElement('p', { className: 'mb-4 text-green-600' }, msg),
    // Company Listings Section
    React.createElement(
      'h2',
      { className: 'text-xl font-semibold mb-2' },
      'Unternehmen & Stellenangebote'
    ),
    React.createElement(
      'div',
      null,
      companies.map(company =>
        React.createElement(
          'div',
          { key: company.companyName, className: 'mb-6 border p-4 rounded' },
          React.createElement(
            'h3',
            { className: 'text-lg font-bold mb-1' },
            company.companyName
          ),
          React.createElement(
            'p',
            { className: 'mb-2 text-sm text-gray-600' },
            company.companyDescription
          ),
          React.createElement(
            'ul',
            { className: 'list-disc ml-5' },
            company.postings.map(post =>
              React.createElement(
                'li',
                { key: post.id, className: 'mb-1' },
                React.createElement(
                  'a',
                  {
                    href: `/apply/${post.id}`,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    className: 'text-blue-500 hover:underline'
                  },
                  post.title
                )
              )
            )
          )
        )
      )
    )
  );
}
