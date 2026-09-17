/*
 * Datenhaltung des Prototyps.
 *
 * Solange es noch keinen Server gibt, liegen Anträge und Log-Einträge im
 * localStorage des Browsers. Die öffentlichen Funktionen am Ende entsprechen
 * später PHP-Funktionen, die dasselbe mit MySQL erledigen.
 */
const Daten = (() => {
  'use strict';

  const SPEICHER = 'antragsverwaltung-demo-v3';

  const ROLLEN = {
    lehrer: 'Lehrer',
    al: 'Abteilungsleiter',
    ssl: 'stellv. Schulleiter',
    sl: 'Schulleiter'
  };

  const BENUTZER = [
    { id: 1, login: 'keller', name: 'M. Keller',  rolle: 'lehrer' },
    { id: 2, login: 'brandt', name: 'P. Brandt',  rolle: 'lehrer' },
    { id: 3, login: 'vogt',   name: 'S. Vogt',    rolle: 'al' },
    { id: 4, login: 'roth',   name: 'K. Roth',    rolle: 'ssl' },
    { id: 5, login: 'wenzel', name: 'Dr. Wenzel', rolle: 'sl' }
  ];

  // Genehmigungsweg in dieser Reihenfolge; zustaendig = wer den Antrag als Nächstes bearbeitet
  const STATUS = {
    eingereicht:      { text: 'eingereicht',                          zustaendig: 'al' },
    abgezeichnet_al:  { text: 'abgezeichnet vom AL',                  zustaendig: 'ssl' },
    abgezeichnet_ssl: { text: 'abgezeichnet vom stellv. Schulleiter', zustaendig: 'sl' },
    genehmigt:        { text: 'genehmigt vom Schulleiter',            zustaendig: null }
  };

  const REIHENFOLGE = Object.keys(STATUS);

  const FOLGESTATUS = { al: 'abgezeichnet_al', ssl: 'abgezeichnet_ssl', sl: 'genehmigt' };

  const KLASSE = { name: 'klasse', label: 'Klasse', typ: 'text' };
  const ZIEL   = { name: 'ziel',   label: 'Ziel',   typ: 'text' };
  const VON    = { name: 'von',    label: 'Von',    typ: 'date' };
  const BIS    = { name: 'bis',    label: 'Bis',    typ: 'date' };

  // bei jedem Anlass möglich; im Prototyp wird nur der Dateiname gespeichert
  const ANLAGE = { name: 'anlage', label: 'Anlage (optional)', anzeige: 'Anlage', typ: 'file', optional: true };

  // Je nach Anlass wird ein anderes Formular angezeigt
  const ANLAESSE = {
    fortbildung: {
      titel: 'Fortbildung',
      felder: [{ name: 'thema', label: 'Thema', typ: 'text' }, { name: 'ort', label: 'Ort', typ: 'text' }, VON, BIS, ANLAGE]
    },
    klassenfahrt: {
      titel: 'Klassenfahrt',
      felder: [KLASSE, ZIEL, VON, BIS, { name: 'begleitperson', label: 'Begleitperson', typ: 'text' }, ANLAGE]
    },
    exkursion: {
      titel: 'Exkursion',
      felder: [KLASSE, ZIEL, { name: 'datum', label: 'Datum', typ: 'date' }, ANLAGE]
    },
    sonstiges: {
      titel: 'Sonstiges',
      felder: [{ name: 'beschreibung', label: 'Beschreibung', typ: 'textarea' }, VON, BIS, ANLAGE]
    }
  };

  /* ---------- Speicher ---------- */

  function laden() {
    try {
      const roh = localStorage.getItem(SPEICHER);
      if (roh) return JSON.parse(roh);
    } catch (e) {
      // gesperrter oder beschädigter Speicher: mit Beispieldaten weiterarbeiten
    }
    const neu = beispieldaten();
    speichern(neu);
    return neu;
  }

  function speichern(stand) {
    try {
      localStorage.setItem(SPEICHER, JSON.stringify(stand));
    } catch (e) {
      // ohne Speicher gehen Änderungen beim Seitenwechsel verloren
    }
  }

  // Log-Eintrag: wer hat wann was mit welchem Antrag gemacht
  function logEintrag(stand, benutzer, aktion, antragId, zeit) {
    stand.log.push({
      nr: stand.log.length + 1,
      zeit: zeit || new Date().toISOString(),
      benutzerId: benutzer.id,
      aktion,
      antragId
    });
  }

  /* ---------- Beispieldaten ---------- */

  function vorTagen(tage, stunde, minute) {
    const d = new Date();
    d.setDate(d.getDate() - tage);
    d.setHours(stunde, minute, 0, 0);
    return d.toISOString();
  }

  function datumIn(tage) {
    const d = new Date();
    d.setDate(d.getDate() + tage);
    const monat = String(d.getMonth() + 1).padStart(2, '0');
    const tag = String(d.getDate()).padStart(2, '0');
    return `${d.getFullYear()}-${monat}-${tag}`;
  }

  function beispieldaten() {
    const stand = { naechsteNr: 242, antraege: [], log: [] };

    // schritte: [login, aktion, zeitpunkt]
    const anlegen = (nr, login, anlass, daten, schritte) => {
      const antrag = {
        id: 'A-' + nr,
        anlass,
        antragstellerId: benutzerNachLogin(login).id,
        daten,
        status: 'eingereicht',
        erstellt: schritte[0][2]
      };
      stand.antraege.push(antrag);
      for (const [wer, aktion, zeit] of schritte) {
        antrag.status = aktion;
        logEintrag(stand, benutzerNachLogin(wer), aktion, antrag.id, zeit);
      }
    };

    anlegen(238, 'brandt', 'fortbildung', { thema: 'Prüfungsvorbereitung', ort: 'Köln', von: datumIn(8), bis: datumIn(8), anlage: '' }, [
      ['brandt', 'eingereicht', vorTagen(9, 8, 10)],
      ['vogt', 'abgezeichnet_al', vorTagen(8, 11, 40)],
      ['roth', 'abgezeichnet_ssl', vorTagen(7, 9, 5)],
      ['wenzel', 'genehmigt', vorTagen(6, 14, 30)]
    ]);

    anlegen(239, 'keller', 'exkursion', { klasse: '10a', ziel: 'Landtag', datum: datumIn(5), anlage: '' }, [
      ['keller', 'eingereicht', vorTagen(5, 7, 50)],
      ['vogt', 'abgezeichnet_al', vorTagen(4, 12, 15)],
      ['roth', 'abgezeichnet_ssl', vorTagen(3, 9, 30)]
    ]);

    anlegen(240, 'brandt', 'klassenfahrt', {
      klasse: '9c', ziel: 'Berlin', von: datumIn(20), bis: datumIn(23), begleitperson: 'L. Sommer', anlage: ''
    }, [
      ['brandt', 'eingereicht', vorTagen(3, 10, 5)],
      ['vogt', 'abgezeichnet_al', vorTagen(2, 15, 20)]
    ]);

    anlegen(241, 'keller', 'klassenfahrt', {
      klasse: '10b', ziel: 'Weimar', von: datumIn(30), bis: datumIn(33), begleitperson: 'R. Ahmadi', anlage: 'Programm_Weimar.pdf'
    }, [
      ['keller', 'eingereicht', vorTagen(1, 16, 45)]
    ]);

    return stand;
  }

  /* ---------- Benutzer ---------- */

  function benutzerNachId(id) {
    return BENUTZER.find(b => b.id === id) || null;
  }

  function benutzerNachLogin(login) {
    const gesucht = String(login).trim().toLowerCase();
    return BENUTZER.find(b => b.login === gesucht) || null;
  }

  // Bearbeiten darf nur, wer im aktuellen Status als Nächstes zuständig ist
  function darfBearbeiten(benutzer, antrag) {
    return STATUS[antrag.status].zustaendig === benutzer.rolle;
  }

  /* ---------- Abfragen ---------- */

  function antragNachId(id) {
    return laden().antraege.find(a => a.id === id) || null;
  }

  function antraegeVon(benutzer) {
    return laden().antraege.filter(a => a.antragstellerId === benutzer.id);
  }

  function offeneFuer(benutzer) {
    return laden().antraege.filter(a => darfBearbeiten(benutzer, a));
  }

  function logZu(antragId) {
    return laden().log.filter(e => e.antragId === antragId);
  }

  /* ---------- Aktionen (erzeugen jeweils einen Log-Eintrag) ---------- */

  function antragAnlegen(benutzer, anlass, daten) {
    if (benutzer.rolle !== 'lehrer') throw new Error('Nur Lehrer können Anträge stellen.');
    if (!ANLAESSE[anlass]) throw new Error('Unbekannter Anlass.');

    const stand = laden();
    const antrag = {
      id: 'A-' + stand.naechsteNr,
      anlass,
      antragstellerId: benutzer.id,
      daten,
      status: 'eingereicht',
      erstellt: new Date().toISOString()
    };
    stand.naechsteNr += 1;
    stand.antraege.push(antrag);
    logEintrag(stand, benutzer, 'eingereicht', antrag.id);
    speichern(stand);
    return antrag;
  }

  // Abzeichnen (AL, stellv. SL) bzw. Genehmigen (SL)
  function antragAbzeichnen(benutzer, antragId) {
    const stand = laden();
    const antrag = stand.antraege.find(a => a.id === antragId);
    if (!antrag || !darfBearbeiten(benutzer, antrag)) {
      throw new Error('Dieser Antrag liegt nicht zur Bearbeitung bei Ihnen.');
    }
    antrag.status = FOLGESTATUS[benutzer.rolle];
    logEintrag(stand, benutzer, antrag.status, antrag.id);
    speichern(stand);
    return antrag;
  }

  // Antragsteller darf seinen Antrag löschen, solange er nicht genehmigt ist
  function darfLoeschen(benutzer, antrag) {
    return antrag.antragstellerId === benutzer.id && antrag.status !== 'genehmigt';
  }

  function antragLoeschen(benutzer, antragId) {
    const stand = laden();
    const index = stand.antraege.findIndex(a => a.id === antragId);
    const antrag = stand.antraege[index];
    if (!antrag || !darfLoeschen(benutzer, antrag)) {
      throw new Error('Dieser Antrag kann nicht mehr gelöscht werden.');
    }
    stand.antraege.splice(index, 1);
    // der Log-Eintrag bleibt auch nach dem Löschen erhalten
    logEintrag(stand, benutzer, 'geloescht', antrag.id);
    speichern(stand);
  }

  function zuruecksetzen() {
    speichern(beispieldaten());
  }

  return {
    ROLLEN, BENUTZER, STATUS, REIHENFOLGE, ANLAESSE,
    benutzerNachId, benutzerNachLogin, darfBearbeiten, darfLoeschen,
    antragNachId, antraegeVon, offeneFuer, logZu,
    antragAnlegen, antragAbzeichnen, antragLoeschen, zuruecksetzen
  };
})();
