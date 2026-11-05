(() => {
  'use strict';

  const STORE_KEY = 'webinarscrap:v1';
  const TABS = ['poseedor', 'scrap', 'gestor'];

  // ---------- Estado y almacenamiento (localStorage) ----------
  const state = load();

  function load() {
    const empty = { poseedores: [], scraps: [], webinars: [] };
    try {
      const raw = localStorage.getItem(STORE_KEY);
      if (!raw) return empty;
      const data = JSON.parse(raw);
      return {
        poseedores: Array.isArray(data.poseedores) ? data.poseedores : [],
        scraps: Array.isArray(data.scraps) ? data.scraps : [],
        webinars: Array.isArray(data.webinars) ? data.webinars : [],
      };
    } catch {
      return empty;
    }
  }

  function persist() {
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify(state));
    } catch {
      /* almacenamiento no disponible: los datos solo viven en esta pestaña */
    }
  }

  const uid = () =>
    window.crypto && crypto.randomUUID
      ? crypto.randomUUID()
      : Date.now().toString(36) + Math.random().toString(36).slice(2);

  function readForm(form) {
    const data = {};
    for (const [key, value] of new FormData(form)) {
      data[key] = typeof value === 'string' ? value.trim() : value;
    }
    return data;
  }

  // ---------- Pestañas ----------
  const tabButtons = [...document.querySelectorAll('[role="tab"]')];

  function selectTab(name, focus = false) {
    if (!TABS.includes(name)) name = TABS[0];
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
    btn.addEventListener('click', () => selectTab(btn.dataset.tab));
    btn.addEventListener('keydown', (e) => {
      const last = tabButtons.length - 1;
      const next = { ArrowRight: i === last ? 0 : i + 1, ArrowLeft: i === 0 ? last : i - 1, Home: 0, End: last }[e.key];
      if (next === undefined) return;
      e.preventDefault();
      selectTab(tabButtons[next].dataset.tab, true);
    });
  });

  document.addEventListener('click', (e) => {
    const link = e.target.closest('[data-goto]');
    if (link) selectTab(link.dataset.goto, true);
  });

  window.addEventListener('hashchange', () => selectTab(location.hash.slice(1)));

  // ---------- Formularios ----------
  const forms = {
    poseedor: document.getElementById('form-poseedor'),
    scrap: document.getElementById('form-scrap'),
    gestor: document.getElementById('form-gestor'),
  };

  function afterSave(form, message) {
    persist();
    form.reset();
    render();
    toast(message);
  }

  // 1. Webinar Poseedor
  forms.poseedor.addEventListener('submit', (e) => {
    e.preventDefault();
    const data = readForm(forms.poseedor);
    state.poseedores.unshift({ ...data, id: uid(), creado: new Date().toISOString() });
    afterSave(forms.poseedor, `Poseedor "${data.nombre}" guardado`);
  });

  // 2. Webinar Scrap — exige al menos un dato a extraer
  const campoInputs = [...forms.scrap.querySelectorAll('input[name="campos"]')];

  function validateCampos() {
    const ok = campoInputs.some((c) => c.checked);
    campoInputs[0].setCustomValidity(ok ? '' : 'Selecciona al menos un dato a extraer.');
  }
  forms.scrap.addEventListener('change', validateCampos);
  forms.scrap.addEventListener('reset', () => setTimeout(validateCampos));
  validateCampos();

  forms.scrap.addEventListener('submit', (e) => {
    e.preventDefault();
    const data = readForm(forms.scrap);
    data.campos = new FormData(forms.scrap).getAll('campos');
    state.scraps.unshift({ ...data, id: uid(), estado: 'Pendiente', creado: new Date().toISOString() });
    afterSave(forms.scrap, 'Tarea de scrap creada');
  });

  // 3. Webinar Gestor
  forms.gestor.addEventListener('submit', (e) => {
    e.preventDefault();
    const data = readForm(forms.gestor);
    state.webinars.push({ ...data, id: uid(), creado: new Date().toISOString() });
    afterSave(forms.gestor, `Webinar "${data.titulo}" guardado`);
  });

  // ---------- Renderizado ----------
  const fmtFechaHora = new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' });

  const EMPTY = {
    poseedores: 'Todavía no hay poseedores registrados.',
    scraps: 'Todavía no hay tareas de scrap.',
    webinars: 'Todavía no hay webinars.',
  };

  function render() {
    renderPoseedorSelect();
    document.getElementById('gestor-aviso').hidden = state.poseedores.length > 0;

    renderList('poseedores', state.poseedores, (p) => {
      const n = state.webinars.filter((w) => w.poseedorId === p.id).length;
      return {
        title: p.nombre,
        meta: [p.email, p.empresa, p.telefono, p.plataforma, `${n} webinar${n === 1 ? '' : 's'}`],
        link: p.web,
      };
    });

    renderList('scraps', state.scraps, (s) => ({
      title: s.url,
      link: s.url,
      meta: [
        s.plataforma,
        (s.campos || []).join(', '),
        s.formato,
        s.frecuencia,
        s.maxPaginas && `máx. ${s.maxPaginas} págs.`,
        s.palabras && `“${s.palabras}”`,
      ],
      badge: s.estado,
    }));

    const ordenados = [...state.webinars].sort((a, b) =>
      `${a.fecha}T${a.hora}`.localeCompare(`${b.fecha}T${b.hora}`));

    renderList('webinars', ordenados, (w) => ({
      title: w.titulo,
      link: w.enlace,
      meta: [
        formatFecha(w),
        w.duracion && `${w.duracion} min`,
        nombrePoseedor(w.poseedorId),
        w.capacidad && `${w.capacidad} plazas`,
      ],
      badge: w.estado,
    }));
  }

  function renderPoseedorSelect() {
    const select = document.getElementById('g-poseedor');
    const current = select.value;
    const placeholder = new Option(
      state.poseedores.length ? 'Selecciona…' : 'Primero registra un poseedor', '', true, true);
    placeholder.disabled = true;
    const options = state.poseedores.map((p) =>
      new Option(p.empresa ? `${p.nombre} (${p.empresa})` : p.nombre, p.id));
    select.replaceChildren(placeholder, ...options);
    if (current && state.poseedores.some((p) => p.id === current)) select.value = current;
  }

  function renderList(key, items, describe) {
    const list = document.querySelector(`[data-list="${key}"]`);
    document.querySelector(`[data-count="${key}"]`).textContent = items.length;
    list.replaceChildren();

    if (!items.length) {
      list.append(el('p', 'empty', EMPTY[key]));
      return;
    }

    for (const item of items) {
      const { title, meta = [], badge, link } = describe(item);
      const row = el('article', 'row');
      const body = el('div', 'row-body');

      const href = safeHref(link);
      const titleEl = el(href ? 'a' : 'p', 'row-title', title);
      if (href) {
        titleEl.href = href;
        titleEl.target = '_blank';
        titleEl.rel = 'noopener noreferrer';
      }
      body.append(titleEl, el('p', 'row-meta', meta.filter(Boolean).join(' · ')));
      row.append(body);

      if (badge) row.append(el('span', `badge badge-${slug(badge)}`, badge));

      const del = el('button', 'btn-delete', 'Eliminar');
      del.type = 'button';
      del.setAttribute('aria-label', `Eliminar ${title}`);
      del.addEventListener('click', () => removeItem(key, item));
      row.append(del);

      list.append(row);
    }
  }

  function removeItem(key, item) {
    let msg = '¿Eliminar este elemento?';
    if (key === 'poseedores') {
      const n = state.webinars.filter((w) => w.poseedorId === item.id).length;
      msg = `¿Eliminar a "${item.nombre}"?` + (n ? ` Sus ${n} webinar(s) quedarán sin poseedor.` : '');
    }
    if (!confirm(msg)) return;
    state[key] = state[key].filter((x) => x.id !== item.id);
    persist();
    render();
    toast('Elemento eliminado');
  }

  // ---------- Utilidades ----------
  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }

  function slug(s) {
    return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, '-');
  }

  function safeHref(url) {
    return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : null;
  }

  function formatFecha(w) {
    if (!w.fecha) return '';
    const d = new Date(`${w.fecha}T${w.hora || '00:00'}`);
    return Number.isNaN(d.getTime()) ? w.fecha : fmtFechaHora.format(d);
  }

  function nombrePoseedor(id) {
    const p = state.poseedores.find((x) => x.id === id);
    return p ? p.nombre : 'Sin poseedor';
  }

  const toastEl = document.getElementById('toast');
  let toastTimer;
  function toast(message) {
    toastEl.textContent = message;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('show'), 2600);
  }

  // ---------- Inicio ----------
  selectTab(location.hash.slice(1));
  render();
})();
