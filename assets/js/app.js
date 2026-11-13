/* VetSmart front-end behaviour: menu, cascading dropdowns, live notifications, small helpers */
(function () {
  'use strict';
  const VS = window.VS || {};
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const api = (p, opt) => fetch(VS.base + '/api/' + p, Object.assign({ credentials: 'same-origin' }, opt)).then(r => r.json());

  /* ---------- Mobile menu ---------- */
  const rail = $('#rail');
  $('#railToggle')?.addEventListener('click', () => rail.classList.toggle('open'));
  document.addEventListener('click', e => {
    if (rail?.classList.contains('open') && !rail.contains(e.target) && !e.target.closest('#railToggle')) rail.classList.remove('open');
  });

  /* ---------- Cascading Farm -> Location -> Batch dropdowns ---------- */
  function fillSelect(sel, items, placeholder, mapper) {
    const keep = sel.dataset.selected || '';
    sel.innerHTML = '<option value="">' + esc(placeholder) + '</option>';
    items.forEach(it => {
      const m = mapper(it);
      const o = document.createElement('option');
      o.value = m.value; o.textContent = m.label;
      Object.entries(m.data || {}).forEach(([k, v]) => o.dataset[k] = v);
      if (String(m.value) === String(keep)) o.selected = true;
      sel.appendChild(o);
    });
    if (items.length === 1 && !keep && sel.dataset.autopick !== 'off') sel.selectedIndex = 1;
    sel.dataset.selected = '';
  }

  $$('[data-cascade]').forEach(scope => {
    const farm = $('[data-farm]', scope), loc = $('[data-location]', scope), bat = $('[data-batch]', scope);
    const next = $('[data-next-batch]', scope), birds = $('[data-birds]', scope);
    if (!farm) return;

    async function loadBatches() {
      if (!bat) return;
      if (!farm.value) { fillSelect(bat, [], 'Select batch', x => x); return; }
      const qs = new URLSearchParams({ farm_id: farm.value });
      if (loc && loc.value) qs.set('location_id', loc.value);
      const d = await api('batches.php?' + qs);
      fillSelect(bat, d.batches || [], 'Select batch', b => ({ value: b.id, label: b.batch_no + (b.cage_no ? ' · Cage ' + b.cage_no : ''), data: { birds: b.total_input } }));
      bat.dispatchEvent(new Event('change'));
    }
    async function loadLocations() {
      if (!farm.value) {
        if (loc) fillSelect(loc, [], 'Select farm location', x => x);
        if (next) next.value = '';
        loadBatches(); return;
      }
      const d = await api('locations.php?farm_id=' + encodeURIComponent(farm.value));
      if (loc) fillSelect(loc, d.locations || [], 'Select farm location', l => ({ value: l.id, label: l.name }));
      if (next) next.value = d.next_batch || '';
      loadBatches();
    }
    farm.addEventListener('change', loadLocations);
    loc?.addEventListener('change', loadBatches);
    bat?.addEventListener('change', () => {
      const o = bat.options[bat.selectedIndex];
      if (birds && o && o.dataset.birds && !birds.dataset.touched) birds.value = o.dataset.birds;
    });
    birds?.addEventListener('input', () => birds.dataset.touched = '1');
    if (farm.value) loadLocations();
  });

  /* ---------- Select-all checkboxes ---------- */
  $$('[data-check-all]').forEach(master => {
    master.addEventListener('change', () => $$(master.dataset.checkAll).forEach(c => c.checked = master.checked));
  });

  /* ---------- Prescription rows ---------- */
  const rxBody = $('#rxBody');
  if (rxBody) {
    const tpl = rxBody.querySelector('tr').cloneNode(true);
    const clear = tr => { $$('input', tr).forEach(i => i.value = ''); return tr; };
    $('#addRow').addEventListener('click', () => { rxBody.appendChild(clear(tpl.cloneNode(true))); rxBody.lastElementChild.querySelector('input').focus(); });
    rxBody.addEventListener('click', e => {
      if (!e.target.closest('.btn-del')) return;
      if (rxBody.children.length > 1) e.target.closest('tr').remove(); else clear(rxBody.firstElementChild);
    });
  }

  /* ---------- Confirm dialogs ---------- */
  document.addEventListener('submit', e => {
    const m = e.target.dataset.confirm; if (m && !confirm(m)) e.preventDefault();
  });

  /* ---------- Live notifications ---------- */
  const ICONS = { farm: 'bi-house-heart-fill', batch: 'bi-egg-fill', broiler: 'bi-clipboard2-pulse-fill', layer: 'bi-clipboard2-pulse-fill', prescription: 'bi-capsule', user: 'bi-person-plus-fill', login: 'bi-box-arrow-in-right' };
  const BLUE = ['batch', 'prescription', 'user'];
  const feedItem = (n, isNew) => {
    const cls = BLUE.includes(n.type) ? 'b' : (n.type === 'login' ? 'x' : '');
    const inner = '<span class="t">' + esc(n.message) + '</span>';
    return '<li class="' + (isNew ? 'is-new' : '') + '"><span class="feed-ico ' + cls + '"><i class="bi ' + (ICONS[n.type] || 'bi-bell-fill') + '"></i></span>' +
      '<div class="feed-body">' + (n.link ? '<a href="' + VS.base + '/' + esc(n.link) + '">' + esc(n.message) + '</a>' : inner) +
      '<small>' + esc(n.actor_name) + ' · ' + esc(n.ago) + '</small></div></li>';
  };
  window.VSfeedItem = feedItem;

  const bellList = $('#bellList');
  let lastId = 0;
  const setUnread = n => $$('.js-unread').forEach(b => { b.textContent = n > 99 ? '99+' : n; b.hidden = !n; });

  function toast(n) {
    const stack = $('#toastStack'); if (!stack) return;
    const el = document.createElement('div');
    el.className = 'vs-toast' + (BLUE.includes(n.type) ? ' b' : '');
    el.innerHTML = '<div class="feed-body"><span class="t">' + esc(n.message) + '</span><small>' + esc(n.actor_name) + ' · just now</small></div>';
    stack.appendChild(el);
    setTimeout(() => el.remove(), 6000);
  }

  async function bootNotifs() {
    try {
      const d = await api('notifications.php?mode=list');
      lastId = d.last || 0;
      setUnread(d.unread || 0);
      if (bellList) bellList.innerHTML = (d.items || []).length ? '<ul class="feed">' + d.items.map(n => feedItem(n, false)).join('') + '</ul>' : '<div class="bell-empty">Nothing yet. New submissions will appear here instantly.</div>';
    } catch (e) { /* ignore */ }
    setInterval(poll, VS.pollMs || 5000);
  }
  async function poll() {
    if (document.hidden) return;
    try {
      const d = await api('notifications.php?after=' + lastId);
      if (d.error) return;
      setUnread(d.unread || 0);
      if (d.items && d.items.length) {
        lastId = d.last;
        d.items.slice(-3).forEach(toast);
        if (bellList) {
          let ul = $('ul.feed', bellList);
          if (!ul) { bellList.innerHTML = '<ul class="feed"></ul>'; ul = $('ul.feed', bellList); }
          ul.insertAdjacentHTML('afterbegin', d.items.slice().reverse().map(n => feedItem(n, true)).join(''));
        }
        const live = $('#liveFeed');
        if (live) {
          $('.empty', live.parentElement)?.remove();
          live.insertAdjacentHTML('afterbegin', d.items.slice().reverse().map(n => feedItem(n, true)).join(''));
        }
        document.dispatchEvent(new CustomEvent('vs:activity', { detail: d.items }));
      }
    } catch (e) { /* offline: try again next tick */ }
  }
  $('#markRead')?.addEventListener('click', async () => {
    const fd = new FormData(); fd.append('action', 'read'); fd.append('csrf', VS.csrf);
    await api('notifications.php', { method: 'POST', body: fd });
    setUnread(0); $$('.feed li.is-new').forEach(li => li.classList.remove('is-new'));
  });
  if ($('#bellBtn')) bootNotifs();
})();

/* ---------- Admin delete buttons ---------- */
document.addEventListener('click', function (e) {
  const b = e.target.closest('[data-del]');
  const f = document.getElementById('delForm');
  if (!b || !f) return;
  e.preventDefault();
  if (!confirm(b.dataset.msg || 'Delete this item? This cannot be undone.')) return;
  const [t, id] = b.dataset.del.split(':');
  f.elements['type'].value = t; f.elements['id'].value = id;
  if (b.dataset.return) f.elements['return'].value = b.dataset.return;
  f.submit();
});
