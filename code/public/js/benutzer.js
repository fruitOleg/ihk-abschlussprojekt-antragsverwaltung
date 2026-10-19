(() => {
  'use strict';

  const admin = App.seiteSchuetzen(['admin']);
  if (!admin) return;

  const esc = App.esc;
  const formular = document.getElementById('formular');
  const f = formular.elements;
  let bearbeitet = null;

  document.getElementById('rollen').innerHTML = Object.entries(Daten.ROLLEN).map(([rolle, text]) =>
    `<label class="haken"><input type="checkbox" name="rollen" value="${rolle}"> ${text}</label>`).join('');
  document.getElementById('abteilungen').innerHTML = Daten.ABTEILUNGEN.map(abt =>
    `<label class="haken"><input type="checkbox" name="abteilungen" value="${abt}"> ${abt}</label>`).join('');

  liste();

  function liste() {
    const benutzer = Daten.alleBenutzer();
    document.getElementById('liste').innerHTML = `<div class="tabelle-rahmen">
      <table class="tabelle">
        <thead><tr><th scope="col">Benutzer</th><th scope="col">Name</th><th scope="col">Amt</th><th scope="col">Rollen</th><th scope="col">Status</th><th scope="col"><span class="unsichtbar">Aktion</span></th></tr></thead>
        <tbody>${benutzer.map(b => `<tr${b.aktiv ? '' : ' class="ausgeblendet"'}>
          <td>${esc(b.login)}</td>
          <td>${esc(b.name)}</td>
          <td>${esc(b.amt || '–')}</td>
          <td>${esc(Daten.rollenText(b))}</td>
          <td>${b.aktiv ? 'aktiv' : 'deaktiviert'}</td>
          <td class="rechts"><button type="button" class="knopf knopf--zweit knopf--klein" data-id="${b.id}">Bearbeiten</button></td>
        </tr>`).join('')}</tbody>
      </table>
    </div>`;
  }

  document.getElementById('liste').addEventListener('click', ereignis => {
    const knopf = ereignis.target.closest('[data-id]');
    if (knopf) oeffnen(Daten.benutzerNachId(Number(knopf.dataset.id)));
  });
  document.getElementById('neu').addEventListener('click', () => oeffnen(null));
  document.getElementById('abbrechen').addEventListener('click', () => { formular.hidden = true; });

  function oeffnen(b) {
    bearbeitet = b;
    document.getElementById('formular-titel').textContent = b ? `Benutzer „${b.login}“ bearbeiten` : 'Neuer Benutzer';
    f.login.value = b ? b.login : '';
    f.name.value = b ? b.name : '';
    f.amt.value = b ? b.amt || '' : '';
    f.aktiv.checked = b ? b.aktiv : true;
    formular.querySelectorAll('input[name="rollen"]').forEach(e => { e.checked = b ? b.rollen.includes(e.value) : e.value === 'lehrer'; });
    formular.querySelectorAll('input[name="abteilungen"]').forEach(e => { e.checked = b ? b.abteilungen.includes(e.value) : false; });
    abteilungenZeigen();
    formular.hidden = false;
    f.login.focus();
  }

  function abteilungenZeigen() {
    document.getElementById('abteilungen-feld').hidden = !formular.querySelector('input[name="rollen"][value="al"]').checked;
  }
  formular.addEventListener('change', abteilungenZeigen);

  formular.addEventListener('submit', ereignis => {
    ereignis.preventDefault();
    const gewaehlt = name => [...formular.querySelectorAll(`input[name="${name}"]:checked`)].map(e => e.value);
    try {
      const b = Daten.benutzerSpeichern(admin, {
        id: bearbeitet ? bearbeitet.id : undefined,
        login: f.login.value,
        name: f.name.value,
        amt: f.amt.value,
        aktiv: f.aktiv.checked,
        rollen: gewaehlt('rollen'),
        abteilungen: gewaehlt('abteilungen')
      });
      formular.hidden = true;
      liste();
      App.hinweisZeigen(`Benutzer „${b.login}“ wurde gespeichert.`);
    } catch (fehler) {
      App.hinweisZeigen(fehler.message, 'fehler');
    }
  });
})();
