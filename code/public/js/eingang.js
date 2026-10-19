(() => {
  'use strict';

  const benutzer = App.seiteSchuetzen(['al', 'ssl', 'sl', 'sek']);
  if (!benutzer) return;

  // Beschreibung passend zu den Rollen des Benutzers
  const teile = [];
  if (Daten.hatRolle(benutzer, 'al')) teile.push(`als Abteilungsleiter (${benutzer.abteilungen.join(', ')}): Anträge prüfen, abzeichnen, zurückweisen oder umleiten`);
  if (Daten.istSchulleitung(benutzer)) teile.push('als Schulleitung: abzeichnen bzw. genehmigen oder ablehnen – stellv. SL und SL vertreten sich gegenseitig');
  if (Daten.hatRolle(benutzer, 'sek')) teile.push('als Sekretariat: alle abgeschlossenen Vorgänge abzeichnen');
  document.getElementById('untertitel').textContent = `Anträge, die auf Sie warten – ${teile.join('; ')}.`;

  // älteste zuerst, damit nichts liegen bleibt
  const antraege = Daten.aufgabenFuer(benutzer)
    .sort((a, b) => (a.eingereicht || a.erstellt).localeCompare(b.eingereicht || b.erstellt));

  const ziel = document.getElementById('liste');
  ziel.innerHTML = antraege.length
    ? Anzeige.liste(antraege, { aufgabe: true, knopf: 'Bearbeiten' })
    : '<div class="karte"><p class="leer">Zurzeit liegen keine Anträge zur Bearbeitung vor. <a href="uebersicht.html">Alle Anträge ansehen</a></p></div>';
})();
