(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['lehrer']);
  if (!benutzer) return;

  const STUFEN = {
    eingereicht:      'Eingereicht',
    abgezeichnet_al:  'Abgezeichnet vom AL',
    abgezeichnet_ssl: 'Abgezeichnet vom stellv. Schulleiter',
    genehmigt:        'Genehmigt vom Schulleiter'
  };

  const antraege = Daten.antraegeVon(benutzer)
    .sort((a, b) => b.erstellt.localeCompare(a.erstellt));

  const liste = document.getElementById('liste');
  const stand = document.getElementById('stand');

  if (!antraege.length) {
    liste.innerHTML = `<div class="karte"><p class="leer">Sie haben noch keine Anträge gestellt.
      <a href="antrag-neu.html">Antrag stellen</a></p></div>`;
    return;
  }

  // Stand wird für den gewählten Antrag angezeigt, sonst für den neuesten
  const gewaehlt = antraege.find(a => a.id === App.parameter('id')) || antraege[0];

  liste.innerHTML = `
    <div class="tabelle-rahmen">
      <table class="tabelle">
        <thead>
          <tr>
            <th scope="col">Nr.</th>
            <th scope="col">Anlass</th>
            <th scope="col">Eingereicht</th>
            <th scope="col">Stand</th>
          </tr>
        </thead>
        <tbody>${antraege.map(zeile).join('')}</tbody>
      </table>
    </div>`;

  const log = Daten.logZu(gewaehlt.id);
  const schritte = Daten.REIHENFOLGE.map(status => {
    const eintrag = log.find(e => e.aktion === status);
    return eintrag
      ? `<li class="erledigt">${STUFEN[status]}<small>${App.datum(eintrag.zeit)}</small></li>`
      : `<li>${STUFEN[status]}<small>offen</small></li>`;
  }).join('');

  const loeschbar = Daten.darfLoeschen(benutzer, gewaehlt);

  stand.innerHTML = `
    <h2 id="stand-titel">Stand zu ${gewaehlt.id}</h2>
    <ol class="stand">${schritte}</ol>
    ${loeschbar ? `<div class="knopfzeile knopfzeile--links">
      <button type="button" class="knopf knopf--gefahr-zweit" id="loeschen">Antrag löschen</button>
    </div>` : ''}`;
  stand.hidden = false;

  if (loeschbar) {
    document.getElementById('loeschen').addEventListener('click', loeschen);
  }

  async function loeschen() {
    const ok = await App.bestaetigen({
      titel: 'Antrag löschen',
      text: `Antrag ${gewaehlt.id} wird gelöscht und nicht weiter bearbeitet. Das kann nicht rückgängig gemacht werden.`,
      ja: 'Löschen',
      nein: 'Zurück',
      gefahr: true
    });
    if (!ok) return;

    try {
      Daten.antragLoeschen(benutzer, gewaehlt.id);
    } catch (fehler) {
      App.hinweisZeigen(fehler.message, 'fehler');
      return;
    }

    App.hinweisMerken(`Antrag ${gewaehlt.id} wurde gelöscht.`);
    location.href = 'meine-antraege.html';
  }

  function zeile(antrag) {
    const aktiv = antrag === gewaehlt;
    return `<tr${aktiv ? ' class="gewaehlt"' : ''}>
      <td class="zahl"><a href="meine-antraege.html?id=${antrag.id}"${aktiv ? ' aria-current="true"' : ''}>${antrag.id}</a></td>
      <td>${Daten.ANLAESSE[antrag.anlass].titel}</td>
      <td class="zahl">${App.datum(antrag.erstellt)}</td>
      <td>${App.statusMarke(antrag.status)}</td>
    </tr>`;
  }
})();
