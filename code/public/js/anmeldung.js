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
  const demo = document.getElementById('demoZugaenge');

  function demoAufbauen() {
    demo.innerHTML = Daten.alleBenutzer().filter(b => b.aktiv).map(b => `<tr>
        <td><button type="button" class="link" data-login="${b.login}">${b.login}</button></td>
        <td>${App.esc(b.name)}</td>
        <td>${App.esc(Daten.rollenText(b))}</td>
      </tr>`).join('');
  }
  demoAufbauen();

  demo.addEventListener('click', ereignis => {
    const knopf = ereignis.target.closest('[data-login]');
    if (!knopf) return;
    feldLogin.value = knopf.dataset.login;
    fehler.hidden = true;
    feldPasswort.focus();
  });

  formular.addEventListener('submit', ereignis => {
    ereignis.preventDefault();
    fehler.hidden = true;

    const benutzer = Daten.benutzerNachLogin(feldLogin.value);
    if (!benutzer || !benutzer.aktiv) {
      zeigeFehler(benutzer ? 'Dieses Konto ist deaktiviert.' : 'Benutzername unbekannt.', feldLogin);
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
    demoAufbauen();
    App.hinweisZeigen('Die Demodaten wurden zurückgesetzt.');
  });

  function zeigeFehler(text, feld) {
    fehler.textContent = text;
    fehler.hidden = false;
    feld.focus();
  }
})();
