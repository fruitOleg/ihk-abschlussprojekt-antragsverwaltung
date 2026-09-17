(() => {
  'use strict';

  const angemeldet = App.aktuellerBenutzer();
  if (angemeldet) {
    location.replace(App.startseite(angemeldet));
    return;
  }

  const formular = document.getElementById('anmeldung');
  const feldLogin = document.getElementById('login');
  const feldPasswort = document.getElementById('passwort');
  const fehler = document.getElementById('anmeldeFehler');

  formular.addEventListener('submit', ereignis => {
    ereignis.preventDefault();
    fehler.hidden = true;

    const benutzer = Daten.benutzerNachLogin(feldLogin.value);
    if (!benutzer) {
      zeigeFehler('Benutzername unbekannt.', feldLogin);
      return;
    }
    if (!feldPasswort.value) {
      zeigeFehler('Bitte geben Sie ein Passwort ein.', feldPasswort);
      return;
    }

    App.anmelden(benutzer);
    location.href = App.startseite(benutzer);
  });

  document.getElementById('zuruecksetzen').addEventListener('click', () => {
    Daten.zuruecksetzen();
    App.hinweisZeigen('Die Demodaten wurden zurückgesetzt.');
  });

  function zeigeFehler(text, feld) {
    fehler.textContent = text;
    fehler.hidden = false;
    feld.focus();
  }
})();
