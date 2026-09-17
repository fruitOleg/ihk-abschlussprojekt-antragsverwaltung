(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['lehrer']);
  if (!benutzer) return;

  const formular = document.getElementById('antragFormular');
  const auswahl = document.getElementById('anlass');
  const bereich = document.getElementById('angaben');
  const felder = document.getElementById('felder');

  auswahl.innerHTML = '<option value="">Bitte wählen …</option>' +
    Object.entries(Daten.ANLAESSE)
      .map(([schluessel, anlass]) => `<option value="${schluessel}">${anlass.titel}</option>`)
      .join('');

  // Formular passend zum Anlass aufbauen
  auswahl.addEventListener('change', () => {
    App.fehlerEntfernen(auswahl);
    const anlass = Daten.ANLAESSE[auswahl.value];
    if (!anlass) {
      bereich.hidden = true;
      felder.innerHTML = '';
      return;
    }
    felder.innerHTML = anlass.felder.map(feldHtml).join('');
    bereich.hidden = false;
  });

  // Fehlermeldung verschwindet, sobald das Feld geändert wird
  formular.addEventListener('input', ereignis => {
    if (ereignis.target.matches('[aria-invalid="true"]')) App.fehlerEntfernen(ereignis.target);
  });

  formular.addEventListener('submit', async ereignis => {
    ereignis.preventDefault();

    const anlass = Daten.ANLAESSE[auswahl.value];
    if (!anlass) {
      App.fehlerSetzen(auswahl, 'Bitte wählen Sie einen Anlass.');
      auswahl.focus();
      return;
    }

    const erstesFehlerfeld = pruefen(anlass);
    if (erstesFehlerfeld) {
      erstesFehlerfeld.focus();
      return;
    }

    const daten = {};
    for (const feld of anlass.felder) {
      const element = formular.elements[feld.name];
      daten[feld.name] = feld.typ === 'file'
        ? (element.files[0] ? element.files[0].name : '')
        : element.value.trim();
    }

    const ok = await App.bestaetigen({
      titel: 'Antrag absenden',
      text: `Ihr Antrag „${anlass.titel}“ wird an den Abteilungsleiter weitergeleitet. Nach dem Absenden ist keine Änderung mehr möglich.`,
      ja: 'Absenden',
      nein: 'Zurück'
    });
    if (!ok) return;

    // Absenden erzeugt den Log-Eintrag
    const antrag = Daten.antragAnlegen(benutzer, auswahl.value, daten);
    App.hinweisMerken(`Antrag ${antrag.id} wurde abgesendet.`);
    location.href = `meine-antraege.html?id=${antrag.id}`;
  });

  function feldHtml(feld) {
    const id = `feld-${feld.name}`;
    let eingabe;
    if (feld.typ === 'textarea') {
      eingabe = `<textarea id="${id}" name="${feld.name}" rows="3"></textarea>`;
    } else if (feld.typ === 'file') {
      eingabe = `<input id="${id}" name="${feld.name}" type="file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="${id}-hilfe">
        <small class="feld__hilfe" id="${id}-hilfe">PDF, JPG oder PNG, höchstens 5 MB</small>`;
    } else {
      eingabe = `<input id="${id}" name="${feld.name}" type="${feld.typ}">`;
    }
    const breit = feld.typ === 'textarea' ? ' feld--breit' : '';
    return `<div class="feld${breit}"><label for="${id}">${feld.label}</label>${eingabe}</div>`;
  }


  // gibt das erste fehlerhafte Feld zurück oder null
  function pruefen(anlass) {
    let erstes = null;
    const melden = (element, text) => {
      App.fehlerSetzen(element, text);
      if (!erstes) erstes = element;
    };

    for (const feld of anlass.felder) {
      const element = formular.elements[feld.name];
      App.fehlerEntfernen(element);
      if (!feld.optional && !element.value.trim()) melden(element, 'Bitte ausfüllen.');
    }

    const { von, bis } = formular.elements;
    if (von && bis && von.value && bis.value && bis.value < von.value) {
      melden(bis, '„Bis“ liegt vor „Von“.');
    }

    return erstes;
  }
})();
