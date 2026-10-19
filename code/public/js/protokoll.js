(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['sl']);
  if (!benutzer) return;

  const esc = App.esc;
  const filter = document.getElementById('filter');
  const f = filter.elements;

  f.aktion.innerHTML = '<option value="">Alle</option>' +
    Object.entries(Daten.AKTIONEN).map(([schluessel, text]) => `<option value="${schluessel}">${text}</option>`).join('');

  filter.addEventListener('input', zeigen);
  filter.addEventListener('submit', e => e.preventDefault());
  zeigen();

  function gefiltert() {
    const suche = f.suche.value.trim().toLowerCase();
    return Daten.logAlle(benutzer).filter(e => {
      if (f.aktion.value && e.aktion !== f.aktion.value) return false;
      if (!suche) return true;
      return `${e.antragId || ''} ${e.name} ${Daten.AKTIONEN[e.aktion]} ${e.bemerkung}`.toLowerCase().includes(suche);
    }).reverse();
  }

  function zeigen() {
    const eintraege = gefiltert();
    document.getElementById('anzahl').textContent = `${eintraege.length} Einträge, neueste zuerst.`;
    document.getElementById('log').innerHTML = `<div class="tabelle-rahmen">
      <table class="tabelle">
        <thead><tr><th scope="col">Nr.</th><th scope="col">Zeitpunkt</th><th scope="col">Person</th><th scope="col">Aktion</th><th scope="col">Antrag</th><th scope="col">Bemerkung</th></tr></thead>
        <tbody>${eintraege.map(e => `<tr>
          <td class="zahl">${e.nr}</td>
          <td class="zahl">${App.zeitpunkt(e.zeit)}</td>
          <td>${esc(e.name)}</td>
          <td>${Daten.AKTIONEN[e.aktion] || esc(e.aktion)}</td>
          <td>${e.antragId ? `<a href="antrag.html?id=${e.antragId}">${e.antragId}</a>` : '–'}</td>
          <td>${esc(e.bemerkung)}</td>
        </tr>`).join('')}</tbody>
      </table>
    </div>`;
  }

  const zeitstempel = () => new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '');

  document.getElementById('csv').addEventListener('click', () => {
    const feld = wert => `"${String(wert ?? '').replace(/"/g, '""')}"`;
    const zeilen = [['Nr', 'Zeitpunkt', 'Person', 'Aktion', 'Antrag', 'Bemerkung'].map(feld).join(';')];
    for (const e of Daten.logAlle(benutzer)) {
      zeilen.push([e.nr, e.zeit, e.name, Daten.AKTIONEN[e.aktion] || e.aktion, e.antragId, e.bemerkung].map(feld).join(';'));
    }
    // BOM, damit Excel die Umlaute richtig liest
    App.herunterladen(`logdatei-${zeitstempel()}.csv`, '﻿' + zeilen.join('\r\n'), 'text/csv;charset=utf-8');
  });

  document.getElementById('drucken').addEventListener('click', () => window.print());

  document.getElementById('export').addEventListener('click', async () => {
    const ok = await App.bestaetigen({
      titel: 'Datensicherung erstellen',
      text: 'Alle Antragsdaten und die Logdatei werden als JSON-Datei heruntergeladen. Der Export wird im Log vermerkt.',
      ja: 'Herunterladen'
    });
    if (!ok) return;
    const daten = Daten.export(benutzer);
    App.herunterladen(`datensicherung-${zeitstempel()}.json`, JSON.stringify(daten, null, 2), 'application/json');
    App.hinweisZeigen(`Datensicherung mit ${daten.antraege.length} Anträgen und ${daten.log.length} Log-Einträgen wurde erstellt.`);
    zeigen();
  });
})();
