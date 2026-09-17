(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['al', 'ssl', 'sl']);
  if (!benutzer) return;

  const UNTERTITEL = {
    al: 'Eingereichte Anträge, die Sie abzeichnen.',
    ssl: 'Vom Abteilungsleiter abgezeichnete Anträge, die Sie abzeichnen.',
    sl: 'Vom stellvertretenden Schulleiter abgezeichnete Anträge, die Sie genehmigen.'
  };
  document.getElementById('untertitel').textContent = UNTERTITEL[benutzer.rolle];

  // älteste zuerst, damit nichts liegen bleibt
  const antraege = Daten.offeneFuer(benutzer)
    .sort((a, b) => a.erstellt.localeCompare(b.erstellt));

  const ziel = document.getElementById('liste');

  if (!antraege.length) {
    ziel.innerHTML = '<div class="karte"><p class="leer">Zurzeit liegen keine Anträge vor.</p></div>';
    return;
  }

  ziel.innerHTML = `
    <div class="tabelle-rahmen">
      <table class="tabelle">
        <thead>
          <tr>
            <th scope="col">Nr.</th>
            <th scope="col">Anlass</th>
            <th scope="col">Antragsteller</th>
            <th scope="col">Datum</th>
            <th scope="col">Status</th>
            <th scope="col"><span class="unsichtbar">Aktion</span></th>
          </tr>
        </thead>
        <tbody>${antraege.map(zeile).join('')}</tbody>
      </table>
    </div>`;

  function zeile(antrag) {
    const person = Daten.benutzerNachId(antrag.antragstellerId);
    return `<tr>
      <td class="zahl">${antrag.id}</td>
      <td>${Daten.ANLAESSE[antrag.anlass].titel}</td>
      <td>${App.esc(person.name)}</td>
      <td class="zahl">${App.datum(antrag.erstellt)}</td>
      <td>${App.statusMarke(antrag.status)}</td>
      <td class="rechts"><a class="knopf knopf--klein" href="antrag.html?id=${antrag.id}">Öffnen</a></td>
    </tr>`;
  }
})();
