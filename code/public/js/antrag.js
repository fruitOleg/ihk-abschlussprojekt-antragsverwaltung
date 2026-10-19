(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen();
  if (!benutzer) return;

  const esc = App.esc;
  const ziel = document.getElementById('antrag');
  const antrag = Daten.antragNachId(App.parameter('id') || '');

  // zurück dorthin, woher man kam
  const zurueck = document.getElementById('zurueck');
  const herkunft = document.referrer && new URL(document.referrer, location.href);
  if (herkunft && herkunft.origin === location.origin && herkunft.pathname !== location.pathname) {
    zurueck.href = herkunft.href;
  } else {
    zurueck.href = antrag && antrag.antragstellerId === benutzer.id ? `meine-antraege.html?id=${antrag.id}` : App.startseite(benutzer);
  }

  if (!antrag || !Daten.darfAnsehen(benutzer, antrag)) {
    ziel.innerHTML = `<div class="karte stapel stapel--eng">
      <h1>Antrag nicht verfügbar</h1>
      <p>Diesen Antrag gibt es nicht oder Sie dürfen ihn nicht einsehen.</p>
    </div>`;
    return;
  }

  const aufgabe = Daten.aufgabe(benutzer, antrag);
  const vertretung = aufgabe ? Daten.vertretungsHinweis(benutzer, aufgabe) : '';
  document.title = `Antrag ${antrag.id} – Antragsverwaltung`;

  ziel.innerHTML = `
    <h1>Antrag ${antrag.id}: ${esc(Daten.kurzbeschreibung(antrag))}</h1>
    ${aufgabe ? `<section class="karte karte--aufgabe" aria-labelledby="aufgabe-titel">
      <h2 id="aufgabe-titel">Ihre Aufgabe: ${Daten.AUFGABEN[aufgabe]}${vertretung ? ` <small class="leise">(${vertretung})</small>` : ''}</h2>
      ${aufgabeHtml()}
    </section>` : ''}
    ${sekretariatHtml()}
    ${Anzeige.angaben(antrag)}
    <section class="karte" aria-labelledby="stand-titel">
      <h2 id="stand-titel">Bearbeitungsstand</h2>
      ${Anzeige.stand(antrag)}
    </section>
    <section class="karte" aria-labelledby="verlauf-titel">
      <h2 id="verlauf-titel">Verlauf (Log)</h2>
      ${Anzeige.verlauf(antrag.id)}
    </section>`;

  /* ---------- Aktionsbereiche je Aufgabe ---------- */

  function aufgabeHtml() {
    switch (aufgabe) {
      case 'al_pruefen':
        return `<p class="leise abstand-unten">Regeln Sie, was nötig ist (z. B. Vertretung), und zeichnen Sie den Antrag dann ab.
          Gehört der Antrag in eine andere Abteilung, wählen Sie diese aus – der Knopf wechselt dann zu „Umleiten“.</p>
          <div class="formular-raster">
            <div class="feld">
              <label for="abteilung">Abteilung</label>
              <select id="abteilung">${Daten.ABTEILUNGEN.map(a => `<option${a === antrag.abteilung ? ' selected' : ''}>${a}</option>`).join('')}</select>
            </div>
            <div class="feld feld--unten">
              <label class="haken"><input type="checkbox" id="k"> Feld „K“ in Untis setzen (ganztägige Abwesenheit)</label>
            </div>
          </div>
          <div class="knopfzeile knopfzeile--links">
            <button type="button" class="knopf" id="al-weiter">Abzeichnen</button>
            <button type="button" class="knopf knopf--zweit" id="al-zurueck">Zur Korrektur zurückweisen</button>
          </div>`;
      case 'ssl_abzeichnen':
        return `<p class="leise">Der Abteilungsleiter hat den Antrag abgezeichnet. Mit Ihrer Abzeichnung nehmen Sie ihn zur Kenntnis; danach liegt er beim Schulleiter.</p>
          <div class="knopfzeile knopfzeile--links"><button type="button" class="knopf" id="ssl-weiter">Abzeichnen</button></div>`;
      case 'sl_entscheiden':
        return `<p class="leise">Genehmigen oder ablehnen. Bei einer Ablehnung werden die Abteilungsleitung und der Antragsteller informiert.</p>
          <div class="knopfzeile knopfzeile--links">
            <button type="button" class="knopf" id="sl-genehmigen">Genehmigen</button>
            <button type="button" class="knopf knopf--gefahr-zweit" id="sl-ablehnen">Ablehnen</button>
          </div>`;
      case 'al_bestaetigen':
        return `<p class="leise">${antrag.status === 'abgelehnt' ? 'Der Schulleiter hat den Antrag abgelehnt.' : 'Der Antragsteller hat den Antrag zurückgezogen.'}
          Bitte leiten Sie das Nötige ein (z. B. Vertretung aufheben) und zeichnen Sie den Vorgang ab. Danach geht er an das Sekretariat.</p>
          <div class="knopfzeile knopfzeile--links"><button type="button" class="knopf" id="al-bestaetigen">${antrag.status === 'abgelehnt' ? 'Ablehnung' : 'Stornierung'} abzeichnen</button></div>`;
      case 'sek_abzeichnen':
        return `<p class="leise">${antrag.k ? '<b>Feld „K“ ist gesetzt</b> (ganztägige Abwesenheit) – bitte Untis-Eintrag prüfen. ' : ''}
          Mit Ihrer Abzeichnung ist der Vorgang abgeschlossen. Danach können Sie ihn ausblenden.</p>
          <div class="knopfzeile knopfzeile--links"><button type="button" class="knopf" id="sek-weiter">Abzeichnen</button></div>`;
      default:
        return '';
    }
  }

  // Aus-/Einblenden abgeschlossener Vorgänge
  function sekretariatHtml() {
    if (!Daten.hatRolle(benutzer, 'sek') || !antrag.sekErledigt) return '';
    return `<section class="karte">
      <p>${antrag.ausgeblendet ? 'Dieser Antrag ist ausgeblendet und erscheint nur, wenn „Ausgeblendete anzeigen“ gewählt ist.' : 'Der Vorgang ist abgeschlossen.'}</p>
      <div class="knopfzeile knopfzeile--links">
        <button type="button" class="knopf knopf--zweit" id="sek-blenden">${antrag.ausgeblendet ? 'Einblenden' : 'Ausblenden'}</button>
      </div>
    </section>`;
  }

  /* ---------- Ereignisse ---------- */

  const an = (id, ereignis, funktion) => {
    const element = document.getElementById(id);
    if (element) element.addEventListener(ereignis, funktion);
  };

  // AL: Knopf wechselt zu "Umleiten", sobald eine andere Abteilung gewählt ist
  an('abteilung', 'change', () => {
    const umleiten = document.getElementById('abteilung').value !== antrag.abteilung;
    const knopf = document.getElementById('al-weiter');
    knopf.textContent = umleiten ? 'Umleiten' : 'Abzeichnen';
    document.getElementById('k').disabled = umleiten;
  });

  an('al-weiter', 'click', async () => {
    const abteilung = document.getElementById('abteilung').value;
    if (abteilung !== antrag.abteilung) {
      const ok = await App.bestaetigen({
        titel: 'Antrag umleiten',
        text: `Antrag ${antrag.id} wird an die Abteilung ${abteilung} umgeleitet. Deren Abteilungsleitung wird informiert.`,
        ja: 'Umleiten'
      });
      if (ok) ausfuehren(() => Daten.alUmleiten(benutzer, antrag.id, abteilung), `Antrag ${antrag.id} wurde an ${abteilung} umgeleitet.`);
      return;
    }
    const k = document.getElementById('k').checked;
    const ok = await App.zweifach({
      titel: 'Abzeichnen',
      frage: `Möchten Sie den Antrag ${antrag.id} abzeichnen?${k ? ' Das Feld „K“ wird gesetzt.' : ''}`,
      bestaetigung: `Bitte bestätigen Sie: Antrag ${antrag.id} wird verbindlich abgezeichnet und an den stellv. Schulleiter weitergeleitet.`
    });
    if (ok) ausfuehren(() => Daten.alAbzeichnen(benutzer, antrag.id, k), `Antrag ${antrag.id} wurde abgezeichnet.`);
  });

  an('al-zurueck', 'click', async () => {
    const grund = await App.bestaetigen({
      titel: 'Zur Korrektur zurückweisen',
      text: `Antrag ${antrag.id} geht zurück an ${Daten.benutzerNachId(antrag.antragstellerId).name}, der ihn korrigieren und erneut absenden kann.`,
      eingabe: 'Was ist zu korrigieren?',
      ja: 'Zurückweisen'
    });
    if (grund !== false) ausfuehren(() => Daten.alZurueckweisen(benutzer, antrag.id, grund), `Antrag ${antrag.id} wurde zur Korrektur zurückgewiesen.`);
  });

  an('ssl-weiter', 'click', async () => {
    const ok = await App.zweifach({
      titel: 'Abzeichnen',
      frage: `Möchten Sie den Antrag ${antrag.id} abzeichnen?`,
      bestaetigung: `Bitte bestätigen Sie: Antrag ${antrag.id} wird verbindlich abgezeichnet und an den Schulleiter weitergeleitet.`
    });
    if (ok) ausfuehren(() => Daten.sslAbzeichnen(benutzer, antrag.id), `Antrag ${antrag.id} wurde abgezeichnet.`);
  });

  an('sl-genehmigen', 'click', async () => {
    const ok = await App.zweifach({
      titel: 'Genehmigen',
      frage: `Möchten Sie den Antrag ${antrag.id} genehmigen?`,
      bestaetigung: `Bitte bestätigen Sie: Antrag ${antrag.id} wird verbindlich genehmigt.`,
      ja: 'Genehmigen'
    });
    if (ok) ausfuehren(() => Daten.slGenehmigen(benutzer, antrag.id), `Antrag ${antrag.id} wurde genehmigt.`);
  });

  an('sl-ablehnen', 'click', async () => {
    const grund = await App.bestaetigen({
      titel: 'Antrag ablehnen',
      text: `Antrag ${antrag.id} wird abgelehnt. Die Abteilungsleitung ${antrag.abteilung} und der Antragsteller werden informiert.`,
      eingabe: 'Begründung',
      ja: 'Ablehnen', gefahr: true
    });
    if (grund !== false) ausfuehren(() => Daten.slAblehnen(benutzer, antrag.id, grund), `Antrag ${antrag.id} wurde abgelehnt.`);
  });

  an('al-bestaetigen', 'click', async () => {
    const was = antrag.status === 'abgelehnt' ? 'Ablehnung' : 'Stornierung';
    const ok = await App.zweifach({
      titel: `${was} abzeichnen`,
      frage: `Haben Sie die ${was} von Antrag ${antrag.id} zur Kenntnis genommen und das Nötige veranlasst?`,
      bestaetigung: `Bitte bestätigen Sie: Die ${was} wird abgezeichnet und an das Sekretariat weitergeleitet.`
    });
    if (ok) ausfuehren(() => Daten.alBestaetigen(benutzer, antrag.id), `${was} von ${antrag.id} wurde abgezeichnet.`);
  });

  an('sek-weiter', 'click', async () => {
    const ok = await App.zweifach({
      titel: 'Abschließend abzeichnen',
      frage: `Möchten Sie den Vorgang ${antrag.id} abzeichnen?${antrag.k ? ' Das Feld „K“ ist gesetzt.' : ''}`,
      bestaetigung: `Bitte bestätigen Sie: Der Vorgang ${antrag.id} (${Daten.statusText(antrag)}) wird abgeschlossen.`
    });
    if (ok) ausfuehren(() => Daten.sekAbzeichnen(benutzer, antrag.id), `Vorgang ${antrag.id} wurde abgezeichnet.`);
  });

  an('sek-blenden', 'click', () => {
    const aus = !antrag.ausgeblendet;
    ausfuehren(() => Daten.sekAusblenden(benutzer, antrag.id, aus),
      `Antrag ${antrag.id} wurde ${aus ? 'ausgeblendet' : 'eingeblendet'}.`, `antrag.html?id=${antrag.id}`);
  });

  function ausfuehren(aktion, meldung, weiter = 'eingang.html') {
    try {
      aktion();
    } catch (fehler) {
      App.hinweisZeigen(fehler.message, 'fehler');
      return;
    }
    App.hinweisMerken(meldung);
    location.href = weiter;
  }
})();
