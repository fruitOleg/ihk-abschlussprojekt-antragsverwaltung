/*
 * Pop-up-Abfragen vor dem Absenden eines Formulars.
 *
 * Am <form> steuern data-Attribute, was gefragt wird:
 *   data-titel1, data-frage1, data-ja1, data-nein1, data-gefahr1  – erste Abfrage
 *   data-titel2, data-frage2, data-ja2, data-nein2, data-gefahr2  – optionale zweite Abfrage
 *   data-eingabe        – Beschriftung eines Textfelds in der ersten Abfrage
 *   data-eingabe-name   – Name des versteckten Feldes, in das der Text übernommen wird
 *   data-eingabe-pflicht="0" – Eingabe ist freiwillig
 * Erst wenn alle Abfragen bestätigt sind, wird das Formular wirklich abgeschickt.
 */
(() => {
  'use strict';

  function frage({ titel, text, ja, nein, gefahr, eingabe, pflicht }) {
    return new Promise(fertig => {
      const dialog = document.createElement('dialog');
      dialog.className = 'dialog';

      const formular = document.createElement('form');
      formular.method = 'dialog';

      const kopf = document.createElement('h2');
      kopf.className = 'dialog__titel';
      kopf.textContent = titel;

      const absatz = document.createElement('p');
      absatz.className = 'dialog__text';
      absatz.textContent = text;
      formular.append(kopf, absatz);

      let feld = null;
      if (eingabe) {
        const block = document.createElement('div');
        block.className = 'feld dialog__feld';
        const beschriftung = document.createElement('label');
        beschriftung.htmlFor = 'dialog-eingabe';
        beschriftung.textContent = pflicht ? eingabe : `${eingabe} (optional)`;
        feld = document.createElement('textarea');
        feld.id = 'dialog-eingabe';
        feld.rows = 3;
        feld.required = pflicht;
        block.append(beschriftung, feld);
        formular.append(block);
      }

      const zeile = document.createElement('div');
      zeile.className = 'knopfzeile';

      const knopfNein = document.createElement('button');
      knopfNein.value = 'nein';
      knopfNein.className = 'knopf knopf--zweit';
      knopfNein.textContent = nein;
      knopfNein.formNoValidate = true;

      const knopfJa = document.createElement('button');
      knopfJa.value = 'ja';
      knopfJa.className = gefahr ? 'knopf knopf--gefahr' : 'knopf';
      knopfJa.textContent = ja;

      zeile.append(knopfNein, knopfJa);
      formular.append(zeile);
      dialog.append(formular);
      document.body.append(dialog);

      dialog.addEventListener('close', () => {
        const ok = dialog.returnValue === 'ja';
        fertig(ok ? { ok: true, text: feld ? feld.value.trim() : '' } : { ok: false });
        dialog.remove();
      });
      dialog.showModal();
      knopfNein.focus();
    });
  }

  document.addEventListener('submit', async ereignis => {
    const formular = ereignis.target;
    if (formular.dataset.bestaetigt === 'ja') return;

    // die Abfrage kann am Formular oder am auslösenden Knopf stehen
    const quelle = ereignis.submitter && ereignis.submitter.dataset.frage1
      ? ereignis.submitter.dataset
      : formular.dataset;
    if (!quelle.frage1) return;

    ereignis.preventDefault();

    for (const nr of ['1', '2']) {
      const text = quelle['frage' + nr];
      if (!text) continue;
      const antwort = await frage({
        titel: quelle['titel' + nr] || '',
        text,
        ja: quelle['ja' + nr] || 'OK',
        nein: quelle['nein' + nr] || 'Abbrechen',
        gefahr: quelle['gefahr' + nr] === '1',
        eingabe: nr === '1' ? quelle.eingabe || '' : '',
        pflicht: quelle.eingabePflicht !== '0'
      });
      if (!antwort.ok) return;

      if (nr === '1' && quelle.eingabe) {
        const ziel = formular.elements[quelle.eingabeName || 'grund'];
        if (ziel) ziel.value = antwort.text;
      }
    }

    formular.dataset.bestaetigt = 'ja';
    formular.requestSubmit(ereignis.submitter || undefined);
  });

  // nach "Zurück" im Browser muss wieder gefragt werden
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-bestaetigt]').forEach(f => delete f.dataset.bestaetigt);
  });

  // Auswahl lädt die passende Seite sofort (Anlass, Filter)
  document.addEventListener('change', ereignis => {
    if (ereignis.target.matches('[data-auto-absenden]')) {
      ereignis.target.form.requestSubmit();
    }
  });

  // Benachrichtigungen, die der Server als Pop-up mitgibt, nacheinander zeigen
  const sofort = [...document.querySelectorAll('dialog[data-sofort]')];
  const naechstes = () => {
    const dialog = sofort.shift();
    if (!dialog) return;
    dialog.addEventListener('close', naechstes);
    dialog.showModal();
  };
  naechstes();
})();
