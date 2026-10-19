/*
 * Darstellung eines Antrags, die auf mehreren Seiten gebraucht wird:
 * Angaben aus dem Formular, Bearbeitungsstand und Verlauf (Log).
 */
const Anzeige = (() => {
  'use strict';

  const esc = App.esc;

  function wert(feld, roh) {
    if (feld.typ === 'checkbox') return roh ? 'ja' : 'nein';
    if (roh === undefined || roh === null || String(roh).trim() === '') return '–';
    if (feld.typ === 'date') return App.datum(roh);
    if (feld.typ === 'time') return `${esc(roh)} Uhr`;
    return esc(roh).replace(/\n/g, '<br>');
  }

  function zeitraum(d) {
    const teil = (datum, zeit) => datum ? `${App.datum(datum)}${zeit ? `, ${esc(zeit)} Uhr` : ''}` : '–';
    return `${teil(d.beginn_datum, d.beginn_zeit)} bis ${teil(d.ende_datum, d.ende_zeit)}`;
  }

  function tabelle(zeilen) {
    return `<div class="tabelle-rahmen">
      <table class="tabelle tabelle--angaben">
        <tbody>${zeilen.map(([titel, inhalt]) => `<tr><th scope="row">${titel}</th><td>${inhalt}</td></tr>`).join('')}</tbody>
      </table>
    </div>`;
  }

  function taetigkeiten(liste) {
    if (!liste || !liste.length) return '<p class="leise">Keine Einträge.</p>';
    return `<div class="tabelle-rahmen">
      <table class="tabelle">
        <thead><tr><th scope="col">Datum</th><th scope="col">U-Std</th><th scope="col">Anderweitige dienstliche Tätigkeit</th></tr></thead>
        <tbody>${liste.map(z => `<tr><td class="zahl">${App.datum(z.datum)}</td><td>${esc(z.stunden)}</td><td>${esc(z.taetigkeit)}</td></tr>`).join('')}</tbody>
      </table>
    </div>`;
  }

  // Kopfdaten und je Formularabschnitt eine Tabelle
  function angaben(antrag) {
    const person = Daten.benutzerNachId(antrag.antragstellerId);
    const anlass = Daten.ANLAESSE[antrag.anlass];
    const kopf = [
      ['Antragsteller', `${esc(person.name)}${person.amt ? `, ${esc(person.amt)}` : ''}`],
      ['Abteilung', esc(antrag.abteilung || '–')],
      ['Anlass', anlass ? esc(anlass.titel) : '–'],
      ['Eingereicht', antrag.eingereicht ? App.zeitpunkt(antrag.eingereicht) : 'noch nicht'],
      ['Status', App.statusMarke(antrag)]
    ];
    if (antrag.status !== 'entwurf' && antrag.status !== 'eingereicht') {
      kopf.push(['Feld „K“ (Untis)', antrag.k ? '<b>gesetzt</b> – ganztägige Abwesenheit' : 'nicht gesetzt']);
    }
    kopf.push(['Anlage', antrag.anlage ? `<span class="link-text">${esc(antrag.anlage)}</span>` : '–']);

    let html = `<section class="karte"><h2>Antrag</h2>${tabelle(kopf)}</section>`;
    if (!anlass) return html;

    const d = antrag.daten || {};
    for (const schluessel of anlass.abschnitte) {
      const abschnitt = Daten.ABSCHNITTE[schluessel];
      let inhalt;
      if (schluessel === 'taetigkeit') {
        inhalt = taetigkeiten(d.taetigkeiten);
      } else {
        const zeilen = [];
        for (const feld of abschnitt.felder) {
          if (feld.name === 'beginn_datum') zeilen.push(['Zeitraum', zeitraum(d)]);
          else if (!feld.name.startsWith('beginn_') && !feld.name.startsWith('ende_')) zeilen.push([esc(feld.label), wert(feld, d[feld.name])]);
        }
        inhalt = tabelle(zeilen);
      }
      html += `<section class="karte"><h2>${esc(abschnitt.titel)}</h2>${inhalt}</section>`;
    }
    return html;
  }

  // Bearbeitungsstand mit Hinweisen auf Zurückweisung, Ablehnung oder Stornierung
  function stand(antrag) {
    const schritte = Daten.stufen(antrag).map(s => {
      const klasse = s.zustand === 'offen' ? '' : ` class="${s.zustand}"`;
      let zusatz = 'offen';
      if (s.zeit) zusatz = `${App.datum(s.zeit)} · ${esc(s.name)}`;
      else if (s.zustand === 'entfaellt') zusatz = 'entfällt';
      return `<li${klasse}>${s.text}<small>${zusatz}</small></li>`;
    }).join('');

    let hinweise = '';
    if (antrag.status === 'entwurf' && antrag.zurueckweisung) {
      const z = antrag.zurueckweisung;
      hinweise += `<p class="begruendung begruendung--warn"><b>Zur Korrektur zurückgewiesen von ${esc(z.name)} am ${App.datum(z.zeit)}:</b> ${esc(z.grund)}</p>`;
    }
    if (antrag.status === 'abgelehnt') {
      const a = antrag.ablehnung;
      hinweise += `<p class="begruendung"><b>Abgelehnt von ${esc(a.name)} am ${App.datum(a.zeit)}:</b> ${esc(a.grund)}</p>`;
    }
    if (antrag.status === 'storniert') {
      const s = antrag.stornierung;
      hinweise += `<p class="begruendung"><b>Zurückgezogen am ${App.datum(s.zeit)}</b> (vorher: ${esc(s.vorher)}).
        ${s.grund ? `Grund: ${esc(s.grund)}<br>` : ''}Der Antrag bleibt archiviert und kann weiterhin eingesehen werden.</p>`;
    }
    if ((antrag.status === 'abgelehnt' || antrag.status === 'storniert')) {
      hinweise += `<p class="leise">${antrag.alAbgezeichnet ? 'Der Abteilungsleiter hat den Vorgang abgezeichnet.' : 'Der Abteilungsleiter wurde informiert und muss den Vorgang noch abzeichnen.'}</p>`;
    }

    return `<ol class="stand stand--fuenf">${schritte}</ol>${hinweise}`;
  }

  // Log-Einträge zu einem Antrag
  function verlauf(antragId) {
    const zeilen = Daten.logZu(antragId).map(e => `<tr>
        <td class="zahl">${App.zeitpunkt(e.zeit)}</td>
        <td>${esc(e.name)}</td>
        <td>${Daten.AKTIONEN[e.aktion] || esc(e.aktion)}</td>
        <td>${esc(e.bemerkung)}</td>
      </tr>`).join('');
    if (!zeilen) return '<p class="leise">Noch keine Einträge.</p>';
    return `<div class="tabelle-rahmen">
      <table class="tabelle">
        <thead><tr><th scope="col">Zeitpunkt</th><th scope="col">Person</th><th scope="col">Aktion</th><th scope="col">Bemerkung</th></tr></thead>
        <tbody>${zeilen}</tbody>
      </table>
    </div>`;
  }

  // Tabellenzeilen für Antragslisten
  function listenZeile(antrag, { aufgabe = false, knopf = 'Öffnen', ziel } = {}) {
    const person = Daten.benutzerNachId(antrag.antragstellerId);
    const benutzer = App.aktuellerBenutzer();
    const aufgabeSchluessel = aufgabe ? Daten.aufgabe(benutzer, antrag) : null;
    const vertretung = aufgabeSchluessel ? Daten.vertretungsHinweis(benutzer, aufgabeSchluessel) : '';
    return `<tr${antrag.ausgeblendet ? ' class="ausgeblendet"' : ''}>
      <td class="zahl">${antrag.id}</td>
      <td>${esc(Daten.kurzbeschreibung(antrag))}</td>
      <td>${esc(person.name)}</td>
      <td>${esc(antrag.abteilung)}</td>
      <td class="zahl">${App.datum(antrag.eingereicht || antrag.erstellt)}</td>
      <td>${aufgabeSchluessel
        ? `<span class="aufgabe">${Daten.AUFGABEN[aufgabeSchluessel]}</span>${vertretung ? `<small class="leise block">${vertretung}</small>` : ''}`
        : App.statusMarke(antrag)}${antrag.k ? ' <span class="marke-k" title="Feld K gesetzt: ganztägige Abwesenheit">K</span>' : ''}</td>
      <td class="rechts"><a class="knopf ${aufgabe ? '' : 'knopf--zweit '}knopf--klein" href="${ziel || `antrag.html?id=${antrag.id}`}">${knopf}</a></td>
    </tr>`;
  }

  function liste(antraege, optionen = {}) {
    return `<div class="tabelle-rahmen">
      <table class="tabelle">
        <thead>
          <tr>
            <th scope="col">Nr.</th>
            <th scope="col">Anlass</th>
            <th scope="col">Antragsteller</th>
            <th scope="col">Abt.</th>
            <th scope="col">Datum</th>
            <th scope="col">${optionen.aufgabe ? 'Aufgabe' : 'Status'}</th>
            <th scope="col"><span class="unsichtbar">Aktion</span></th>
          </tr>
        </thead>
        <tbody>${antraege.map(a => listenZeile(a, optionen)).join('')}</tbody>
      </table>
    </div>`;
  }

  return { angaben, stand, verlauf, liste };
})();
