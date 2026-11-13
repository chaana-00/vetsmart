/* Farm location picker: type a place name, search, click the map, drag the pin, or use your GPS.
   Uses Google Maps when GOOGLE_MAPS_API_KEY is set, otherwise OpenStreetMap (Leaflet). */
(function () {
  'use strict';
  const VS = window.VS || {};
  const root = document.querySelector('[data-map-picker]');
  if (!root) return;
  const $ = s => root.querySelector(s);
  const latI = $('[name=latitude]'), lngI = $('[name=longitude]'), nameI = $('[name=location_name]');
  const search = $('.mp-search'), mapEl = $('.mp-map'), status = $('.mp-status');
  const center = (latI.value && lngI.value) ? [parseFloat(latI.value), parseFloat(lngI.value)] : VS.mapCenter;
  const hasPin = !!(latI.value && lngI.value);
  let engine = null; // {setPin(lat,lng), pan(lat,lng,zoom), search(text), reverse(lat,lng)->Promise<string>}

  const say = t => { if (status) status.textContent = t; };
  function setFields(lat, lng, fillName) {
    latI.value = (+lat).toFixed(7); lngI.value = (+lng).toFixed(7);
    say('Pinned at ' + (+lat).toFixed(5) + ', ' + (+lng).toFixed(5));
    if (fillName && engine && !nameI.dataset.manual) {
      engine.reverse(lat, lng).then(n => { if (n) nameI.value = n; }).catch(() => {});
    }
  }
  nameI.addEventListener('input', () => nameI.dataset.manual = nameI.value ? '1' : '');
  if (nameI.value) nameI.dataset.manual = '1';

  /* ---------- Google Maps ---------- */
  function startGoogle() {
    window.__vsGmap = function () {
      const map = new google.maps.Map(mapEl, { center: { lat: center[0], lng: center[1] }, zoom: hasPin ? 15 : 9, mapTypeControl: true, streetViewControl: false });
      const marker = new google.maps.Marker({ map, draggable: true, visible: hasPin, position: { lat: center[0], lng: center[1] } });
      const geo = new google.maps.Geocoder();
      const put = (lat, lng, fill) => { marker.setPosition({ lat, lng }); marker.setVisible(true); setFields(lat, lng, fill); };
      map.addListener('click', e => put(e.latLng.lat(), e.latLng.lng(), true));
      marker.addListener('dragend', e => setFields(e.latLng.lat(), e.latLng.lng(), true));
      engine = {
        pan: (lat, lng, z) => { map.setCenter({ lat, lng }); map.setZoom(z || 15); put(lat, lng, false); },
        search: txt => geo.geocode({ address: txt }, (r, s) => {
          if (s === 'OK' && r[0]) { const l = r[0].geometry.location; map.fitBounds(r[0].geometry.viewport); put(l.lat(), l.lng(), false); if (!nameI.dataset.manual) nameI.value = r[0].formatted_address; }
          else say('Place not found. Try a more specific name.');
        }),
        reverse: (lat, lng) => new Promise(res => geo.geocode({ location: { lat, lng } }, (r, s) => res(s === 'OK' && r[0] ? r[0].formatted_address : ''))),
        syncFromFields: (lat, lng) => put(lat, lng, false)
      };
      if (google.maps.places) {
        const ac = new google.maps.places.Autocomplete(search, { fields: ['geometry', 'formatted_address', 'name'] });
        ac.addListener('place_changed', () => {
          const p = ac.getPlace(); if (!p.geometry) return;
          if (p.geometry.viewport) map.fitBounds(p.geometry.viewport); else map.setZoom(16);
          put(p.geometry.location.lat(), p.geometry.location.lng(), false);
          if (!nameI.dataset.manual) nameI.value = p.formatted_address || p.name;
        });
      }
    };
    const s = document.createElement('script');
    s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(VS.mapsKey) + '&libraries=places&callback=__vsGmap';
    s.async = true; document.head.appendChild(s);
  }

  /* ---------- OpenStreetMap / Leaflet (no key) ---------- */
  function startLeaflet() {
    const css = document.createElement('link'); css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(css);
    const s = document.createElement('script'); s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    s.onload = () => {
      const map = L.map(mapEl).setView(center, hasPin ? 15 : 9);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
      let marker = hasPin ? L.marker(center, { draggable: true }).addTo(map) : null;
      const bind = m => m.on('dragend', () => { const p = m.getLatLng(); setFields(p.lat, p.lng, true); });
      if (marker) bind(marker);
      const put = (lat, lng, fill) => {
        if (!marker) { marker = L.marker([lat, lng], { draggable: true }).addTo(map); bind(marker); } else marker.setLatLng([lat, lng]);
        setFields(lat, lng, fill);
      };
      map.on('click', e => put(e.latlng.lat, e.latlng.lng, true));
      engine = {
        pan: (lat, lng, z) => { map.setView([lat, lng], z || 15); put(lat, lng, false); },
        search: txt => fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(txt))
          .then(r => r.json()).then(r => {
            if (r[0]) { map.setView([+r[0].lat, +r[0].lon], 15); put(+r[0].lat, +r[0].lon, false); if (!nameI.dataset.manual) nameI.value = r[0].display_name; }
            else say('Place not found. Try a more specific name.');
          }).catch(() => say('Search is unavailable right now. Click the map instead.')),
        reverse: (lat, lng) => fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng)
          .then(r => r.json()).then(r => r.display_name || ''),
        syncFromFields: (lat, lng) => { map.setView([lat, lng], 15); put(lat, lng, false); }
      };
      setTimeout(() => map.invalidateSize(), 200);
    };
    document.head.appendChild(s);
  }

  VS.mapsKey ? startGoogle() : startLeaflet();

  /* ---------- Controls ---------- */
  const doSearch = () => { const t = (search.value || nameI.value).trim(); if (t && engine) engine.search(t); };
  $('.mp-go')?.addEventListener('click', doSearch);
  search.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });
  $('.mp-gps')?.addEventListener('click', () => {
    if (!navigator.geolocation) return say('Your browser cannot share its location.');
    say('Finding your position…');
    navigator.geolocation.getCurrentPosition(p => engine && engine.pan(p.coords.latitude, p.coords.longitude, 17), () => say('Location permission was denied.'), { enableHighAccuracy: true, timeout: 10000 });
  });
  [latI, lngI].forEach(i => i.addEventListener('change', () => {
    const a = parseFloat(latI.value), b = parseFloat(lngI.value);
    if (!isNaN(a) && !isNaN(b) && engine) engine.syncFromFields(a, b);
  }));
})();
