// FILE: pages/apply/index.js
export default function ApplyIndex() {
  // Diese Seite zeigt die Stellenliste und das Chat‑Widget (ohne job_id)
  return (
    <>
      <h1 className="sr-only">Stellenangebote</h1> {/* versteckt, aber vorhanden */}
      <div id="kibt-app" data-job-id="" />
    </>
  )
}
