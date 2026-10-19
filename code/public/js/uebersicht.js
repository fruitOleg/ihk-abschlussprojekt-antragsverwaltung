(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['al', 'ssl', 'sl', 'sek']);
  if (!benutzer) return;

  const istSek = Daten.hatRolle(benutzer, 'sek');
  const alleSichtbar = Daten.sichtbarFuer(benutzer);

  document.getElementById('untertitel').textContent = istSek || Daten.istSchulleitung(benutzer)
    ? 'Alle eingereichten Anträge der Schule mit ihrem Bearbeitungsstand.'
    : `Alle Anträge Ihrer Abteilung (${benutzer.abteilungen.join(', ')}) mit ihrem Bearbeitungsstand.`;

  const filter = document.getElementById('filter');
  const f = filter.elements;

  const anzahl = gruppe => alleSichtbar.filter(a => Daten.statusGruppe(a) === gruppe).length;
  f.status.innerHTML = `<option value="">Alle (${alleSichtbar.length})</option>` +
    Object.entries(Daten.STATUS_GRUPPEN)
      .filter(([gruppe]) => gruppe !== 'entwurf')
      .map(([gruppe, text]) => `<option value="${gruppe}">${text} (${anzahl(gruppe)})</option>`)
      .join('');
  f.abteilung.innerHTML = '<option value="">Alle</option>' +
    Daten.ABTEILUNGEN.map(abt => `<option>${abt}</option>`).join('');

  // nur das Sekretariat blendet Anträge aus
  document.getElementById('ausgeblendet-schalter').hidden = !istSek;

  // Filter bleiben beim Zurückkehren erhalten
  const url = new URLSearchParams(location.search);
  for (const name of ['status', 'abteilung', 'suche', 'sortierung']) {
    if (url.get(name)) f[name].value = url.get(name);
  }
  f.ausgeblendet.checked = url.get('ausgeblendet') === '1';

  filter.addEventListener('input', zeigen);
  filter.addEventListener('submit', e => e.preventDefault());
  zeigen();

  function zeigen() {
    const suche = f.suche.value.trim().toLowerCase();
    const sichtbar = alleSichtbar.filter(a => {
      if (a.ausgeblendet && !f.ausgeblendet.checked) return false;
      if (f.status.value && Daten.statusGruppe(a) !== f.status.value) return false;
      if (f.abteilung.value && a.abteilung !== f.abteilung.value) return false;
      if (suche) {
        const text = `${a.id} ${Daten.benutzerNachId(a.antragstellerId).name} ${Daten.kurzbeschreibung(a)}`.toLowerCase();
        if (!text.includes(suche)) return false;
      }
      return true;
    });

    const name = a => Daten.benutzerNachId(a.antragstellerId).name;
    const datum = a => a.eingereicht || a.erstellt;
    const sortierung = {
      neu: (a, b) => datum(b).localeCompare(datum(a)),
      alt: (a, b) => datum(a).localeCompare(datum(b)),
      nr: (a, b) => a.id.localeCompare(b.id),
      name: (a, b) => name(a).localeCompare(name(b), 'de'),
      beginn: (a, b) => (a.daten.beginn_datum || '').localeCompare(b.daten.beginn_datum || '')
    }[f.sortierung.value];
    sichtbar.sort(sortierung);

    const parameter = new URLSearchParams();
    for (const n of ['status', 'abteilung', 'suche']) if (f[n].value) parameter.set(n, f[n].value);
    if (f.sortierung.value !== 'neu') parameter.set('sortierung', f.sortierung.value);
    if (f.ausgeblendet.checked) parameter.set('ausgeblendet', '1');
    history.replaceState(null, '', parameter.toString() ? `?${parameter}` : location.pathname);

    document.getElementById('liste').innerHTML = sichtbar.length
      ? Anzeige.liste(sichtbar, { knopf: 'Ansehen' })
      : '<div class="karte"><p class="leer">Keine Anträge für diese Auswahl.</p></div>';
  }
})();
