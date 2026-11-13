<?php /* Reusable location block: manual name + map pick. Include inside a <form>. */ ?>
<div data-map-picker>
  <div class="mb-3">
    <label class="form-label" for="location_name">Farm location</label>
    <input class="form-control" id="location_name" name="location_name" required maxlength="200"
           placeholder="e.g. Homagama, or pick on the map below" value="<?= h($loc['location_name'] ?? post('location_name')) ?>">
    <div class="form-text">Type it yourself, or pin the spot and the address fills in for you.</div>
  </div>
  <div class="mp mb-2">
    <div class="mp-bar">
      <input type="text" class="form-control mp-search" placeholder="Search a place or address" autocomplete="off">
      <button type="button" class="btn btn-sm btn-vs mp-go"><i class="bi bi-search"></i> Search</button>
      <button type="button" class="btn btn-sm btn-soft mp-gps"><i class="bi bi-crosshair"></i> Use my location</button>
    </div>
    <div class="mp-map"></div>
    <div class="mp-foot"><span class="mp-status">Click the map to drop a pin, then drag it to fine-tune.</span></div>
  </div>
  <div class="row g-3">
    <div class="col-6"><label class="form-label" for="latitude">Latitude</label>
      <input class="form-control" id="latitude" name="latitude" inputmode="decimal" placeholder="optional" value="<?= h($loc['latitude'] ?? post('latitude')) ?>"></div>
    <div class="col-6"><label class="form-label" for="longitude">Longitude</label>
      <input class="form-control" id="longitude" name="longitude" inputmode="decimal" placeholder="optional" value="<?= h($loc['longitude'] ?? post('longitude')) ?>"></div>
  </div>
</div>
