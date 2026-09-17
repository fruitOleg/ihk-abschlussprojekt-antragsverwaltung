/*
 * Gemeinsame Funktionen für alle Seiten: Anmeldung, Kopfzeile,
 * Formatierung, Hinweise und Pop-up-Abfragen.
 */
const App = (() => {
  'use strict';

  const SITZUNG = 'antragsverwaltung-sitzung';
  const HINWEIS = 'antragsverwaltung-hinweis';

  const NAVIGATION = {
    lehrer: [['meine-antraege.html', 'Meine Anträge'], ['antrag-neu.html', 'Antrag stellen']],
    al:     [['eingang.html', 'Anträge']],
    ssl:    [['eingang.html', 'Anträge']],
    sl:     [['eingang.html', 'Anträge']]
  };

  /* ---------- Sitzung ---------- */

  function aktuellerBenutzer() {
    let id = null;
    try { id = sessionStorage.getItem(SITZUNG); } catch (e) { /* kein Sitzungsspeicher */ }
    return id ? Daten.benutzerNachId(Number(id)) : null;
  }

  function anmelden(benutzer) {
    sessionStorage.setItem(SITZUNG, String(benutzer.id));
  }

  function abmelden() {
    sessionStorage.removeItem(SITZUNG);
    location.href = 'index.html';
  }

  function startseite(benutzer) {
    return benutzer.rolle === 'lehrer' ? 'meine-antraege.html' : 'eingang.html';
  }

  // Leitet weiter, wenn niemand angemeldet ist oder die Rolle nicht passt
  function seiteSchuetzen(erlaubteRollen) {
    const benutzer = aktuellerBenutzer();
    if (!benutzer) {
      location.replace('index.html');
      return null;
    }
    if (!erlaubteRollen.includes(benutzer.rolle)) {
      location.replace(startseite(benutzer));
      return null;
    }
    kopfzeileAufbauen(benutzer);
    gemerktenHinweisZeigen();
    return benutzer;
  }

  function kopfzeileAufbauen(benutzer) {
    const kopf = document.getElementById('kopf');
    const seite = location.pathname.split('/').pop();
    const links = NAVIGATION[benutzer.rolle].map(([ziel, text]) => {
      const aktiv = ziel === seite ? ' aria-current="page"' : '';
      return `<a href="${ziel}"${aktiv}>${text}</a>`;
    }).join('');

    kopf.innerHTML = `
      <div class="kopf__innen">
        <a class="kopf__marke" href="${startseite(benutzer)}">Antragsverwaltung</a>
        <nav class="kopf__nav" aria-label="Hauptnavigation">${links}</nav>
        <div class="kopf__benutzer">
          <span>${esc(benutzer.name)} (${Daten.ROLLEN[benutzer.rolle]})</span>
          <button type="button" class="knopf knopf--leise" id="abmelden">Abmelden</button>
        </div>
      </div>`;

    document.getElementById('abmelden').addEventListener('click', abmelden);
  }

  /* ---------- Formatierung ---------- */

  function esc(wert) {
    const ersatz = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(wert ?? '').replace(/[&<>"']/g, zeichen => ersatz[zeichen]);
  }

  const DATUM = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });

  // nimmt "JJJJ-MM-TT" oder einen vollständigen ISO-Zeitstempel
  function datum(wert) {
    if (!wert) return '';
    const d = wert.length === 10 ? new Date(`${wert}T00:00:00`) : new Date(wert);
    return DATUM.format(d);
  }

  function statusMarke(status) {
    return `<span class="status status--${status}">${Daten.STATUS[status].text}</span>`;
  }

  function parameter(name) {
    return new URLSearchParams(location.search).get(name);
  }

  /* ---------- Hinweise ---------- */

  // Hinweis für die nächste Seite merken (z. B. nach dem Absenden)
  function hinweisMerken(text) {
    try { sessionStorage.setItem(HINWEIS, text); } catch (e) { /* kein Sitzungsspeicher */ }
  }

  function gemerktenHinweisZeigen() {
    let text = null;
    try {
      text = sessionStorage.getItem(HINWEIS);
      sessionStorage.removeItem(HINWEIS);
    } catch (e) { /* kein Sitzungsspeicher */ }
    if (text) hinweisZeigen(text);
  }

  function hinweisZeigen(text, art = 'erfolg') {
    const ort = document.querySelector('[data-hinweise]');
    ort.querySelectorAll(':scope > .hinweis').forEach(h => h.remove());
    const box = document.createElement('div');
    box.className = `hinweis hinweis--${art}`;
    box.setAttribute('role', art === 'fehler' ? 'alert' : 'status');
    box.textContent = text;
    ort.prepend(box);
  }

  /* ---------- Formularfehler ---------- */

  function fehlerSetzen(feld, text) {
    fehlerEntfernen(feld);
    const meldung = document.createElement('small');
    meldung.className = 'feld__fehler';
    meldung.id = `${feld.id}-fehler`;
    meldung.textContent = text;
    feld.setAttribute('aria-invalid', 'true');
    feld.setAttribute('aria-describedby', meldung.id);
    feld.closest('.feld').appendChild(meldung);
  }

  function fehlerEntfernen(feld) {
    feld.removeAttribute('aria-invalid');
    feld.removeAttribute('aria-describedby');
    const alt = document.getElementById(`${feld.id}-fehler`);
    if (alt) alt.remove();
  }

  /* ---------- Pop-up-Abfrage ---------- */

  // liefert true bei Bestätigung, false bei Abbrechen oder Esc
  function bestaetigen({ titel, text, ja, nein = 'Abbrechen', gefahr = false }) {
    return new Promise(fertig => {
      const dialog = document.createElement('dialog');
      dialog.className = 'dialog';
      dialog.innerHTML = `
        <form method="dialog">
          <h2 class="dialog__titel">${esc(titel)}</h2>
          <p class="dialog__text">${esc(text)}</p>
          <div class="knopfzeile">
            <button value="nein" class="knopf knopf--zweit">${esc(nein)}</button>
            <button value="ja" class="knopf${gefahr ? ' knopf--gefahr' : ''}">${esc(ja)}</button>
          </div>
        </form>`;
      document.body.appendChild(dialog);
      dialog.addEventListener('close', () => {
        fertig(dialog.returnValue === 'ja');
        dialog.remove();
      });
      dialog.showModal();
      dialog.querySelector('button[value="nein"]').focus();
    });
  }

  return {
    aktuellerBenutzer, anmelden, startseite, seiteSchuetzen,
    esc, datum, statusMarke, parameter,
    hinweisMerken, hinweisZeigen,
    fehlerSetzen, fehlerEntfernen, bestaetigen
  };
})();
