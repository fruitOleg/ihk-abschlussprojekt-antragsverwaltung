/*
 * Datenhaltung und Geschäftslogik des Prototyps.
 *
 * Solange es noch keinen Server gibt, liegen Benutzer, Anträge, Log und
 * Benachrichtigungen im localStorage des Browsers. Die öffentlichen Funktionen
 * am Ende entsprechen später PHP-Funktionen, die dasselbe mit MySQL erledigen.
 *
 * Ablauf laut UML-Zustandsdiagramm:
 *   Entwurf -> eingereicht -> abgezeichnet (AL) -> abgezeichnet (stellv. SL) -> genehmigt (SL) -> Sekretariat
 *   AL kann zur Korrektur zurückweisen (zurück zum Entwurf) oder in eine andere Abteilung umleiten.
 *   SL lehnt ab oder Lehrer zieht zurück -> AL zeichnet ab -> Sekretariat.
 *   Das Sekretariat zeichnet am Ende jeden Antrag ab und kann ihn danach aus- und einblenden.
 */
const Daten = (() => {
  'use strict';

  const SPEICHER = 'antragsverwaltung-demo-v7';

  const ROLLEN = {
    lehrer: 'Lehrer',
    al: 'Abteilungsleiter',
    ssl: 'stellv. Schulleiter',
    sl: 'Schulleiter',
    sek: 'Sekretariat',
    admin: 'Administrator'
  };

  const ABTEILUNGEN = ['EMT', 'IPT', 'BBWQ'];

  /* ---------- Formular "Änderung der Unterrichtsorganisation" ---------- */

  const ZEITRAUM = [
    { name: 'beginn_datum', label: 'Beginn – Datum',  typ: 'date' },
    { name: 'beginn_zeit',  label: 'Beginn – Uhrzeit', typ: 'time' },
    { name: 'ende_datum',   label: 'Ende – Datum',    typ: 'date' },
    { name: 'ende_zeit',    label: 'Ende – Uhrzeit',  typ: 'time' }
  ];

  const VERANSTALTUNGSARTEN = {
    'Schulveranstaltung an außerschulischem Lernort (Schulfahrtenerlass)':
      ['Unterrichtsgang', 'Schulfahrt', 'Internationale Veranstaltung', 'Fahrt aus besonderem Anlass'],
    'Sonstige Veranstaltung':
      ['Projekt', 'Internationale Veranstaltung (sonstige)', 'Sonstiges (z. B. Prüfung)']
  };

  // Abschnitte des Papierformulars; "Vor-/Nachholen/Tausch" entfällt laut Besprechung vom 17.09.26
  const ABSCHNITTE = {
    lehrkraft: {
      titel: 'Abwesenheit der Lehrkraft',
      felder: [
        { name: 'grund', label: 'Grund der Verhinderung', typ: 'textarea', breit: true,
          hilfe: 'Bitte ggf. Anordnung des Dienstgeschäftes bzw. Einladungsschreiben als Anlage beifügen.' },
        ...ZEITRAUM,
        { name: 'material_plattform', label: 'Vertretungsmaterial auf Lernplattform', typ: 'checkbox' },
        { name: 'material_mail', label: 'Vertretungsmaterial nach Absprache per Mail', typ: 'checkbox' }
      ]
    },
    klasse: {
      titel: 'Abwesenheit der Klasse',
      felder: [
        { name: 'klasse', label: 'Klasse/Klassenteil', typ: 'text' },
        { name: 'grund', label: 'Grund (z. B. Prüfung, ÜLU)', typ: 'text' },
        ...ZEITRAUM,
        { name: 'info_freigesetzt', label: 'Info an freigesetzte Lehrkräfte (per E-Mail oder persönlich durch Klassenlehrer/in)', typ: 'checkbox', breit: true }
      ]
    },
    veranstaltung: {
      titel: 'Abwesenheit von Klasse(n) und Lehrkraft',
      felder: [
        { name: 'art', label: 'Art der Veranstaltung', typ: 'select', gruppen: VERANSTALTUNGSARTEN },
        { name: 'ziel', label: 'Ziel bzw. Thema/Ort', typ: 'text' },
        { name: 'klassen', label: 'Teilnehmende Klasse(n)/Klassenteil(e)', typ: 'text' },
        { name: 'lehrkraefte', label: 'Zusätzlich teilnehmende Lehrkräfte', typ: 'text', optional: true },
        ...ZEITRAUM,
        { name: 'verkehrsmittel', label: 'Verkehrsmittel', typ: 'text', optional: true },
        { name: 'info_freigesetzt', label: 'Info an freigesetzte Lehrkräfte (per E-Mail oder persönlich durch antragstellende Lehrkraft)', typ: 'checkbox', breit: true }
      ]
    },
    taetigkeit: {
      titel: 'Anderweitige dienstliche Tätigkeit bei Abwesenheit der SuS',
      hinweis: 'Die anderweitige dienstliche Tätigkeit ist mit der Abteilungsleitung zu besprechen. Vertretungen haben grundsätzlich Vorrang.',
      felder: [
        { name: 'taetigkeiten', label: 'Tätigkeiten', typ: 'taetigkeiten', optional: true, breit: true }
      ]
    }
  };

  // Punkt 1 des Formulars; "Unterrichtstausch" entfällt laut Kundentermin vom 17.09.26
  const ANLAESSE = {
    dienstlich:      { titel: 'Unterrichtsvertretung aus dienstlichen Gründen', kurz: 'Dienstliche Gründe', abschnitte: ['lehrkraft'] },
    dienstbefreiung: { titel: 'Dienstbefreiung nach § 14 Abs. 1 Urlaubsverordnung', kurz: 'Dienstbefreiung § 14', abschnitte: ['lehrkraft'] },
    fortbildung:     { titel: 'Dienstbefreiung für Fortbildungsveranstaltung', kurz: 'Fortbildung', abschnitte: ['lehrkraft'] },
    verlegung:       { titel: 'Unterrichtsverlegung aus privaten Gründen', kurz: 'Verlegung (privat)', abschnitte: ['lehrkraft'] },
    veranstaltung:   { titel: 'Unterrichtsvertretung wegen Veranstaltung', kurz: 'Veranstaltung', abschnitte: ['veranstaltung', 'taetigkeit'] },
    klasse:          { titel: 'Klasse abwesend ohne Lehrkraft', kurz: 'Klasse abwesend', abschnitte: ['klasse', 'taetigkeit'] }
  };

  function felderVon(anlass) {
    return ANLAESSE[anlass].abschnitte.flatMap(schluessel => ABSCHNITTE[schluessel].felder);
  }

  /* ---------- Status ---------- */

  const STATUS_TEXT = {
    entwurf: 'Entwurf',
    eingereicht: 'eingereicht',
    abgezeichnet_al: 'abgezeichnet vom AL',
    abgezeichnet_ssl: 'abgezeichnet vom stellv. SL',
    genehmigt: 'genehmigt',
    abgelehnt: 'abgelehnt',
    storniert: 'zurückgezogen'
  };

  // Filter in den Übersichten
  const STATUS_GRUPPEN = {
    offen: 'In Bearbeitung',
    genehmigt: 'Genehmigt',
    abgelehnt: 'Abgelehnt',
    storniert: 'Zurückgezogen',
    entwurf: 'Entwurf'
  };

  // Texte für Log und Verlauf
  const AKTIONEN = {
    eingereicht: 'Eingereicht',
    zurueckgewiesen: 'Zur Korrektur zurückgewiesen',
    umgeleitet: 'An andere Abteilung umgeleitet',
    abgezeichnet_al: 'Abgezeichnet (AL)',
    abgezeichnet_ssl: 'Abgezeichnet (stellv. SL)',
    genehmigt: 'Genehmigt',
    abgelehnt: 'Abgelehnt',
    storniert: 'Zurückgezogen',
    ablehnung_abgezeichnet: 'Ablehnung vom AL abgezeichnet',
    stornierung_abgezeichnet: 'Stornierung vom AL abgezeichnet',
    sek_abgezeichnet: 'Vom Sekretariat abgezeichnet',
    ausgeblendet: 'Ausgeblendet',
    eingeblendet: 'Eingeblendet',
    entwurf_geloescht: 'Entwurf gelöscht',
    benutzer_angelegt: 'Benutzer angelegt',
    benutzer_geaendert: 'Benutzer geändert',
    export: 'Datensicherung exportiert'
  };

  /* ---------- Speicher ---------- */

  let zwischenspeicher = null;

  function laden() {
    if (zwischenspeicher) return zwischenspeicher;
    try {
      const roh = localStorage.getItem(SPEICHER);
      if (roh) return (zwischenspeicher = JSON.parse(roh));
    } catch (e) {
      // gesperrter oder beschädigter Speicher: mit Beispieldaten weiterarbeiten
    }
    zwischenspeicher = beispieldaten();
    speichern(zwischenspeicher);
    return zwischenspeicher;
  }

  function speichern(stand) {
    zwischenspeicher = stand;
    try {
      localStorage.setItem(SPEICHER, JSON.stringify(stand));
    } catch (e) {
      // ohne Speicher gehen Änderungen beim Seitenwechsel verloren
    }
  }

  // Änderungen aus anderen Tabs übernehmen
  window.addEventListener('storage', ereignis => {
    if (ereignis.key === SPEICHER) zwischenspeicher = null;
  });

  function jetzt() {
    return new Date().toISOString();
  }

  // Log-Einträge werden nur angehängt, nie geändert oder gelöscht
  function logEintrag(stand, benutzer, aktion, antragId, zeit, bemerkung) {
    stand.log.push(Object.freeze({
      nr: stand.log.length + 1,
      zeit: zeit || jetzt(),
      benutzerId: benutzer.id,
      name: benutzer.name,
      aktion,
      antragId: antragId || null,
      bemerkung: bemerkung || ''
    }));
  }

  // simuliert die Benachrichtigung per E-Mail bzw. Untis
  function benachrichtigen(stand, empfaenger, text, antragId, zeit, popup = false) {
    for (const b of empfaenger) {
      stand.nachrichten.push({
        id: stand.nachrichten.length + 1,
        an: b.id,
        text,
        antragId,
        zeit: zeit || jetzt(),
        gelesen: false,
        popup
      });
    }
  }

  /* ---------- Benutzer und Rollen ---------- */

  function hatRolle(benutzer, rolle) {
    return Boolean(benutzer && benutzer.rollen.includes(rolle));
  }

  function istAlVon(benutzer, abteilung) {
    return hatRolle(benutzer, 'al') && benutzer.abteilungen.includes(abteilung);
  }

  // stellv. SL und SL vertreten sich gegenseitig
  function istSchulleitung(benutzer) {
    return hatRolle(benutzer, 'ssl') || hatRolle(benutzer, 'sl');
  }

  function aktive(stand) {
    return stand.benutzer.filter(b => b.aktiv);
  }

  function alsVon(stand, abteilung) {
    return aktive(stand).filter(b => istAlVon(b, abteilung));
  }

  function mitRolle(stand, rolle) {
    return aktive(stand).filter(b => hatRolle(b, rolle));
  }

  function ohne(liste, ...ids) {
    return liste.filter(b => !ids.includes(b.id));
  }

  function rollenText(benutzer) {
    return benutzer.rollen.map(rolle => rolle === 'al'
      ? `${ROLLEN.al} (${benutzer.abteilungen.join(', ')})`
      : ROLLEN[rolle]).join(', ');
  }

  /* ---------- Wer ist gerade am Zug? ---------- */

  function beimSekretariat(antrag) {
    if (antrag.sekErledigt) return false;
    return antrag.status === 'genehmigt' ||
      ((antrag.status === 'abgelehnt' || antrag.status === 'storniert') && antrag.alAbgezeichnet);
  }

  // liefert die Aufgabe des Benutzers für diesen Antrag oder null
  function aufgabe(benutzer, antrag) {
    if (!benutzer || antrag.antragstellerId === benutzer.id) return null;
    switch (antrag.status) {
      case 'eingereicht':
        return istAlVon(benutzer, antrag.abteilung) ? 'al_pruefen' : null;
      case 'abgezeichnet_al':
        return istSchulleitung(benutzer) ? 'ssl_abzeichnen' : null;
      case 'abgezeichnet_ssl':
        return istSchulleitung(benutzer) ? 'sl_entscheiden' : null;
      case 'abgelehnt':
      case 'storniert':
        if (!antrag.alAbgezeichnet) return istAlVon(benutzer, antrag.abteilung) ? 'al_bestaetigen' : null;
        return beimSekretariat(antrag) && hatRolle(benutzer, 'sek') ? 'sek_abzeichnen' : null;
      case 'genehmigt':
        return beimSekretariat(antrag) && hatRolle(benutzer, 'sek') ? 'sek_abzeichnen' : null;
      default:
        return null;
    }
  }

  const AUFGABEN = {
    al_pruefen: 'Prüfen und abzeichnen',
    ssl_abzeichnen: 'Abzeichnen (Kenntnis nehmen)',
    sl_entscheiden: 'Genehmigen oder ablehnen',
    al_bestaetigen: 'Ablehnung/Stornierung abzeichnen',
    sek_abzeichnen: 'Abschließend abzeichnen'
  };

  // Hinweis, wenn stellv. SL und SL sich gegenseitig vertreten
  function vertretungsHinweis(benutzer, aufgabeSchluessel) {
    if (aufgabeSchluessel === 'ssl_abzeichnen' && !hatRolle(benutzer, 'ssl')) return 'in Vertretung des stellv. Schulleiters';
    if (aufgabeSchluessel === 'sl_entscheiden' && !hatRolle(benutzer, 'sl')) return 'in Vertretung des Schulleiters';
    return '';
  }

  function darfAnsehen(benutzer, antrag) {
    if (antrag.antragstellerId === benutzer.id) return true;
    if (antrag.status === 'entwurf') return false;
    return hatRolle(benutzer, 'sek') || istSchulleitung(benutzer) || istAlVon(benutzer, antrag.abteilung);
  }

  // auch ein zur Korrektur zurückgewiesener Antrag kann zurückgezogen werden
  function darfZurueckziehen(benutzer, antrag) {
    if (antrag.antragstellerId !== benutzer.id) return false;
    if (antrag.status === 'entwurf') return Boolean(antrag.eingereicht);
    return ['eingereicht', 'abgezeichnet_al', 'abgezeichnet_ssl', 'genehmigt'].includes(antrag.status);
  }

  // Entwürfe, die nie eingereicht wurden, dürfen wirklich gelöscht werden
  function darfEntwurfLoeschen(benutzer, antrag) {
    return antrag.antragstellerId === benutzer.id && antrag.status === 'entwurf' && !antrag.eingereicht;
  }

  /* ---------- Status-Anzeige ---------- */

  function statusText(antrag) {
    if (antrag.status === 'entwurf' && antrag.zurueckweisung) return 'zur Korrektur zurückgewiesen';
    let text = STATUS_TEXT[antrag.status];
    if (antrag.sekErledigt) text += ', abgeschlossen';
    else if (antrag.status === 'abgelehnt' || antrag.status === 'storniert') {
      text += antrag.alAbgezeichnet ? ', beim Sekretariat' : ', beim AL';
    } else if (antrag.status === 'genehmigt') text += ', beim Sekretariat';
    return text;
  }

  function statusGruppe(antrag) {
    if (antrag.status === 'entwurf') return 'entwurf';
    if (['genehmigt', 'abgelehnt', 'storniert'].includes(antrag.status)) return antrag.status;
    return 'offen';
  }

  // Farbe der Status-Plakette
  function statusFarbe(antrag) {
    if (antrag.status === 'entwurf') return antrag.zurueckweisung ? 'abgezeichnet' : 'entwurf';
    if (antrag.status === 'eingereicht') return 'eingereicht';
    if (antrag.status.startsWith('abgezeichnet')) return 'abgezeichnet';
    return antrag.status;
  }

  // Bearbeitungsstand für den Antragsteller: je eine Spalte pro Station
  function stufen(antrag) {
    const log = logZu(antrag.id);
    // nur den letzten Durchlauf seit dem letzten Einreichen betrachten
    let start = -1;
    log.forEach((e, i) => { if (e.aktion === 'eingereicht') start = i; });
    const lauf = start === -1 ? [] : log.slice(start);
    const finde = (...aktionen) => lauf.find(e => aktionen.includes(e.aktion));

    const eingereicht = finde('eingereicht');
    const al = finde('abgezeichnet_al');
    const ssl = finde('abgezeichnet_ssl');
    const sl = finde('genehmigt', 'abgelehnt');
    const sek = finde('sek_abgezeichnet');
    const beendet = antrag.status === 'storniert' || antrag.status === 'abgelehnt';

    const stufe = (text, eintrag, zustand) => ({
      text,
      zeit: eintrag && eintrag.zeit,
      name: eintrag && eintrag.name,
      zustand: zustand || (eintrag ? 'erledigt' : (beendet ? 'entfaellt' : 'offen'))
    });

    return [
      stufe('Eingereicht', eingereicht, eingereicht ? 'erledigt' : 'offen'),
      stufe('Abgezeichnet vom AL', al),
      stufe('Abgezeichnet vom stellv. SL', ssl),
      sl && sl.aktion === 'abgelehnt'
        ? stufe('Abgelehnt vom SL', sl, 'abgelehnt')
        : stufe('Genehmigt vom SL', sl),
      stufe('Abgezeichnet vom Sekretariat', sek, sek ? 'erledigt' : 'offen')
    ];
  }

  function kurzbeschreibung(antrag) {
    const d = antrag.daten || {};
    let text = ANLAESSE[antrag.anlass] ? ANLAESSE[antrag.anlass].kurz : 'ohne Anlass';
    if (d.beginn_datum) {
      const von = datumText(d.beginn_datum);
      text += d.ende_datum && d.ende_datum !== d.beginn_datum ? `, ${von} bis ${datumText(d.ende_datum)}` : `, am ${von}`;
    }
    return text;
  }

  function datumText(iso) {
    const [j, m, t] = iso.split('-');
    return `${t}.${m}.${j}`;
  }

  /* ---------- Prüfung der Formulardaten ---------- */

  // liefert { feldname: meldung } – leer, wenn alles passt
  function pruefen(antrag) {
    const fehler = {};
    if (!ABTEILUNGEN.includes(antrag.abteilung)) fehler.abteilung = 'Bitte wählen Sie eine Abteilung.';
    if (!ANLAESSE[antrag.anlass]) {
      fehler.anlass = 'Bitte wählen Sie einen Anlass.';
      return fehler;
    }
    const d = antrag.daten || {};
    for (const feld of felderVon(antrag.anlass)) {
      if (feld.optional || feld.typ === 'checkbox') continue;
      if (!String(d[feld.name] || '').trim()) fehler[feld.name] = 'Bitte ausfüllen.';
    }
    if (d.beginn_datum && d.ende_datum) {
      const beginn = `${d.beginn_datum}T${d.beginn_zeit || '00:00'}`;
      const ende = `${d.ende_datum}T${d.ende_zeit || '23:59'}`;
      if (ende < beginn) fehler.ende_datum = 'Das Ende liegt vor dem Beginn.';
    }
    (d.taetigkeiten || []).forEach((zeile, i) => {
      if (!zeile.datum || !zeile.stunden || !zeile.taetigkeit) {
        fehler.taetigkeiten = `Zeile ${i + 1}: bitte Datum, U-Std und Tätigkeit angeben oder die Zeile entfernen.`;
      }
    });
    return fehler;
  }

  /* ---------- Übergänge (intern, mit optionalem Zeitpunkt für die Beispieldaten) ---------- */

  function einreichenIn(stand, benutzer, antrag, zeit) {
    antrag.status = 'eingereicht';
    antrag.eingereicht = zeit || jetzt();
    delete antrag.zurueckweisung;
    logEintrag(stand, benutzer, 'eingereicht', antrag.id, zeit, `Abteilung ${antrag.abteilung}`);
    benachrichtigen(stand, ohne(alsVon(stand, antrag.abteilung), benutzer.id),
      `Neuer Antrag ${antrag.id} (${kurzbeschreibung(antrag)}) von ${benutzer.name}.`, antrag.id, zeit);
  }

  function alAbzeichnenIn(stand, benutzer, antrag, k, zeit) {
    antrag.status = 'abgezeichnet_al';
    antrag.k = Boolean(k);
    logEintrag(stand, benutzer, 'abgezeichnet_al', antrag.id, zeit, antrag.k ? 'Feld „K“ gesetzt (ganztägige Abwesenheit)' : '');
    benachrichtigen(stand, ohne(mitRolle(stand, 'ssl'), antrag.antragstellerId),
      `Antrag ${antrag.id} wurde vom AL abgezeichnet und liegt zur Kenntnisnahme vor.`, antrag.id, zeit);
  }

  function zurueckweisenIn(stand, benutzer, antrag, grund, zeit) {
    antrag.status = 'entwurf';
    antrag.zurueckweisung = { grund, name: benutzer.name, zeit: zeit || jetzt() };
    logEintrag(stand, benutzer, 'zurueckgewiesen', antrag.id, zeit, grund);
    benachrichtigen(stand, [benutzerNachIdIn(stand, antrag.antragstellerId)],
      `Ihr Antrag ${antrag.id} wurde zur Korrektur zurückgewiesen: ${grund}`, antrag.id, zeit, true);
  }

  function umleitenIn(stand, benutzer, antrag, abteilung, zeit) {
    const alt = antrag.abteilung;
    antrag.abteilung = abteilung;
    logEintrag(stand, benutzer, 'umgeleitet', antrag.id, zeit, `${alt} → ${abteilung}`);
    benachrichtigen(stand, ohne(alsVon(stand, abteilung), antrag.antragstellerId),
      `Antrag ${antrag.id} wurde von der Abteilung ${alt} an Ihre Abteilung umgeleitet.`, antrag.id, zeit);
  }

  function sslAbzeichnenIn(stand, benutzer, antrag, zeit) {
    antrag.status = 'abgezeichnet_ssl';
    logEintrag(stand, benutzer, 'abgezeichnet_ssl', antrag.id, zeit, vertretungsHinweis(benutzer, 'ssl_abzeichnen'));
    benachrichtigen(stand, ohne(mitRolle(stand, 'sl'), antrag.antragstellerId),
      `Antrag ${antrag.id} liegt zur Genehmigung vor.`, antrag.id, zeit);
  }

  function genehmigenIn(stand, benutzer, antrag, zeit) {
    antrag.status = 'genehmigt';
    logEintrag(stand, benutzer, 'genehmigt', antrag.id, zeit, vertretungsHinweis(benutzer, 'sl_entscheiden'));
    benachrichtigen(stand, [benutzerNachIdIn(stand, antrag.antragstellerId)],
      `Ihr Antrag ${antrag.id} wurde genehmigt.`, antrag.id, zeit);
    benachrichtigen(stand, mitRolle(stand, 'sek'), `Antrag ${antrag.id} wurde genehmigt und ist abzuzeichnen.`, antrag.id, zeit);
  }

  function ablehnenIn(stand, benutzer, antrag, grund, zeit) {
    antrag.status = 'abgelehnt';
    antrag.alAbgezeichnet = false;
    antrag.ablehnung = { grund, name: benutzer.name, zeit: zeit || jetzt() };
    const vertretung = vertretungsHinweis(benutzer, 'sl_entscheiden');
    logEintrag(stand, benutzer, 'abgelehnt', antrag.id, zeit, vertretung ? `${grund} (${vertretung})` : grund);
    // AL wird mit Pop-up informiert und muss die Ablehnung abzeichnen
    benachrichtigen(stand, ohne(alsVon(stand, antrag.abteilung), antrag.antragstellerId),
      `Antrag ${antrag.id} wurde vom Schulleiter abgelehnt. Bitte die Ablehnung abzeichnen. Grund: ${grund}`, antrag.id, zeit, true);
    benachrichtigen(stand, [benutzerNachIdIn(stand, antrag.antragstellerId)],
      `Ihr Antrag ${antrag.id} wurde abgelehnt. Grund: ${grund}`, antrag.id, zeit, true);
  }

  function zurueckziehenIn(stand, benutzer, antrag, grund, zeit) {
    const vorher = STATUS_TEXT[antrag.status];
    antrag.status = 'storniert';
    antrag.alAbgezeichnet = false;
    antrag.stornierung = { grund, zeit: zeit || jetzt(), vorher };
    logEintrag(stand, benutzer, 'storniert', antrag.id, zeit, grund ? `vorher: ${vorher}; ${grund}` : `vorher: ${vorher}`);
    // laut Besprechung der Schulleitung: nur die zuständigen AL werden informiert
    benachrichtigen(stand, ohne(alsVon(stand, antrag.abteilung), benutzer.id),
      `Antrag ${antrag.id} wurde von ${benutzer.name} zurückgezogen. Bitte die Stornierung abzeichnen.`, antrag.id, zeit, true);
  }

  function alBestaetigenIn(stand, benutzer, antrag, zeit) {
    antrag.alAbgezeichnet = true;
    const aktion = antrag.status === 'abgelehnt' ? 'ablehnung_abgezeichnet' : 'stornierung_abgezeichnet';
    logEintrag(stand, benutzer, aktion, antrag.id, zeit);
    benachrichtigen(stand, mitRolle(stand, 'sek'),
      `Antrag ${antrag.id} (${STATUS_TEXT[antrag.status]}) ist abzuzeichnen.`, antrag.id, zeit);
  }

  function sekAbzeichnenIn(stand, benutzer, antrag, zeit) {
    antrag.sekErledigt = true;
    logEintrag(stand, benutzer, 'sek_abgezeichnet', antrag.id, zeit, antrag.k ? 'Feld „K“ berücksichtigt' : '');
  }

  function ausblendenIn(stand, benutzer, antrag, aus, zeit) {
    antrag.ausgeblendet = aus;
    logEintrag(stand, benutzer, aus ? 'ausgeblendet' : 'eingeblendet', antrag.id, zeit);
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
    const benutzer = [
      { id: 1,  login: 'keller',   name: 'M. Keller',   amt: 'StR',  rollen: ['lehrer'],        abteilungen: [] },
      { id: 2,  login: 'brandt',   name: 'P. Brandt',   amt: 'OStR', rollen: ['lehrer'],        abteilungen: [] },
      { id: 3,  login: 'vogt',     name: 'S. Vogt',     amt: 'StD',  rollen: ['lehrer', 'al'],  abteilungen: ['BBWQ'] },
      { id: 4,  login: 'schulz',   name: 'T. Schulz',   amt: 'StD',  rollen: ['lehrer', 'al'],  abteilungen: ['BBWQ'] },
      { id: 5,  login: 'lang',     name: 'J. Lang',     amt: 'StD',  rollen: ['lehrer', 'al'],  abteilungen: ['EMT'] },
      { id: 6,  login: 'becker',   name: 'R. Becker',   amt: 'StD',  rollen: ['lehrer', 'al'],  abteilungen: ['IPT'] },
      { id: 7,  login: 'roth',     name: 'K. Roth',     amt: 'StD',  rollen: ['lehrer', 'ssl'], abteilungen: [] },
      { id: 8,  login: 'rahali',   name: 'Mamut Rahali',  amt: 'OStD', rollen: ['lehrer', 'sl'],  abteilungen: [] },
      { id: 9,  login: 'hoffmann', name: 'A. Hoffmann', amt: '',     rollen: ['sek'],           abteilungen: [] },
      { id: 10, login: 'admin',    name: 'IT-Administration', amt: '', rollen: ['admin'],       abteilungen: [] }
    ].map(b => ({ ...b, aktiv: true }));

    const stand = { naechsteNr: 243, naechsteBenutzerId: 11, benutzer, antraege: [], log: [], nachrichten: [] };
    const wer = login => stand.benutzer.find(b => b.login === login);

    const lehrkraft = (tage, grund) => ({
      grund, beginn_datum: datumIn(tage), beginn_zeit: '07:45', ende_datum: datumIn(tage), ende_zeit: '15:15',
      material_plattform: true, material_mail: false
    });

    // schritte: [aktion, login, vor Tagen, Zusatz]
    const anlegen = (nr, login, abteilung, anlass, daten, schritte) => {
      const antragsteller = wer(login);
      const antrag = { id: 'A-' + nr, antragstellerId: antragsteller.id, abteilung, anlass, daten, anlage: '', status: 'entwurf',
        erstellt: vorTagen(schritte.length ? schritte[0][2] + 1 : 1, 7, 30) };
      stand.antraege.push(antrag);
      schritte.forEach(([aktion, von, tage, zusatz], i) => {
        const b = wer(von);
        const zeit = vorTagen(tage, 8 + i, 15 * i);
        if (aktion === 'einreichen') einreichenIn(stand, b, antrag, zeit);
        if (aktion === 'al') alAbzeichnenIn(stand, b, antrag, zusatz, zeit);
        if (aktion === 'zurueck') zurueckweisenIn(stand, b, antrag, zusatz, zeit);
        if (aktion === 'umleiten') umleitenIn(stand, b, antrag, zusatz, zeit);
        if (aktion === 'ssl') sslAbzeichnenIn(stand, b, antrag, zeit);
        if (aktion === 'genehmigen') genehmigenIn(stand, b, antrag, zeit);
        if (aktion === 'ablehnen') ablehnenIn(stand, b, antrag, zusatz, zeit);
        if (aktion === 'storno') zurueckziehenIn(stand, b, antrag, zusatz, zeit);
        if (aktion === 'bestaetigen') alBestaetigenIn(stand, b, antrag, zeit);
        if (aktion === 'sek') sekAbzeichnenIn(stand, b, antrag, zeit);
        if (aktion === 'ausblenden') ausblendenIn(stand, b, antrag, true, zeit);
      });
    };

    anlegen(231, 'brandt', 'IPT', 'dienstlich', lehrkraft(-20, 'Prüfungsausschuss IHK'), [
      ['einreichen', 'brandt', 30], ['al', 'becker', 29, false], ['ssl', 'roth', 28], ['genehmigen', 'rahali', 27],
      ['sek', 'hoffmann', 26], ['ausblenden', 'hoffmann', 25]
    ]);
    anlegen(232, 'brandt', 'IPT', 'dienstbefreiung', lehrkraft(3, 'Hochzeit der Tochter'), [
      ['einreichen', 'brandt', 12], ['al', 'becker', 11, true], ['ssl', 'roth', 10], ['genehmigen', 'rahali', 9], ['sek', 'hoffmann', 8]
    ]);
    anlegen(233, 'keller', 'BBWQ', 'fortbildung', lehrkraft(9, 'Fortbildung „Digitale Medien im Unterricht“, LPM Saarbrücken'), [
      ['einreichen', 'keller', 9], ['al', 'vogt', 8, true], ['ssl', 'roth', 7], ['genehmigen', 'rahali', 6]
    ]);
    anlegen(234, 'brandt', 'BBWQ', 'verlegung', lehrkraft(6, 'Arzttermin, nicht verschiebbar'), [
      ['einreichen', 'brandt', 7], ['al', 'schulz', 6, false], ['ssl', 'roth', 5],
      ['ablehnen', 'rahali', 4, 'Am Termin finden Abschlussprüfungen statt, bitte Termin verlegen.']
    ]);
    anlegen(235, 'keller', 'EMT', 'dienstlich', lehrkraft(14, 'Messebesuch mit Ausbildungsbetrieben'), [
      ['einreichen', 'keller', 6], ['al', 'lang', 5, false], ['storno', 'keller', 3, 'Messe wurde abgesagt.']
    ]);
    anlegen(236, 'keller', 'BBWQ', 'klasse', {
      klasse: 'BBW 11a', grund: 'Abschlussprüfung', beginn_datum: datumIn(4), beginn_zeit: '07:45', ende_datum: datumIn(4), ende_zeit: '12:00',
      info_freigesetzt: true,
      taetigkeiten: [{ datum: datumIn(4), stunden: '1.–4.', taetigkeit: 'Prüfungsaufsicht Raum 1A405' }]
    }, [['einreichen', 'keller', 4], ['al', 'vogt', 3, false], ['ssl', 'roth', 2]]);
    // zuerst an BBWQ gestellt, vom AL an EMT umgeleitet
    anlegen(237, 'brandt', 'BBWQ', 'veranstaltung', {
      art: 'Schulfahrt', ziel: 'Berlin, Bundestag', klassen: 'EMT 12b', lehrkraefte: 'L. Sommer',
      beginn_datum: datumIn(20), beginn_zeit: '07:00', ende_datum: datumIn(23), ende_zeit: '18:00',
      verkehrsmittel: 'Bahn', info_freigesetzt: true, taetigkeiten: []
    }, [['einreichen', 'brandt', 3], ['umleiten', 'vogt', 2, 'EMT'], ['al', 'lang', 1, true]]);
    anlegen(238, 'vogt', 'BBWQ', 'fortbildung', lehrkraft(12, 'Tagung der Abteilungsleitungen'), [
      ['einreichen', 'vogt', 2]
    ]);
    anlegen(239, 'brandt', 'BBWQ', 'dienstlich', lehrkraft(5, 'Sitzung Prüfungsausschuss'), [
      ['einreichen', 'brandt', 2], ['zurueck', 'schulz', 1, 'Bitte die Uhrzeit korrigieren, die Sitzung beginnt erst um 13 Uhr.']
    ]);
    anlegen(240, 'keller', 'BBWQ', 'fortbildung', lehrkraft(15, 'Fortbildung Prüfungsvorbereitung'), [
      ['einreichen', 'keller', 1]
    ]);
    anlegen(241, 'keller', 'BBWQ', 'veranstaltung', {
      art: 'Unterrichtsgang', ziel: 'Landtag Saarbrücken', klassen: 'BBW 10a', lehrkraefte: '',
      beginn_datum: datumIn(10), beginn_zeit: '08:00', ende_datum: datumIn(10), ende_zeit: '13:00', verkehrsmittel: 'Bus',
      info_freigesetzt: false, taetigkeiten: []
    }, []);
    anlegen(242, 'rahali', 'BBWQ', 'dienstlich', lehrkraft(8, 'Schulleitertagung Ministerium'), [
      ['einreichen', 'rahali', 3], ['al', 'vogt', 2, true]
    ]);

    // Beispiele wurden Antrag für Antrag erzeugt: Log zeitlich ordnen und neu nummerieren
    stand.log = stand.log
      .slice()
      .sort((a, b) => a.zeit.localeCompare(b.zeit))
      .map((e, i) => Object.freeze({ ...e, nr: i + 1 }));

    // Beispielnachrichten gelten als gelesen, Pop-ups nur für die offenen Fälle
    for (const n of stand.nachrichten) {
      const antrag = stand.antraege.find(a => a.id === n.antragId);
      n.gelesen = !(antrag && aufgabe(stand.benutzer.find(b => b.id === n.an), antrag));
    }
    return stand;
  }

  /* ---------- Abfragen ---------- */

  function benutzerNachIdIn(stand, id) {
    return stand.benutzer.find(b => b.id === id) || null;
  }

  function benutzerNachId(id) {
    return benutzerNachIdIn(laden(), id);
  }

  function benutzerNachLogin(login) {
    const gesucht = String(login).trim().toLowerCase();
    return laden().benutzer.find(b => b.login === gesucht) || null;
  }

  function alleBenutzer() {
    return laden().benutzer;
  }

  function antragNachId(id) {
    return laden().antraege.find(a => a.id === id) || null;
  }

  function antraegeVon(benutzer) {
    return laden().antraege.filter(a => a.antragstellerId === benutzer.id);
  }

  function aufgabenFuer(benutzer) {
    return laden().antraege.filter(a => aufgabe(benutzer, a));
  }

  // alles, was der Benutzer außer seinen eigenen Anträgen sehen darf
  function sichtbarFuer(benutzer) {
    return laden().antraege.filter(a => a.antragstellerId !== benutzer.id && darfAnsehen(benutzer, a));
  }

  function logZu(antragId) {
    return laden().log.filter(e => e.antragId === antragId);
  }

  function logAlle(benutzer) {
    if (!hatRolle(benutzer, 'sl')) throw new Error('Nur der Schulleiter darf die Logdatei ausgeben.');
    return laden().log.slice();
  }

  function nachrichtenFuer(benutzer) {
    return laden().nachrichten.filter(n => n.an === benutzer.id).sort((a, b) => b.zeit.localeCompare(a.zeit));
  }

  function ungeleseneAnzahl(benutzer) {
    return nachrichtenFuer(benutzer).filter(n => !n.gelesen).length;
  }

  // Pop-up-Nachrichten werden einmal angezeigt und dann als gelesen markiert
  function popupsAbholen(benutzer) {
    const stand = laden();
    const offen = stand.nachrichten.filter(n => n.an === benutzer.id && n.popup && !n.gelesen);
    if (!offen.length) return [];
    offen.forEach(n => { n.gelesen = true; });
    speichern(stand);
    return offen;
  }

  function nachrichtenGelesen(benutzer) {
    const stand = laden();
    stand.nachrichten.filter(n => n.an === benutzer.id).forEach(n => { n.gelesen = true; });
    speichern(stand);
  }

  /* ---------- Aktionen der Lehrkraft ---------- */

  // speichert neuen oder vorhandenen Entwurf; einreichen = true schickt ihn gleich ab
  function antragSpeichern(benutzer, eingabe, einreichen) {
    if (!hatRolle(benutzer, 'lehrer')) throw new Error('Nur Lehrkräfte können Anträge stellen.');
    if (einreichen && Object.keys(pruefen(eingabe)).length) throw new Error('Der Antrag ist nicht vollständig.');

    const stand = laden();
    let antrag = eingabe.id ? stand.antraege.find(a => a.id === eingabe.id) : null;
    if (eingabe.id && (!antrag || antrag.antragstellerId !== benutzer.id || antrag.status !== 'entwurf')) {
      throw new Error('Dieser Entwurf kann nicht mehr bearbeitet werden.');
    }
    if (!antrag) {
      antrag = { id: 'A-' + stand.naechsteNr, antragstellerId: benutzer.id, status: 'entwurf', erstellt: jetzt() };
      stand.naechsteNr += 1;
      stand.antraege.push(antrag);
    }
    antrag.abteilung = eingabe.abteilung || '';
    antrag.anlass = eingabe.anlass || '';
    antrag.daten = eingabe.daten || {};
    antrag.anlage = eingabe.anlage || antrag.anlage || '';
    antrag.geaendert = jetzt();

    if (einreichen) einreichenIn(stand, benutzer, antrag);
    speichern(stand);
    return antrag;
  }

  function entwurfLoeschen(benutzer, antragId) {
    const stand = laden();
    const index = stand.antraege.findIndex(a => a.id === antragId);
    if (index === -1 || !darfEntwurfLoeschen(benutzer, stand.antraege[index])) {
      throw new Error('Dieser Entwurf kann nicht gelöscht werden.');
    }
    stand.antraege.splice(index, 1);
    speichern(stand);
  }

  function antragZurueckziehen(benutzer, antragId, grund) {
    const stand = laden();
    const antrag = stand.antraege.find(a => a.id === antragId);
    if (!antrag || !darfZurueckziehen(benutzer, antrag)) throw new Error('Dieser Antrag kann nicht zurückgezogen werden.');
    zurueckziehenIn(stand, benutzer, antrag, String(grund || '').trim());
    speichern(stand);
    return antrag;
  }

  /* ---------- Aktionen der Bearbeiter ---------- */

  function mitAufgabe(benutzer, antragId, erwartet) {
    const stand = laden();
    const antrag = stand.antraege.find(a => a.id === antragId);
    if (!antrag || !erwartet.includes(aufgabe(benutzer, antrag))) {
      throw new Error('Dieser Antrag liegt nicht zur Bearbeitung bei Ihnen.');
    }
    return { stand, antrag };
  }

  function pflichtGrund(grund) {
    const text = String(grund || '').trim();
    if (!text) throw new Error('Bitte geben Sie eine Begründung an.');
    return text;
  }

  function alAbzeichnen(benutzer, antragId, k) {
    const { stand, antrag } = mitAufgabe(benutzer, antragId, ['al_pruefen']);
    alAbzeichnenIn(stand, benutzer, antrag, k);
    speichern(stand);
  }

  function alZurueckweisen(benutzer, antragId, grund) {
    const text = pflichtGrund(grund);
    const { stand, antrag } = mitAufgabe(benutzer, antragId, ['al_pruefen']);
    zurueckweisenIn(stand, benutzer, antrag, text);
    speichern(stand);
  }

  function alUmleiten(benutzer, antragId, abteilung) {
    const { stand, antrag } = mitAufgabe(benutzer, antragId, ['al_pruefen']);
    if (!ABTEILUNGEN.includes(abteilung) || abteilung === antrag.abteilung) throw new Error('Bitte eine andere Abteilung wählen.');
    umleitenIn(stand, benutzer, antrag, abteilung);
    speichern(stand);
  }

  function sslAbzeichnen(benutzer, antragId) {
    const { stand, antrag } = mitAufgabe(benutzer, antragId, ['ssl_abzeichnen']);
    sslAbzeichnenIn(stand, benutzer, antrag);
    speichern(stand);
  }

  function slGenehmigen(benutzer, antragId) {
    const { stand, antrag } = mitAufgabe(benutzer, antragId, ['sl_entscheiden']);
    genehmigenIn(stand, benutzer, antrag);
    speichern(stand);
  }

  function slAblehnen(benutzer, antragId, grund) {
    const text = pflichtGrund(grund);
    const { stand, antrag } = mitAufgabe(benutzer, antragId, ['sl_entscheiden']);
    ablehnenIn(stand, benutzer, antrag, text);
    speichern(stand);
  }

  function alBestaetigen(benutzer, antragId) {
    const { stand, antrag } = mitAufgabe(benutzer, antragId, ['al_bestaetigen']);
    alBestaetigenIn(stand, benutzer, antrag);
    speichern(stand);
  }

  function sekAbzeichnen(benutzer, antragId) {
    const { stand, antrag } = mitAufgabe(benutzer, antragId, ['sek_abzeichnen']);
    sekAbzeichnenIn(stand, benutzer, antrag);
    speichern(stand);
  }

  function sekAusblenden(benutzer, antragId, aus) {
    const stand = laden();
    const antrag = stand.antraege.find(a => a.id === antragId);
    if (!hatRolle(benutzer, 'sek') || !antrag || !antrag.sekErledigt) {
      throw new Error('Nur abgeschlossene Anträge können aus- oder eingeblendet werden.');
    }
    ausblendenIn(stand, benutzer, antrag, aus);
    speichern(stand);
  }

  /* ---------- Schulleiter: Log und Datensicherung ---------- */

  function export_(benutzer) {
    if (!hatRolle(benutzer, 'sl')) throw new Error('Nur der Schulleiter darf die Datensicherung erstellen.');
    const stand = laden();
    logEintrag(stand, benutzer, 'export', null, null, `${stand.antraege.length} Anträge, ${stand.log.length} Log-Einträge`);
    speichern(stand);
    return {
      erstellt: jetzt(),
      erstelltVon: benutzer.name,
      antraege: stand.antraege.map(a => ({ ...a, antragsteller: benutzerNachIdIn(stand, a.antragstellerId).name })),
      log: stand.log
    };
  }

  /* ---------- Administration ---------- */

  function benutzerSpeichern(admin, daten) {
    if (!hatRolle(admin, 'admin')) throw new Error('Nur Administratoren dürfen Benutzer verwalten.');
    const stand = laden();
    const login = String(daten.login || '').trim().toLowerCase();
    const name = String(daten.name || '').trim();
    const rollen = ROLLEN_REIHENFOLGE.filter(r => (daten.rollen || []).includes(r));
    const abteilungen = rollen.includes('al') ? ABTEILUNGEN.filter(a => (daten.abteilungen || []).includes(a)) : [];

    if (!/^[a-z][a-z0-9._-]{1,29}$/.test(login)) throw new Error('Der Benutzername darf nur Kleinbuchstaben, Ziffern, Punkt, Binde- und Unterstrich enthalten.');
    if (!name) throw new Error('Bitte einen Namen angeben.');
    if (!rollen.length) throw new Error('Bitte mindestens eine Rolle wählen.');
    if (rollen.includes('al') && !abteilungen.length) throw new Error('Abteilungsleiter brauchen mindestens eine Abteilung.');
    if (stand.benutzer.some(b => b.login === login && b.id !== daten.id)) throw new Error('Dieser Benutzername ist schon vergeben.');
    if (daten.id === admin.id && (!rollen.includes('admin') || !daten.aktiv)) {
      throw new Error('Sie können sich nicht selbst die Administratorrechte entziehen.');
    }

    let b = daten.id ? stand.benutzer.find(x => x.id === daten.id) : null;
    const neu = !b;
    if (neu) {
      b = { id: stand.naechsteBenutzerId++ };
      stand.benutzer.push(b);
    }
    Object.assign(b, { login, name, amt: String(daten.amt || '').trim(), rollen, abteilungen, aktiv: Boolean(daten.aktiv) });
    logEintrag(stand, admin, neu ? 'benutzer_angelegt' : 'benutzer_geaendert', null, null,
      `${login}: ${rollenText(b)}${b.aktiv ? '' : ' (deaktiviert)'}`);
    speichern(stand);
    return b;
  }

  const ROLLEN_REIHENFOLGE = Object.keys(ROLLEN);

  function zuruecksetzen() {
    speichern(beispieldaten());
  }

  return {
    ROLLEN, ABTEILUNGEN, ANLAESSE, ABSCHNITTE, STATUS_GRUPPEN, AKTIONEN, AUFGABEN,
    felderVon, hatRolle, istAlVon, istSchulleitung, rollenText,
    aufgabe, vertretungsHinweis, darfAnsehen, darfZurueckziehen, darfEntwurfLoeschen,
    statusText, statusGruppe, statusFarbe, stufen, kurzbeschreibung, pruefen,
    benutzerNachId, benutzerNachLogin, alleBenutzer,
    antragNachId, antraegeVon, aufgabenFuer, sichtbarFuer, logZu, logAlle,
    nachrichtenFuer, ungeleseneAnzahl, popupsAbholen, nachrichtenGelesen,
    antragSpeichern, entwurfLoeschen, antragZurueckziehen,
    alAbzeichnen, alZurueckweisen, alUmleiten, sslAbzeichnen, slGenehmigen, slAblehnen,
    alBestaetigen, sekAbzeichnen, sekAusblenden,
    export: export_, benutzerSpeichern, zuruecksetzen
  };
})();
