(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen();
  if (!benutzer) return;

  const nachrichten = Daten.nachrichtenFuer(benutzer);
  const ziel = document.getElementById('liste');

  if (!nachrichten.length) {
    ziel.innerHTML = '<div class="karte"><p class="leer">Keine Nachrichten.</p></div>';
    return;
  }

  ziel.innerHTML = `<ul class="nachrichten">${nachrichten.map(n => `
    <li class="karte${n.gelesen ? '' : ' nachricht--neu'}">
      <p>${n.gelesen ? '' : '<span class="neu">Neu</span> '}${App.esc(n.text)}</p>
      <p class="leise">${App.zeitpunkt(n.zeit)}${n.antragId && Daten.antragNachId(n.antragId) && Daten.darfAnsehen(benutzer, Daten.antragNachId(n.antragId))
        ? ` · <a href="${Daten.antragNachId(n.antragId).status === 'entwurf' ? `meine-antraege.html?id=${n.antragId}` : `antrag.html?id=${n.antragId}`}">Antrag ${n.antragId} öffnen</a>` : ''}</p>
    </li>`).join('')}</ul>`;

  // beim Ansehen gelten alle als gelesen; der Zähler oben wird beim nächsten Seitenaufruf kleiner
  Daten.nachrichtenGelesen(benutzer);
})();
