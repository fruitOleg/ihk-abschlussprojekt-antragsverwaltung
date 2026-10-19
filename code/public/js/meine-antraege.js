(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['lehrer']);
  if (!benutzer) return;

  const esc = App.esc;

  // Entwürfe zuerst, dann die neuesten
  const antraege = Daten.antraegeVon(benutzer).sort((a, b) =>
    (a.status === 'entwurf' ? 0 : 1) - (b.status === 'entwurf' ? 0 : 1) ||
    (b.eingereicht || b.erstellt).localeCompare(a.eingereicht || a.erstellt));

  const liste = document.getElementById('liste');
  const stand = document.getElementById('stand');

  if (!antraege.length) {
    liste.innerHTML = `<div class="karte"><p class="leer">Sie haben noch keine Anträge gestellt.
      <a href="antrag-neu.html">Antrag stellen</a></p></div>`;
    return;
  }

  // Stand wird für den gewählten Antrag angezeigt, sonst für den obersten
  const gewaehlt = antraege.find(a => a.id === App.parameter('id')) || antraege[0];

  liste.innerHTML = `
    <div class="tabelle-rahmen">
      <table class="tabelle">
        <thead>
          <tr>
            <th scope="col">Nr.</th>
            <th scope="col">Anlass</th>
            <th scope="col">Abt.</th>
            <th scope="col">Datum</th>
            <th scope="col">Stand</th>
          </tr>
        </thead>
        <tbody>${antraege.map(zeile).join('')}</tbody>
      </table>
    </div>`;

  const knoepfe = [];
  if (gewaehlt.status === 'entwurf') {
    knoepfe.push(`<a class="knopf" href="antrag-neu.html?id=${gewaehlt.id}">${gewaehlt.zurueckweisung ? 'Korrigieren und erneut absenden' : 'Entwurf bearbeiten'}</a>`);
  } else {
    knoepfe.push(`<a class="knopf knopf--zweit" href="antrag.html?id=${gewaehlt.id}">Details und Verlauf</a>`);
  }
  if (Daten.darfEntwurfLoeschen(benutzer, gewaehlt)) {
    knoepfe.push('<button type="button" class="knopf knopf--gefahr-zweit" id="loeschen">Entwurf löschen</button>');
  }
  if (Daten.darfZurueckziehen(benutzer, gewaehlt)) {
    knoepfe.push('<button type="button" class="knopf knopf--gefahr-zweit" id="zurueckziehen">Antrag zurückziehen</button>');
  }

  stand.innerHTML = `
    <h2 id="stand-titel">Stand zu ${gewaehlt.id}: ${esc(Daten.kurzbeschreibung(gewaehlt))}</h2>
    ${gewaehlt.status === 'entwurf' && !gewaehlt.zurueckweisung
      ? '<p class="leise">Dieser Entwurf ist nur für Sie sichtbar und wurde noch nicht abgesendet.</p>'
      : Anzeige.stand(gewaehlt)}
    <div class="knopfzeile knopfzeile--links">${knoepfe.join('')}</div>`;
  stand.hidden = false;

  const loeschen = document.getElementById('loeschen');
  if (loeschen) {
    loeschen.addEventListener('click', async () => {
      const ok = await App.bestaetigen({
        titel: 'Entwurf löschen',
        text: `Der Entwurf ${gewaehlt.id} wird endgültig gelöscht.`,
        ja: 'Löschen', nein: 'Zurück', gefahr: true
      });
      if (!ok) return;
      ausfuehren(() => Daten.entwurfLoeschen(benutzer, gewaehlt.id), `Entwurf ${gewaehlt.id} wurde gelöscht.`, 'meine-antraege.html');
    });
  }

  const zurueckziehen = document.getElementById('zurueckziehen');
  if (zurueckziehen) {
    zurueckziehen.addEventListener('click', async () => {
      const grund = await App.bestaetigen({
        titel: 'Antrag zurückziehen',
        text: `Antrag ${gewaehlt.id} wird storniert. Die Abteilungsleitung ${gewaehlt.abteilung} wird informiert und muss die ` +
          'Stornierung abzeichnen. Der Antrag bleibt archiviert und kann weiter eingesehen werden.',
        eingabe: 'Grund', pflicht: false,
        ja: 'Zurückziehen', nein: 'Abbrechen', gefahr: true
      });
      if (grund === false) return;
      ausfuehren(() => Daten.antragZurueckziehen(benutzer, gewaehlt.id, grund),
        `Antrag ${gewaehlt.id} wurde zurückgezogen. Die Abteilungsleitung wurde informiert.`, `meine-antraege.html?id=${gewaehlt.id}`);
    });
  }

  function ausfuehren(aktion, meldung, ziel) {
    try {
      aktion();
    } catch (fehler) {
      App.hinweisZeigen(fehler.message, 'fehler');
      return;
    }
    App.hinweisMerken(meldung);
    location.href = ziel;
  }

  function zeile(antrag) {
    const aktiv = antrag === gewaehlt;
    return `<tr${aktiv ? ' class="gewaehlt"' : ''}>
      <td class="zahl"><a href="meine-antraege.html?id=${antrag.id}"${aktiv ? ' aria-current="true"' : ''}>${antrag.id}</a></td>
      <td>${esc(Daten.kurzbeschreibung(antrag))}</td>
      <td>${esc(antrag.abteilung || '–')}</td>
      <td class="zahl">${App.datum(antrag.eingereicht || antrag.erstellt)}</td>
      <td>${App.statusMarke(antrag)}</td>
    </tr>`;
  }
})();
