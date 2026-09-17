(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['al', 'ssl', 'sl']);
  if (!benutzer) return;

  const ziel = document.getElementById('antrag');
  const antrag = Daten.antragNachId(App.parameter('id') || '');

  if (!antrag || !Daten.darfBearbeiten(benutzer, antrag)) {
    ziel.innerHTML = `<div class="karte stapel stapel--eng">
      <h1>Antrag nicht verfügbar</h1>
      <p>Dieser Antrag liegt nicht zur Bearbeitung bei Ihnen.</p>
    </div>`;
    return;
  }

  const anlass = Daten.ANLAESSE[antrag.anlass];
  const antragsteller = Daten.benutzerNachId(antrag.antragstellerId);
  const istSchulleiter = benutzer.rolle === 'sl';

  document.title = `Antrag ${antrag.id} – Antragsverwaltung`;

  ziel.innerHTML = `
    <h1>Antrag ${antrag.id}, ${anlass.titel}</h1>
    <section class="karte">
      <div class="tabelle-rahmen">
        <table class="tabelle tabelle--angaben">
          <tbody>${angaben().map(([titel, wert]) => `<tr><th scope="row">${titel}</th><td>${wert}</td></tr>`).join('')}</tbody>
        </table>
      </div>
      <div class="knopfzeile knopfzeile--links">
        <button type="button" class="knopf" id="bearbeiten">${istSchulleiter ? 'Genehmigen' : 'Abzeichnen'}</button>
      </div>
    </section>`;

  document.getElementById('bearbeiten').addEventListener('click', bearbeiten);

  // Von/Bis werden wie im Wireframe zu "Zeitraum" zusammengefasst
  function angaben() {
    const d = antrag.daten;
    const zeilen = [['Antragsteller', App.esc(antragsteller.name)]];
    for (const feld of anlass.felder) {
      if (feld.name === 'von') {
        zeilen.push(['Zeitraum', `${App.datum(d.von)} bis ${App.datum(d.bis)}`]);
      } else if (feld.name !== 'bis') {
        const roh = d[feld.name];
        let wert;
        if (!roh) wert = '–';
        else if (feld.typ === 'date') wert = App.datum(roh);
        else wert = App.esc(roh);
        zeilen.push([feld.anzeige || feld.label, wert]);
      }
    }
    return zeilen;
  }

  async function bearbeiten() {
    // AL und stellv. Schulleiter: zweifache Abfrage laut Anforderung
    if (!istSchulleiter) {
      const erste = await App.bestaetigen({
        titel: 'Abzeichnen (1 von 2)',
        text: `Möchten Sie den Antrag ${antrag.id} abzeichnen?`,
        ja: 'Weiter'
      });
      if (!erste) return;

      const zweite = await App.bestaetigen({
        titel: 'Abzeichnen (2 von 2)',
        text: `Bitte bestätigen Sie: Antrag ${antrag.id} wird verbindlich abgezeichnet.`,
        ja: 'Bestätigen'
      });
      if (!zweite) return;
    }

    try {
      Daten.antragAbzeichnen(benutzer, antrag.id);
    } catch (fehler) {
      App.hinweisZeigen(fehler.message, 'fehler');
      return;
    }

    App.hinweisMerken(istSchulleiter
      ? `Antrag ${antrag.id} wurde genehmigt.`
      : `Antrag ${antrag.id} wurde abgezeichnet.`);
    location.href = 'eingang.html';
  }
})();
