(() => {
  'use strict';

  const SESIONES = ['gestor', 'poseedor', 'scrap'];

  // Inscritos de ejemplo (entre 1 y 30) que se suman a las inscripciones reales en el contador.
  const INSCRITOS_BASE = { gestor: 18, poseedor: 24, scrap: 11 };

  const API = {
    inscribir: 'api/inscribir.php',
    contador: 'api/contador.php',
  };

  // ---------- Pestañas ----------
  const tabButtons = [...document.querySelectorAll('[role="tab"]')];

  function selectTab(name, focus = false) {
    if (!SESIONES.includes(name)) name = SESIONES[0];
    for (const btn of tabButtons) {
      const active = btn.dataset.tab === name;
      btn.setAttribute('aria-selected', String(active));
      btn.tabIndex = active ? 0 : -1;
      if (active && focus) btn.focus();
    }
    for (const panel of document.querySelectorAll('[role="tabpanel"]')) {
      panel.hidden = panel.id !== `panel-${name}`;
    }
    if (location.hash !== `#${name}`) {
      try { history.replaceState(null, '', `#${name}`); } catch { /* p. ej. file:// restringido */ }
    }
  }

  tabButtons.forEach((btn, i) => {
    btn.addEventListener('click', () => {
      selectTab(btn.dataset.tab);
      window.scrollTo({ top: 0 });   // cada sesión empieza en su cabecera
    });
    btn.addEventListener('keydown', (e) => {
      const last = tabButtons.length - 1;
      const next = { ArrowRight: i === last ? 0 : i + 1, ArrowLeft: i === 0 ? last : i - 1, Home: 0, End: last }[e.key];
      if (next === undefined) return;
      e.preventDefault();
      selectTab(tabButtons[next].dataset.tab, true);
    });
  });

  window.addEventListener('hashchange', () => selectTab(location.hash.slice(1)));

  // ---------- Contador de inscritos (ejemplo + reales del servidor) ----------
  function pintarContador(sesion, reales) {
    const el = document.querySelector(`[data-count="${sesion}"]`);
    if (el) el.textContent = (INSCRITOS_BASE[sesion] || 0) + (reales || 0);
  }

  async function cargarContadores() {
    SESIONES.forEach((s) => pintarContador(s, 0));
    try {
      const res = await fetch(API.contador, { cache: 'no-store' });
      const data = await res.json();
      if (data.ok) SESIONES.forEach((s) => pintarContador(s, data.inscritos[s]));
    } catch {
      /* sin servidor: se quedan los números de ejemplo */
    }
  }

  // ---------- Formularios de inscripción ----------
  for (const form of document.querySelectorAll('form[data-sesion]')) {
    const sesion = form.dataset.sesion;
    const fields = [...form.querySelectorAll('input:not([name="web"])')];
    const submit = form.querySelector('[type="submit"]');
    const done = document.querySelector(`[data-done="${sesion}"]`);

    // Cualquier error personalizado se limpia al volver a escribir o al limpiar el formulario.
    fields.forEach((f) => f.addEventListener('input', () => f.setCustomValidity('')));
    form.addEventListener('reset', () => fields.forEach((f) => f.setCustomValidity('')));

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      // Rechaza campos que solo contienen espacios.
      const vacio = fields.find((f) => f.required && f.type !== 'checkbox' && !f.value.trim());
      if (vacio) return fail(vacio, 'Completa este campo.');

      const el = form.elements;
      const payload = {
        sesion,
        nombre: el.nombre.value.trim(),
        empresa: el.empresa.value.trim(),
        cargo: el.cargo.value.trim(),
        correo: el.correo.value.trim(),
        privacidad: el.privacidad.checked,
        web: el.web.value,
      };

      const textoBoton = submit.textContent;
      submit.disabled = true;
      submit.textContent = 'Enviando…';

      try {
        const res = await fetch(API.inscribir, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => ({}));

        if (res.ok && data.ok) {
          if (typeof data.total === 'number') pintarContador(sesion, data.total);
          form.reset();
          form.hidden = true;
          done.hidden = false;
          done.focus();
          return;
        }

        const errores = data.errores || {};
        const campo = Object.keys(errores).find((k) => el[k]);
        if (campo) return fail(el[campo], errores[campo]);
        toast('No se ha podido completar la inscripción. Inténtalo de nuevo en unos minutos.');
      } catch {
        toast('No hay conexión con el servidor. Inténtalo de nuevo en unos minutos.');
      } finally {
        submit.disabled = false;
        submit.textContent = textoBoton;
      }
    });

    // "Inscribir a otra persona": vuelve a mostrar el formulario vacío.
    done.querySelector('[data-otra]').addEventListener('click', () => {
      done.hidden = true;
      form.hidden = false;
      form.elements.nombre.focus();
    });
  }

  function fail(field, message) {
    field.setCustomValidity(message);
    field.reportValidity();
  }

  // ---------- Aviso flotante ----------
  const toastEl = document.getElementById('toast');
  let toastTimer;
  function toast(message) {
    toastEl.textContent = message;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('show'), 4000);
  }

  // ---------- Inicio ----------
  selectTab(location.hash.slice(1));
  cargarContadores();
})();
