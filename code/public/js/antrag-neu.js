(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['lehrer']);
  if (!benutzer) return;

  const esc = App.esc;
  const formular = document.getElementById('antragFormular');
  const auswahl = document.getElementById('anlass');
  const abschnitte = document.getElementById('abschnitte');
  const abschluss = document.getElementById('abschluss');

  // vorhandenen Entwurf bearbeiten
  const idParameter = App.parameter('id');
  const entwurf = idParameter ? Daten.antragNachId(idParameter) : null;
  if (idParameter && (!entwurf || entwurf.antragstellerId !== benutzer.id || entwurf.status !== 'entwurf')) {
    App.hinweisMerken('Dieser Antrag kann nicht mehr bearbeitet werden.');
    location.replace('meine-antraege.html');
    return;
  }

  let taetigkeitNr = 0;

  /* ---------- Aufbau ---------- */

  document.getElementById('antragsteller').textContent = `${benutzer.name}${benutzer.amt ? `, ${benutzer.amt}` : ''}`;
  document.getElementById('heute').textContent = App.datum(new Date().toISOString());

  document.getElementById('abteilungen').innerHTML = Daten.ABTEILUNGEN.map(abt => `
    <label class="haken"><input type="radio" name="abteilung" value="${abt}" id="abteilung-${abt}"> ${abt}</label>`).join('');

  auswahl.innerHTML = '<option value="">Bitte wählen …</option>' +
    Object.entries(Daten.ANLAESSE)
      .map(([schluessel, anlass]) => `<option value="${schluessel}">${esc(anlass.titel)}</option>`)
      .join('');

  if (entwurf) {
    document.getElementById('titel').textContent = `Entwurf ${entwurf.id} bearbeiten`;
    document.title = `Entwurf ${entwurf.id} – Antragsverwaltung`;
    if (entwurf.abteilung) document.getElementById(`abteilung-${entwurf.abteilung}`).checked = true;
    auswahl.value = entwurf.anlass || '';
    abschnitteAufbauen(entwurf.daten || {});
    if (entwurf.zurueckweisung) {
      const z = entwurf.zurueckweisung;
      const box = document.getElementById('korrektur');
      box.innerHTML = `<p class="begruendung begruendung--warn"><b>Zur Korrektur zurückgewiesen von ${esc(z.name)}:</b> ${esc(z.grund)}</p>`;
      box.hidden = false;
    }
  }

  // Formular passend zum Anlass aufbauen; bereits eingegebene Werte bleiben erhalten
  auswahl.addEventListener('change', () => {
    App.fehlerEntfernen(auswahl);
    abschnitteAufbauen(datenSammeln());
  });

  function abschnitteAufbauen(werte) {
    const anlass = Daten.ANLAESSE[auswahl.value];
    taetigkeitNr = 0;
    if (!anlass) {
      abschnitte.innerHTML = '';
      abschluss.hidden = true;
      return;
    }
    abschnitte.innerHTML = anlass.abschnitte.map((schluessel, i) => {
      const abschnitt = Daten.ABSCHNITTE[schluessel];
      return `<div class="karte">
        <fieldset>
          <legend>${i + 2}. ${esc(abschnitt.titel)}</legend>
          ${abschnitt.hinweis ? `<p class="leise abstand-unten">${esc(abschnitt.hinweis)}</p>` : ''}
          <div class="formular-raster">${abschnitt.felder.map(feld => feldHtml(feld, werte[feld.name])).join('')}</div>
        </fieldset>
      </div>`;
    }).join('');
    document.getElementById('abschluss-titel').textContent = `${anlass.abschnitte.length + 2}. Anlage und Absenden`;
    abschluss.hidden = false;

    const tabelle = document.getElementById('taetigkeiten');
    if (tabelle) (werte.taetigkeiten || []).forEach(zeile => taetigkeitHinzufuegen(zeile));
  }

  function feldHtml(feld, wert) {
    const id = `feld-${feld.name}`;
    const breit = feld.breit ? ' feld--breit' : '';
    const optional = feld.optional ? ' <span class="leise">(optional)</span>' : '';
    const hilfe = feld.hilfe ? `<small class="feld__hilfe" id="${id}-hilfe">${esc(feld.hilfe)}</small>` : '';
    const beschreibt = feld.hilfe ? ` aria-describedby="${id}-hilfe"` : '';

    switch (feld.typ) {
      case 'checkbox':
        return `<div class="feld${breit}">
          <label class="haken"><input type="checkbox" id="${id}" name="${feld.name}"${wert ? ' checked' : ''}> ${esc(feld.label)}</label>
        </div>`;
      case 'textarea':
        return `<div class="feld${breit}"><label for="${id}">${esc(feld.label)}${optional}</label>
          <textarea id="${id}" name="${feld.name}" rows="3"${beschreibt}>${esc(wert || '')}</textarea>${hilfe}</div>`;
      case 'select': {
        const gruppen = Object.entries(feld.gruppen).map(([gruppe, optionen]) => `<optgroup label="${esc(gruppe)}">
          ${optionen.map(o => `<option${o === wert ? ' selected' : ''}>${esc(o)}</option>`).join('')}</optgroup>`).join('');
        return `<div class="feld${breit}"><label for="${id}">${esc(feld.label)}${optional}</label>
          <select id="${id}" name="${feld.name}"><option value="">Bitte wählen …</option>${gruppen}</select>${hilfe}</div>`;
      }
      case 'taetigkeiten':
        return `<div class="feld feld--breit">
          <div class="tabelle-rahmen">
            <table class="tabelle tabelle--eingabe">
              <thead><tr><th scope="col">Datum</th><th scope="col">U-Std (1.–4. Std.)</th><th scope="col">Anderweitige dienstliche Tätigkeit</th><th scope="col"><span class="unsichtbar">Entfernen</span></th></tr></thead>
              <tbody id="taetigkeiten"></tbody>
            </table>
          </div>
          <div id="feld-taetigkeiten" tabindex="-1" class="knopfzeile knopfzeile--links">
            <button type="button" class="knopf knopf--zweit knopf--klein" id="zeile-neu">+ Zeile hinzufügen</button>
          </div>
        </div>`;
      default:
        return `<div class="feld${breit}"><label for="${id}">${esc(feld.label)}${optional}</label>
          <input id="${id}" name="${feld.name}" type="${feld.typ}" value="${esc(wert || '')}"${beschreibt}>${hilfe}</div>`;
    }
  }

  function taetigkeitHinzufuegen(zeile = {}) {
    const nr = ++taetigkeitNr;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="date" aria-label="Datum" data-spalte="datum" value="${esc(zeile.datum || '')}"></td>
      <td><input type="text" aria-label="U-Std" data-spalte="stunden" placeholder="z. B. 1.–4." value="${esc(zeile.stunden || '')}"></td>
      <td><input type="text" aria-label="Tätigkeit" data-spalte="taetigkeit" value="${esc(zeile.taetigkeit || '')}"></td>
      <td class="rechts"><button type="button" class="knopf knopf--leise knopf--klein" aria-label="Zeile ${nr} entfernen">Entfernen</button></td>`;
    tr.querySelector('button').addEventListener('click', () => tr.remove());
    document.getElementById('taetigkeiten').appendChild(tr);
  }

  abschnitte.addEventListener('click', ereignis => {
    if (ereignis.target.id === 'zeile-neu') taetigkeitHinzufuegen();
  });

  /* ---------- Daten ---------- */

  function datenSammeln() {
    const daten = {};
    const anlass = Daten.ANLAESSE[auswahl.value];
    if (!anlass) return daten;
    for (const feld of Daten.felderVon(auswahl.value)) {
      if (feld.typ === 'taetigkeiten') {
        daten.taetigkeiten = [...document.querySelectorAll('#taetigkeiten tr')].map(tr => {
          const zeile = {};
          tr.querySelectorAll('[data-spalte]').forEach(e => { zeile[e.dataset.spalte] = e.value.trim(); });
          return zeile;
        }).filter(z => z.datum || z.stunden || z.taetigkeit);
        continue;
      }
      const element = formular.elements[feld.name];
      if (!element) continue;
      daten[feld.name] = feld.typ === 'checkbox' ? element.checked : element.value.trim();
    }
    return daten;
  }

  function eingabe() {
    const abteilung = formular.querySelector('input[name="abteilung"]:checked');
    const datei = document.getElementById('feld-anlage').files[0];
    return {
      id: entwurf ? entwurf.id : undefined,
      abteilung: abteilung ? abteilung.value : '',
      anlass: auswahl.value,
      daten: datenSammeln(),
      anlage: datei ? datei.name : ''
    };
  }

  function fehlerZeigen(fehler) {
    formular.querySelectorAll('[aria-invalid="true"]').forEach(App.fehlerEntfernen);
    let erstes = null;
    for (const [name, text] of Object.entries(fehler)) {
      const element = document.getElementById({ abteilung: 'abteilungen', anlass: 'anlass' }[name] || `feld-${name}`);
      if (!element) continue;
      App.fehlerSetzen(element, text);
      if (!erstes) erstes = element;
    }
    if (erstes) {
      if (erstes.id === 'abteilungen') formular.querySelector('input[name="abteilung"]').focus();
      else erstes.focus();
    }
  }

  // Fehlermeldung verschwindet, sobald das Feld geändert wird
  formular.addEventListener('input', ereignis => {
    const feld = ereignis.target.closest('[aria-invalid="true"]') ||
      (ereignis.target.name === 'abteilung' ? document.getElementById('abteilungen') : null);
    if (feld) App.fehlerEntfernen(feld);
  });
  formular.addEventListener('change', ereignis => {
    if (ereignis.target.name === 'abteilung') App.fehlerEntfernen(document.getElementById('abteilungen'));
  });

  /* ---------- Speichern und Absenden ---------- */

  document.getElementById('entwurf').addEventListener('click', () => {
    const antrag = speichern(false);
    if (!antrag) return;
    App.hinweisMerken(`Entwurf ${antrag.id} wurde gespeichert.`);
    location.href = `meine-antraege.html?id=${antrag.id}`;
  });

  formular.addEventListener('submit', async ereignis => {
    ereignis.preventDefault();
    const daten = eingabe();
    const fehler = Daten.pruefen(daten);
    if (Object.keys(fehler).length) {
      fehlerZeigen(fehler);
      return;
    }

    const ok = await App.bestaetigen({
      titel: 'Antrag absenden',
      text: `${Daten.kurzbeschreibung(daten)} wird an die Abteilungsleitung ${daten.abteilung} weitergeleitet. ` +
        'Nach dem Absenden ist keine Änderung mehr möglich.',
      ja: 'Absenden',
      nein: 'Zurück'
    });
    if (!ok) return;

    const antrag = speichern(true);
    if (!antrag) return;
    App.hinweisMerken(`Antrag ${antrag.id} wurde abgesendet. Die Abteilungsleitung ${antrag.abteilung} wurde benachrichtigt.`);
    location.href = `meine-antraege.html?id=${antrag.id}`;
  });

  function speichern(einreichen) {
    try {
      return Daten.antragSpeichern(benutzer, eingabe(), einreichen);
    } catch (fehler) {
      App.hinweisZeigen(fehler.message, 'fehler');
      return null;
    }
  }
})();
