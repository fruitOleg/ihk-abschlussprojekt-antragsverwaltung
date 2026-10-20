/*
 * Gemeinsame Funktionen für alle Seiten: Anmeldung, Kopfzeile,
 * Formatierung, Hinweise und Pop-up-Abfragen.
 */
const App = (() => {
  'use strict';

  const SITZUNG = 'antragsverwaltung-sitzung';
  const HINWEIS = 'antragsverwaltung-hinweis';

  // Menüpunkte und die Rollen, die sie sehen; wer mehrere Rollen hat, sieht alles davon
  const NAVIGATION = [
    ['eingang.html',        'Zur Bearbeitung', ['al', 'ssl', 'sl', 'sek']],
    ['meine-antraege.html', 'Meine Anträge',   ['lehrer']],
    ['antrag-neu.html',     'Antrag stellen',  ['lehrer']],
    ['uebersicht.html',     'Alle Anträge',    ['al', 'ssl', 'sl', 'sek']],
    ['protokoll.html',      'Log & Export',    ['sl']],
    ['benutzer.html',       'Benutzer',        ['admin']]
  ];

  /* ---------- Sitzung ---------- */

  function aktuellerBenutzer() {
    let id = null;
    try { id = sessionStorage.getItem(SITZUNG); } catch (e) { /* kein Sitzungsspeicher */ }
    const benutzer = id ? Daten.benutzerNachId(Number(id)) : null;
    return benutzer && benutzer.aktiv ? benutzer : null;
  }

  function anmelden(benutzer) {
    sessionStorage.setItem(SITZUNG, String(benutzer.id));
  }

  function abmelden() {
    sessionStorage.removeItem(SITZUNG);
    location.href = 'index.html';
  }

  function hatEine(benutzer, rollen) {
    return rollen.some(rolle => Daten.hatRolle(benutzer, rolle));
  }

  function startseite(benutzer) {
    if (hatEine(benutzer, ['al', 'ssl', 'sl', 'sek'])) return 'eingang.html';
    if (Daten.hatRolle(benutzer, 'lehrer')) return 'meine-antraege.html';
    return 'benutzer.html';
  }

  // Leitet weiter, wenn niemand angemeldet ist oder keine passende Rolle vorliegt
  function seiteSchuetzen(erlaubteRollen) {
    const benutzer = aktuellerBenutzer();
    if (!benutzer) {
      location.replace('index.html');
      return null;
    }
    if (erlaubteRollen && !hatEine(benutzer, erlaubteRollen)) {
      location.replace(startseite(benutzer));
      return null;
    }
    kopfzeileAufbauen(benutzer);
    gemerktenHinweisZeigen();
    popupsZeigen(benutzer);
    return benutzer;
  }

  function kopfzeileAufbauen(benutzer) {
    const kopf = document.getElementById('kopf');
    const seite = location.pathname.split('/').pop();
    const links = NAVIGATION
      .filter(([, , rollen]) => hatEine(benutzer, rollen))
      .map(([ziel, text]) => `<a href="${ziel}"${ziel === seite ? ' aria-current="page"' : ''}>${text}</a>`)
      .join('');

    const ungelesen = Daten.ungeleseneAnzahl(benutzer);
    const zaehler = ungelesen ? ` <span class="zaehler" aria-label="${ungelesen} ungelesen">${ungelesen}</span>` : '';

    kopf.innerHTML = `
      <div class="kopf__innen">
        <a class="kopf__marke" href="${startseite(benutzer)}">Antragsverwaltung</a>
        <nav class="kopf__nav" aria-label="Hauptnavigation">
          ${links}
          <a href="nachrichten.html"${seite === 'nachrichten.html' ? ' aria-current="page"' : ''}>Nachrichten${zaehler}</a>
        </nav>
        <div class="kopf__benutzer">
          <span title="${esc(Daten.rollenText(benutzer))}">${esc(benutzer.name)}</span>
          <button type="button" class="knopf knopf--leise" id="abmelden">Abmelden</button>
        </div>
      </div>`;

    document.getElementById('abmelden').addEventListener('click', abmelden);
  }

  // wichtige Benachrichtigungen (z. B. Ablehnung durch den SL) erscheinen einmal als Pop-up
  async function popupsZeigen(benutzer) {
    const nachrichten = Daten.popupsAbholen(benutzer);
    if (!nachrichten.length) return;
    kopfzeileAufbauen(benutzer);
    for (const n of nachrichten) {
      await bestaetigen({ titel: 'Neue Benachrichtigung', text: n.text, ja: 'Verstanden', nein: '' });
    }
  }

  /* ---------- Formatierung ---------- */

  function esc(wert) {
    const ersatz = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(wert ?? '').replace(/[&<>"']/g, zeichen => ersatz[zeichen]);
  }

  const DATUM = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const UHR = new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit' });

  // nimmt "JJJJ-MM-TT" oder einen vollständigen ISO-Zeitstempel
  function datum(wert) {
    if (!wert) return '';
    const d = wert.length === 10 ? new Date(`${wert}T00:00:00`) : new Date(wert);
    return DATUM.format(d);
  }

  function zeitpunkt(wert) {
    if (!wert) return '';
    return `${DATUM.format(new Date(wert))}, ${UHR.format(new Date(wert))}`;
  }

  function statusMarke(antrag) {
    return `<span class="status status--${Daten.statusFarbe(antrag)}">${esc(Daten.statusText(antrag))}</span>`;
  }

  function parameter(name) {
    return new URLSearchParams(location.search).get(name);
  }

  // Datei im Browser speichern (Log-Ausgabe, Datensicherung)
  function herunterladen(dateiname, inhalt, typ) {
    const url = URL.createObjectURL(new Blob([inhalt], { type: typ }));
    const link = document.createElement('a');
    link.href = url;
    link.download = dateiname;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
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
    box.scrollIntoView({ block: 'nearest' });
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

  // liefert true bei Bestätigung, false bei Abbrechen oder Esc.
  // Mit "eingabe" (Beschriftung eines Textfelds) wird stattdessen der eingegebene Text geliefert.
  // nein = '' zeigt nur einen Knopf (reine Information).
  function bestaetigen({ titel, text, ja, nein = 'Abbrechen', gefahr = false, eingabe = '', pflicht = true }) {
    return new Promise(fertig => {
      const dialog = document.createElement('dialog');
      dialog.className = 'dialog';
      dialog.innerHTML = `
        <form method="dialog">
          <h2 class="dialog__titel">${esc(titel)}</h2>
          <p class="dialog__text">${esc(text)}</p>
          ${eingabe ? `<div class="feld dialog__feld">
            <label for="dialog-eingabe">${esc(eingabe)}${pflicht ? '' : ' (optional)'}</label>
            <textarea id="dialog-eingabe" rows="3"${pflicht ? ' required' : ''}></textarea>
          </div>` : ''}
          <div class="knopfzeile">
            ${nein ? `<button type="button" value="nein" class="knopf knopf--zweit">${esc(nein)}</button>` : ''}
            <button type="button" value="ja" class="knopf${gefahr ? ' knopf--gefahr' : ''}">${esc(ja)}</button>
          </div>
        </form>`;
      document.body.appendChild(dialog);

      // Die Knöpfe beenden die Abfrage selbst. Auf das close-Ereignis allein ist kein Verlass:
      // manche Chromium-Fassungen (u. a. der Browser in VS Code) lösen es dabei nicht aus.
      const feld = dialog.querySelector('textarea');
      let erledigt = false;
      const beenden = ok => {
        if (erledigt) return;
        erledigt = true;
        if (dialog.open) dialog.close();
        dialog.remove();
        fertig(eingabe ? (ok ? feld.value.trim() : false) : ok);
      };

      dialog.querySelector('button[value="ja"]').addEventListener('click', () => {
        if (feld && pflicht && !feld.value.trim()) {
          feld.setCustomValidity('Bitte ausfüllen.');
          feld.reportValidity();
          feld.focus();
          return;
        }
        beenden(true);
      });
      const knopfNein = dialog.querySelector('button[value="nein"]');
      if (knopfNein) knopfNein.addEventListener('click', () => beenden(false));
      if (feld) feld.addEventListener('input', () => feld.setCustomValidity(''));
      dialog.addEventListener('cancel', ereignis => {
        ereignis.preventDefault();
        beenden(false);
      });
      dialog.addEventListener('close', () => beenden(false));
      dialog.querySelector('form').addEventListener('submit', ereignis => ereignis.preventDefault());

      dialog.showModal();
      (feld || dialog.querySelector(nein ? 'button[value="nein"]' : 'button[value="ja"]')).focus();
    });
  }

  // zweifache Abfrage vor verbindlichen Aktionen
  async function zweifach({ titel, frage, bestaetigung, ja = 'Bestätigen', gefahr = false }) {
    const erste = await bestaetigen({ titel: `${titel} (1 von 2)`, text: frage, ja: 'Weiter', gefahr });
    if (!erste) return false;
    return bestaetigen({ titel: `${titel} (2 von 2)`, text: bestaetigung, ja, gefahr });
  }

  return {
    aktuellerBenutzer, anmelden, startseite, seiteSchuetzen,
    esc, datum, zeitpunkt, statusMarke, parameter, herunterladen,
    hinweisMerken, hinweisZeigen,
    fehlerSetzen, fehlerEntfernen, bestaetigen, zweifach
  };
})();
