<?php
// dashboard/analytics/index.php
// Animal Bite Analytics — Full cleaned + legend (Option A)
// - Fixes map unzoom (skip invalid lat/lng including 0,0)
// - Robust date parsing & filtering
// - Optimized layer handling (heat layers, combined heat, clusters, polygons)
// - Transparent polygons and safe refresh
// - Animal legend with emoji + live counts

include '../../layouts/head.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- Fetch distinct animals first (lowercased, normalized) ---
$animals = [];
try {
    $sqlAnimals = "SELECT DISTINCT LOWER(ba.animal_name) AS animal
                   FROM reports r
                   LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id
                   WHERE ba.animal_name IS NOT NULL
                   ORDER BY animal";
    $stmtA = $conn->prepare($sqlAnimals);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($row = $resA->fetch_assoc()) {
        $a = trim(strtolower($row['animal'] ?? 'unknown'));
        if ($a === '') $a = 'unknown';
        $animals[] = $a;
    }
} catch (Exception $e) {
    // fallback if query fails
    $animals = ['dog','cat'];
}

// --- Fetch raw points from DB ---
$sql = "
  SELECT
    r.latitud AS lat,
    r.longhitud AS lng,
    LOWER(ba.animal_name) AS animal,
    br.barangay_name AS barangay,
    DATE_FORMAT(b.date_reported, '%Y-%m-%d') AS date_reported
  FROM reports r
  JOIN bites b ON b.report_id = r.report_id
  LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id
  LEFT JOIN barangay br ON br.barangay_id = r.barangay_id
  WHERE
    r.latitud IS NOT NULL AND r.longhitud IS NOT NULL
    AND ba.animal_name IS NOT NULL
  ORDER BY b.date_reported DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();

$heatPoints = [];        // list of points: {lat, lng, animal, date, barangay}
$minDate = null;
$maxDate = null;

while ($row = $res->fetch_assoc()) {
    $lat = isset($row['lat']) ? trim($row['lat']) : null;
    $lng = isset($row['lng']) ? trim($row['lng']) : null;
    $animal = isset($row['animal']) ? strtolower(trim($row['animal'])) : '';
    $barangay = $row['barangay'] ?? 'Unknown';
    $date = $row['date_reported'] ?? null;

    if ($animal === '') $animal = 'unknown';

    $heatPoints[] = [
        'lat' => $lat,
        'lng' => $lng,
        'animal' => $animal,
        'date' => $date,
        'barangay' => $barangay
    ];

    if ($date) {
        if ($minDate === null || $date < $minDate) $minDate = $date;
        if ($maxDate === null || $date > $maxDate) $maxDate = $date;
    }
}

// fallback date range if DB has none
if ($minDate === null || $maxDate === null) {
    $maxDate = date('Y-m-d');
    $minDate = date('Y-m-d', strtotime('-30 days'));
}

// Asset base for geojson path
$geojsonPath = $assetBase . '/assets/frontend/Sariaya.geojson';

// JSON exposures
$animalsJson = json_encode(array_values(array_unique($animals)));
$heatPointsJson = json_encode($heatPoints);
$minDateJs = json_encode($minDate);
$maxDateJs = json_encode($maxDate);
$geojsonUrlJs = json_encode($geojsonPath);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster/dist/MarkerCluster.Default.css" />

<style>
  .legend { background: white; padding: 10px; border-radius: 6px; line-height: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.15); font-size: 13px; }
  .legend i { width: 22px; height: 12px; float: left; margin-right: 6px; opacity: 0.9; display:inline-block; }
  .controls { display:flex; gap:20px; flex-wrap:wrap; align-items:center; margin-bottom:12px; }
  .controls .form-control { max-width:220px; }
  .date-list { max-height:160px; overflow:auto; font-size:0.9rem; margin-top:6px; padding-left:12px; }
  .animal-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; vertical-align:middle; }
  .marker-divicon {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 2px rgba(0,0,0,0.45);
  }

  @media (max-width: 768px) {

  /* Hide both legends on mobile */
  .legend {
    display: none !important;
  }

  /* Hide the on-map checkboxes section on mobile */
  #toggleClusters,
  #toggleClusters + span,
  #togglePolygons,
  #togglePolygons + span {
    display: none !important;
  }
}
</style>

<div class="card p-5">
  <h3 class="mb-3">📊 Animal Bite Analytics — Heatmap & Barangay Polygons (Multi-Animal)</h3>

  <div class="controls mb-4">
    <label>
      From:
      <input type="date" id="dateFrom" class="form-control ml-2" />
    </label>
    <label>
      To:
      <input type="date" id="dateTo" class="form-control ml-2" />
    </label>

    <label>
      Animal:
      <select id="animalFilter" class="form-control ml-2">
        <option value="all">All (combined)</option>
        <!-- dynamic options injected by JS -->
      </select>
    </label>

    <button id="applyFiltersBtn" class="btn btn-primary mt-5">Apply</button>
    <button id="resetFiltersBtn" class="btn btn-outline-secondary mt-5">Reset</button>

    <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
      <label class="inline-flex items-center text-sm">
        <input type="checkbox" id="toggleClusters" /> <span class="ml-2">Show clusters</span>
      </label>
      <label class="inline-flex items-center text-sm ml-3">
        <input type="checkbox" id="togglePolygons" checked /> <span class="ml-2">Show barangay polygons</span>
      </label>
    </div>
  </div>

  <div id="map" style="height:640px; width:100%;"></div>
</div>

<!-- leaflet libs -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
<script src="https://unpkg.com/leaflet.markercluster/dist/leaflet.markercluster.js"></script>

<script>
/* --- Data from PHP --- */
const ANIMALS = <?= $animalsJson ?>;
const RAW_POINTS = <?= $heatPointsJson ?>;
const MIN_DATE = <?= $minDateJs ?>;
const MAX_DATE = <?= $maxDateJs ?>;
const GEOJSON_URL = <?= $geojsonUrlJs ?>;

/* --- Map setup --- */
const map = L.map('map').setView([13.964, 121.527], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

/* --- helpers --- */
function parseYMD(s) {
  if (!s) return null;
  const parts = s.toString().trim().split('-');
  if (parts.length !== 3) return null;
  const y = Number(parts[0]), m = Number(parts[1]) - 1, d = Number(parts[2]);
  const dt = new Date(y, m, d);
  if (isNaN(dt.getTime())) return null;
  return dt;
}
function hslToHex(h, s, l) {
  s /= 100; l /= 100;
  const k = n => (n + h / 30) % 12;
  const a = s * Math.min(l, 1 - l);
  const f = n => {
    const color = l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return Math.round(255 * color).toString(16).padStart(2, '0');
  };
  return `#${f(0)}${f(8)}${f(4)}`;
}
function toNumberSafe(v) {
  const n = Number((v === null || v === undefined) ? NaN : v);
  return isFinite(n) ? n : NaN;
}
function isValidLatLng(lat, lng) {
  if (!isFinite(lat) || !isFinite(lng)) return false;
  // reject placeholder 0,0
  if (lat === 0 && lng === 0) return false;
  return true;
}

function getFilteredPointCount() {
  let count = 0;

  const animalFilter = animalFilterEl.value;
  const from = dateFromEl.value ? parseYMD(dateFromEl.value) : null;
  const to = dateToEl.value ? parseYMD(dateToEl.value) : null;
  if (to) to.setHours(23,59,59,999);

  RAW_POINTS.forEach(pt => {
    const lat = toNumberSafe(pt.lat);
    const lng = toNumberSafe(pt.lng);
    if (!isValidLatLng(lat, lng)) return;

    const ptDate = pt.date ? parseYMD(pt.date) : null;
    if (ptDate instanceof Date && !isNaN(ptDate)) {
        if (from && ptDate < from) return;
        if (to && ptDate > to) return;
    }

    if (animalFilter !== "all" && pt.animal !== animalFilter) return;

    count++;
  });

  return count;
}


/* --- deterministic animal color map --- */
const animalColorMap = {};
ANIMALS.forEach((animal, idx) => {
  const hue = Math.round((idx * 360) / Math.max(ANIMALS.length, 6));
  const fill = hslToHex(hue, 70, 55);
  const grad1 = hslToHex(hue, 80, 45);
  const grad2 = hslToHex((hue + 20) % 360, 80, 65);
  animalColorMap[animal] = { fill, grad1, grad2 };
});
animalColorMap['unknown'] = animalColorMap['unknown'] || { fill: '#888888', grad1: '#aaaaaa', grad2: '#dddddd' };

/* --- emoji map & counts function (GLOBAL!!) --- */
const animalEmoji = {
  dog: "🐶",
  cat: "🐱",
  monkey: "🐒",
  goat: "🐐",
  rat: "🐀",
  bat: "🦇",
  pig: "🐖",
  cow: "🐄",
  cattle: "🐄",
  snake: "🐍",
  bird: "🐦",
  chicken: "🐔"
};

function getAnimalCountsFiltered() {
  const from = dateFromEl.value ? parseYMD(dateFromEl.value) : null;
  const to = dateToEl.value ? parseYMD(dateToEl.value) : null;
  if (to) to.setHours(23,59,59,999);

  const animalFilter = animalFilterEl.value;
  const counts = {};

  RAW_POINTS.forEach(pt => {
    const lat = toNumberSafe(pt.lat);
    const lng = toNumberSafe(pt.lng);
    if (!isValidLatLng(lat, lng)) return;

    const ptDate = pt.date ? parseYMD(pt.date) : null;
    if (ptDate instanceof Date && !isNaN(ptDate)) {
      if (from && ptDate < from) return;
      if (to && ptDate > to) return;
    }

    if (animalFilter !== "all" && pt.animal !== animalFilter) return;

    const a = pt.animal || 'unknown';
    counts[a] = (counts[a] || 0) + 1;
  });

  // ensure all animals are present
  ANIMALS.forEach(a => {
    if (!counts[a]) counts[a] = 0;
  });
  if (!counts['unknown']) counts['unknown'] = 0;

  return counts;
}

/* --- Heat layers --- */
const heatLayers = {};
ANIMALS.forEach(animal => {
  heatLayers[animal] = L.heatLayer([], {
    radius: 40,
    blur: 45,
    maxZoom: 17,
    minOpacity: 0.55,
    gradient: {0.2: animalColorMap[animal].grad1, 0.6: animalColorMap[animal].grad2, 1.0: animalColorMap[animal].fill}
  });
});
const combinedHeat = L.heatLayer([], {
  radius: 55,
  blur: 60,
  maxZoom: 17,
  minOpacity: 0.6,
  gradient: {0.2: '#a0c4ff', 0.4: '#99ffcc', 0.6: '#ffe599', 0.8: '#ffb399', 1.0: '#ff6b6b'}
});

/* --- clusters --- */
const clusters = L.markerClusterGroup();
let currentMarkers = []; // marker refs

/* --- polygon placeholder --- */
let polygonLayer = null;

/* --- UI elements --- */
const dateFromEl = document.getElementById('dateFrom');
const dateToEl = document.getElementById('dateTo');
const animalFilterEl = document.getElementById('animalFilter');
const applyBtn = document.getElementById('applyFiltersBtn');
const resetBtn = document.getElementById('resetFiltersBtn');
const toggleClustersEl = document.getElementById('toggleClusters');
const togglePolygonsEl = document.getElementById('togglePolygons');

/* defaults */
dateFromEl.value = MIN_DATE || '';
dateToEl.value = MAX_DATE || '';

/* populate animal dropdown */
(function populateAnimalFilter() {
  const seen = new Set();
  const animalsSorted = ANIMALS.slice().sort();
  animalsSorted.forEach(a => {
    if (seen.has(a)) return;
    seen.add(a);
    const opt = document.createElement('option');
    opt.value = a;
    opt.textContent = a.charAt(0).toUpperCase() + a.slice(1);
    animalFilterEl.appendChild(opt);
  });
})();

/* --- marker icon helper --- */
function createMarkerIcon(color) {
  const html = `<div class="marker-divicon" style="background:${color};"></div>`;
  return L.divIcon({ html, className: '', iconSize: [16,16], iconAnchor: [8,8] });
}

/* --- rebuild layers (main) --- */
function rebuildLayers() {
  const animalFilter = animalFilterEl.value;
  const from = dateFromEl.value ? parseYMD(dateFromEl.value) : null;
  const to = dateToEl.value ? parseYMD(dateToEl.value) : null;
  if (to) to.setHours(23,59,59,999);

  // clear heat layers
  Object.values(heatLayers).forEach(h => h.setLatLngs([]));
  combinedHeat.setLatLngs([]);
  if (getFilteredPointCount() === 0) {
      // Reset heat layers without animation
      Object.values(heatLayers).forEach(h => h.setLatLngs([]));
      combinedHeat.setLatLngs([]);
  }

  // clear clusters & markers
  clusters.clearLayers();
  currentMarkers.forEach(m => m.remove && m.remove());
  currentMarkers = [];

  RAW_POINTS.forEach(pt => {
    const lat = toNumberSafe(pt.lat);
    const lng = toNumberSafe(pt.lng);
    if (!isValidLatLng(lat, lng)) return;

    const ptDate = pt.date ? parseYMD(pt.date) : null;
    if (ptDate instanceof Date && !isNaN(ptDate)) {
      if (from && ptDate < from) return;
      if (to && ptDate > to) return;
    }

    if (animalFilter !== 'all' && pt.animal !== animalFilter) return;

    const intensity = 0.9;
    const arr = [lat, lng, intensity];

    if (pt.animal && heatLayers[pt.animal]) {
      heatLayers[pt.animal].addLatLng(arr);
    }
    combinedHeat.addLatLng(arr);

    const color = (animalColorMap[pt.animal] && animalColorMap[pt.animal].fill) || animalColorMap['unknown'].fill;
    const marker = L.marker([lat, lng], { icon: createMarkerIcon(color) });
    let popupText = `<strong>${(pt.animal || 'unknown').toUpperCase()}</strong>`;
    if (pt.date) popupText += `<br/><small>${pt.date}</small>`;
    if (pt.barangay) popupText += `<br/><em>${pt.barangay}</em>`;
    marker.bindPopup(popupText);
    currentMarkers.push(marker);
    clusters.addLayer(marker);
  });

  // remove existing heat layers to avoid duplicates
  Object.keys(heatLayers).forEach(a => {
    if (map.hasLayer(heatLayers[a])) map.removeLayer(heatLayers[a]);
  });
  if (map.hasLayer(combinedHeat)) map.removeLayer(combinedHeat);

  // add proper heat layer(s)
  if (animalFilter === 'all') {
    combinedHeat.addTo(map);
  } else {
    if (heatLayers[animalFilter]) heatLayers[animalFilter].addTo(map);
    else combinedHeat.addTo(map);
  }

  // clusters toggle
  if (toggleClustersEl.checked && currentMarkers.length > 0) map.addLayer(clusters);
  else if (map.hasLayer(clusters)) map.removeLayer(clusters);

  // polygon styles refresh
  if (getFilteredPointCount() > 0) {
      refreshPolygons();
  } else {
      console.warn("⚠ No filtered points — polygon refresh skipped");
  }

  // refresh animal legend
  if (animalLegend) {
    animalLegend.remove();
    animalLegend.addTo(map);
  }
}

/* --- load polygons --- */
async function loadPolygons() {
  try {
    const res = await fetch(GEOJSON_URL);
    if (!res.ok) throw new Error('GeoJSON not found');
    const geojson = await res.json();

    if (polygonLayer) {
      map.removeLayer(polygonLayer);
      polygonLayer = null;
    }

    polygonLayer = L.geoJSON(geojson, {
      style: feature => {
        // always transparent fill per request
        return {
          fillColor: '#000000',
          color: "#222",
          weight: 1,
          fillOpacity: 0,
          opacity: 0.8
        };
      },
      onEachFeature: (feature, layer) => {
        layer.on('click', () => {
          const barangay = feature.properties.Barangay || feature.properties.name;
          const stats = computeBarangayStats(barangay);
          const total = Object.values(stats.counts).reduce((a,b)=>a+b,0);

          let popupHtml = `<strong>${barangay}</strong><br/>`;
          popupHtml += `<strong>Total:</strong> ${total}<br/><hr/>`;

          const animalsSorted = Object.keys(stats.counts).sort((x,y) => stats.counts[y] - stats.counts[x]);
          animalsSorted.forEach(animal => {
            const count = stats.counts[animal] || 0;
            if (count === 0) return;
            const color = (animalColorMap[animal] && animalColorMap[animal].fill) || animalColorMap['unknown'].fill;
            popupHtml += `<div style="margin-top:6px;"><span class="animal-dot" style="background:${color}"></span><strong>${animal.toUpperCase()}:</strong> ${count}</div>`;

            const uniqueDates = Array.from(new Set(stats.dates[animal] || [])).sort();
            popupHtml += `<div class="date-list">${ uniqueDates.length ? uniqueDates.map(d=>`<div>${d}</div>`).join('') : '<div>— none —</div>'}</div>`;
          });

          layer.bindPopup(popupHtml, {maxWidth: 360}).openPopup();
        });
      }
    });

    if (togglePolygonsEl.checked) polygonLayer.addTo(map);

  } catch (err) {
    console.error('Failed to load polygons', err);
  }
}

/* --- compute barangay stats --- */
function computeBarangayStats(barangay) {
  const from = dateFromEl.value ? parseYMD(dateFromEl.value) : null;
  const to = dateToEl.value ? parseYMD(dateToEl.value) : null;
  if (to) to.setHours(23,59,59,999);
  const animalFilter = animalFilterEl.value;

  const counts = {};
  const dates = {};

  RAW_POINTS.forEach(pt => {
    if (!pt.barangay) return;
    if (pt.barangay !== barangay) return;

    const lat = toNumberSafe(pt.lat);
    const lng = toNumberSafe(pt.lng);
    if (!isValidLatLng(lat, lng)) return;

    const ptDate = pt.date ? parseYMD(pt.date) : null;
    if (ptDate instanceof Date && !isNaN(ptDate)) {
      if (from && ptDate < from) return;
      if (to && ptDate > to) return;
    }

    if (animalFilter !== 'all' && pt.animal !== animalFilter) return;

    const a = pt.animal || 'unknown';
    counts[a] = (counts[a] || 0) + 1;
    dates[a] = dates[a] || [];
    if (pt.date) dates[a].push(pt.date);
  });

  ANIMALS.forEach(a => { if (!counts[a]) counts[a] = 0; if (!dates[a]) dates[a] = []; });
  if (!counts['unknown']) counts['unknown'] = 0;
  if (!dates['unknown']) dates['unknown'] = [];

  return { counts, dates };
}

/* --- severity color (unused for fill since polygons transparent, but used for popups) --- */
function getSeverityColor(total) {
  if (total >= 50) return '#4d0000';
  if (total >= 25) return '#b30000';
  if (total >= 15) return '#ff4d4d';
  if (total >= 8) return '#ffad33';
  if (total >= 1) return '#9be564';
  return '#d3d3d3';
}

/* --- refresh polygons safely --- */
function refreshPolygons() {
  if (!polygonLayer) return;
  if (!map.hasLayer(polygonLayer)) return;

  polygonLayer.eachLayer(layer => {
    const barangay = layer.feature.properties.Barangay || layer.feature.properties.name;
    const stats = computeBarangayStats(barangay);
    const total = Object.values(stats.counts).reduce((a,b)=>a+b,0);

    // keep transparent fill as requested, but we update other style props if needed
    layer.setStyle({
      fillColor: getSeverityColor(total),
      fillOpacity: 0,
      color: "#222",
      weight: 1,
      opacity: 0.8
    });
  });
}

/* --- Intensity legend --- */
const legend = L.control({ position: 'bottomleft' });
legend.onAdd = function () {
  const div = L.DomUtil.create('div', 'legend');
  div.innerHTML = `
    <div><strong>Intensity</strong></div>
    <i style="background:blue"></i> Low<br>
    <i style="background:cyan"></i> Mild<br>
    <i style="background:lime"></i> Moderate<br>
    <i style="background:orange"></i> High<br>
    <i style="background:red"></i> Extreme
  `;
  return div;
};
legend.addTo(map);

/* --- Animal legend (Option A) --- */
const animalLegend = L.control({ position: 'topright' });
animalLegend.onAdd = function () {
  const div = L.DomUtil.create('div', 'legend');
  div.style.minWidth = "180px";

  const counts = getAnimalCountsFiltered();
  let html = `<strong>Animal Cases</strong><br>`;

  Object.keys(counts).sort().forEach(a => {
    const emoji = animalEmoji[a] || "❓";
    const count = counts[a] || 0;
    html += `<div style="margin-bottom:4px;">${emoji} <strong>${a.toUpperCase()}</strong>: ${count}</div>`;
  });

  div.innerHTML = html;
  return div;
};
animalLegend.addTo(map);

/* --- layers control --- */
const overlayMaps = {};
ANIMALS.forEach(a => {
  const label = `🌡️ ${a.charAt(0).toUpperCase() + a.slice(1)} Heatmap`;
  overlayMaps[label] = heatLayers[a];
});
overlayMaps["🔥 Combined Heatmap"] = combinedHeat;
overlayMaps["📍 Clusters"] = clusters;
L.control.layers(null, overlayMaps, { collapsed: false }).addTo(map);

/* --- initial load --- */
loadPolygons();
rebuildLayers();

/* --- UI events --- */
applyBtn.addEventListener('click', () => {
  rebuildLayers();

  const count = getFilteredPointCount();

  if (count > 0) {
      const bounds = getFilteredBounds();
      if (
        bounds &&
        isFinite(bounds.getNorth()) &&
        isFinite(bounds.getSouth()) &&
        isFinite(bounds.getEast()) &&
        isFinite(bounds.getWest())
      ) {
        map.fitBounds(bounds, { maxZoom: 15, padding: [40,40] });
      }
  } else {
      console.warn("⚠ No points in this filter — skipping fitBounds()");
  }

  if (animalLegend) {
    animalLegend.remove();
    animalLegend.addTo(map);
  }
});

resetBtn.addEventListener('click', () => {
  dateFromEl.value = MIN_DATE;
  dateToEl.value = MAX_DATE;
  animalFilterEl.value = 'all';
  toggleClustersEl.checked = false;
  togglePolygonsEl.checked = true;
  rebuildLayers();

  if (animalLegend) {
    animalLegend.remove();
    animalLegend.addTo(map);
  }
});

toggleClustersEl.addEventListener('change', () => {
  if (toggleClustersEl.checked && currentMarkers.length > 0) map.addLayer(clusters);
  else if (map.hasLayer(clusters)) map.removeLayer(clusters);
});

togglePolygonsEl.addEventListener('change', () => {
  if (togglePolygonsEl.checked) {
    if (polygonLayer) map.addLayer(polygonLayer);
    else loadPolygons();
  } else {
    if (polygonLayer && map.hasLayer(polygonLayer)) map.removeLayer(polygonLayer);
  }
});

/* --- compute bounds from visible points --- */
function getFilteredBounds() {
  const animalFilter = animalFilterEl.value;
  const from = dateFromEl.value ? parseYMD(dateFromEl.value) : null;
  const to = dateToEl.value ? parseYMD(dateToEl.value) : null;
  if (to) to.setHours(23,59,59,999);

  const latlngs = [];

  RAW_POINTS.forEach(pt => {
    const lat = toNumberSafe(pt.lat);
    const lng = toNumberSafe(pt.lng);
    if (!isValidLatLng(lat, lng)) return;

    const ptDate = pt.date ? parseYMD(pt.date) : null;
    if (ptDate instanceof Date && !isNaN(ptDate)) {
      if (from && ptDate < from) return;
      if (to && ptDate > to) return;
    }

    if (animalFilter !== 'all' && pt.animal !== animalFilter) return;

    latlngs.push([lat, lng]);
  });

  if (latlngs.length === 0) return null;
  return L.latLngBounds(latlngs);
}

/* --- responsiveness --- */
window.addEventListener('resize', () => map.invalidateSize());
</script>

<?php include '../../layouts/footer-block.php'; ?>
