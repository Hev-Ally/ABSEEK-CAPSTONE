<?php
// public_html/index.php
// Homepage with admin heatmap (no date filters, no clusters, combined heat default)

?><!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">

<head>
  <title>Animal Bite Monitoring System</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="description" content="Animal Bite Monitoring System" />
  <meta name="keywords" content="Animal Bite Monitoring System" />
  <meta name="author" content="Thesis" />
  <link rel="icon" href="assets/images/favicon.svg" type="image/x-icon" />

  <!-- existing CSS -->
  <link href="./assets/css/plugins/animate.min.css" rel="stylesheet" type="text/css" />
  <link href="assets/css/plugins/swiper-bundle.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/fonts/phosphor/duotone/style.css" />
  <link rel="stylesheet" href="assets/fonts/tabler-icons.min.css" />
  <link rel="stylesheet" href="assets/fonts/feather.css" />
  <link rel="stylesheet" href="assets/fonts/fontawesome.css" />
  <link rel="stylesheet" href="assets/fonts/material.css" />
  <link rel="stylesheet" href="assets/css/style.css" id="main-style-link" />

  <style>
    .button-group { margin-bottom: 30px; }
    .btn { padding: 10px 20px; margin-right: 10px; background-color: #007bff; color: white; border: none; border-radius: 5px; text-decoration: none; }
    /* Legend styling (map control) */
    .legend { background: white; padding: 10px; line-height: 1.5; font-size: 14px; box-shadow:0 1px 6px rgba(0,0,0,0.08); color: #111; }
    .legend i { width: 18px; height: 18px; float: left; margin-right: 8px; opacity: 0.8; display:inline-block; }
    /* Card text -> black so it’s visible on white background map card */
    .map-card { color: #111; }
    /* simple footer */
    footer.site-footer {
      background: #0b2236;
      color: #fff;
      padding: 18px 0;
      text-align: center;
      margin-top: 30px;
    }
    footer.site-footer a { color: #fff; text-decoration: underline; }
  </style>
</head>

<body style="background: #3f4d67;">
  <!-- [ Pre-loader ] start -->
  <div class="loader-bg fixed inset-0 dark:bg-themedark-cardbg z-[1034]">
    <div class="loader-track h-[5px] w-full inline-block absolute overflow-hidden top-0">
      <div class="loader-fill w-[300px] h-[5px] bg-primary-500 absolute top-0 left-0 animate-[hitZak_0.6s_ease-in-out_infinite_alternate]"></div>
    </div>
  </div>
  <!-- [ Pre-loader ] End -->

  <!-- [ Header ] start -->
  <header id="home" class="flex items-center flex-col justify-center overflow-hidden relative pt-[100px] sm:pt-[180px] pb-0 bg-theme-sidebarbg dark:bg-dark-500">
    <!-- [ Nav ] start -->
    <nav class="navbar group bg-theme-sidebarbg dark:bg-themedark-cardbg absolute top-0 z-90 w-full backdrop-blur">
      <div class="container">
        <div class="static flex py-4 items-center justify-between sm:relative">
          <div class="flex flex-1 items-center justify-center sm:items-stretch sm:justify-between">
            <div class="flex flex-shrink-0 items-center justify-between">
              <a href="#">
                <img class="w-[130px]" src="assets/images/logo-white.svg" alt="Your Company" />
              </a>
            </div>
            <div class="grow">
              <div class="justify-end flex flex-row space-x-2 p-0 me-3">
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>
    <!-- [ Nav ] end -->

    <div class="container relative z-10">
      <div class="w-full md:w-10/12 text-center mx-auto">
        <h1 class="text-white text-[22px] md:text-[36px] lg:text-[48px] leading-[1.2] mb-5 wow animate__fadeInUp" data-wow-delay="0.2s">
          Welcome to
          <span class="text-transparent font-semibold bg-clip-text bg-gradient-to-r from-[rgb(37,161,244)] via-[rgb(249,31,169)] to-[rgb(37,161,244)]">Animal Bite</span>
          Monitoring System
        </h1>

        <div class="wow animate__fadeInUp" data-wow-delay="0.3s">
          <div class="sm:w-8/12 mx-auto">
            <p class="text-white/80 text-[14px] sm:text-[16px] mb-0">
              The objective of ABSEEK is to develop a community-based monitoring system that enables LFG ANIMAL BITE CENTER of Sariaya, Quezon to efficiently record, analyze, and visualize animal bite incidents.
            </p>
          </div>
        </div>

        <div class="my-5 sm:my-12 wow animate__fadeInUp" data-wow-delay="0.4s">
          <div class="button-group flex flex-col lg:flex-row mx-auto gap-3 items-center justify-center">
            <a href="./pages/register.php" class="btn">Register</a>
            <a href="./pages/login.php" class="btn">Login</a>
            <a href="./dashboard/community/reports/report-incident.php" class="btn">Report an Incident</a>
            <a href="./android/app/lfgabc.apk" class="btn">Download Andriod App</a>
          </div>
        </div>
      </div>
    </div>
  </header>
  <!-- [ Header ] End -->

  <?php
  // -------------------------------
  // Load heatmap data (same logic as admin)
  // -------------------------------
  include_once('./assets/db/db.php');
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  // fetch animals
  $animals = [];
  try {
      $sqlA = "SELECT DISTINCT LOWER(ba.animal_name) AS animal
               FROM reports r
               LEFT JOIN biting_animal ba ON ba.biting_animal_id = r.biting_animal_id
               WHERE ba.animal_name IS NOT NULL
               ORDER BY animal";
      $stmtA = $conn->prepare($sqlA);
      $stmtA->execute();
      $resA = $stmtA->get_result();
      while ($row = $resA->fetch_assoc()) {
          $a = trim(strtolower($row['animal'] ?? 'unknown'));
          if ($a === '') $a = 'unknown';
          $animals[] = $a;
      }
  } catch (Exception $e) {
      $animals = ['dog','cat'];
  }

  // fetch points
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

  $heatPoints = [];
  while ($row = $res->fetch_assoc()) {
      $lat = isset($row['lat']) ? trim($row['lat']) : null;
      $lng = isset($row['lng']) ? trim($row['lng']) : null;
      $animal = isset($row['animal']) ? strtolower(trim($row['animal'])) : 'unknown';
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
  }

  $animalsJson = json_encode(array_values(array_unique($animals)));
  $heatPointsJson = json_encode($heatPoints);
  // geojson path (relative)
  $geojsonUrlJs = json_encode('./assets/frontend/Sariaya.geojson');
  ?>

  <!-- Main content: heatmap card -->
  <main class="container mt-8 mb-12">
    <div class="card p-5 map-card rounded">
      <h3 class="mb-3">📊 Animal Bite Heatmap — Combined (All reports)</h3>

      <div style="margin:10px 0 18px;">
        <label for="animalFilter" style="font-weight:600; margin-right:8px;">Animal:</label>
        <select id="animalFilter" class="form-control" style="max-width:260px; display:inline-block;">
          <option value="all">All (combined)</option>
          <!-- options injected by JS -->
        </select>
      </div>

      <div id="map" style="height:560px; width:100%; border-radius:6px; overflow:hidden;"></div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="container">
      <div>© <?= date('Y') ?> LFG Animal Bite Center — Data-driven public health.</div>
    </div>
  </footer>

  <!-- Leaflet + heat plugin (no markercluster) -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>

  <script>
    // Data from PHP
    const ANIMALS = <?= $animalsJson ?>;
    const RAW_POINTS = <?= $heatPointsJson ?>;
    const GEOJSON_URL = <?= $geojsonUrlJs ?>;

    // Map setup
    const map = L.map('map').setView([13.964, 121.527], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // small helpers
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

    // build color map
    const animalColorMap = {};
    ANIMALS.forEach((animal, idx) => {
      const hue = Math.round((idx * 360) / Math.max(ANIMALS.length, 6));
      const fill = hslToHex(hue, 70, 55);
      const grad1 = hslToHex(hue, 80, 45);
      const grad2 = hslToHex((hue + 20) % 360, 80, 65);
      animalColorMap[animal] = { fill, grad1, grad2 };
    });
    animalColorMap['unknown'] = animalColorMap['unknown'] || { fill: '#888888', grad1: '#aaaaaa', grad2: '#dddddd' };

    // create heat layers per animal + combined heat
    const heatLayers = {};
    ANIMALS.forEach(animal => {
      heatLayers[animal] = L.heatLayer([], {
        radius: 40,
        blur: 45,
        maxZoom: 17,
        minOpacity: 0.55,
        gradient: {
          0.2: animalColorMap[animal].grad1,
          0.6: animalColorMap[animal].grad2,
          1.0: animalColorMap[animal].fill
        }
      });
    });

    const combinedHeat = L.heatLayer([], {
      radius: 55,
      blur: 60,
      maxZoom: 17,
      minOpacity: 0.6,
      gradient: {0.2: '#a0c4ff', 0.4: '#99ffcc', 0.6: '#ffe599', 0.8: '#ffb399', 1.0: '#ff6b6b'}
    });

    // populate animal dropdown
    const animalFilterEl = document.getElementById('animalFilter');
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

    // load polygons (transparent fill) and add to map
    let polygonLayer = null;
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
          style: () => ({ fillColor: '#000', color: "#222", weight: 1, fillOpacity: 0, opacity: 0.8 }),
          onEachFeature: (feature, layer) => {
            layer.on('click', () => {
              const barangay = feature.properties.Barangay || feature.properties.name;
              const stats = computeBarangayStats(barangay);
              const total = Object.values(stats.counts).reduce((a,b)=>a+b,0);

              let popupHtml = `<strong>${barangay}</strong><br/><strong>Total:</strong> ${total}<br/><hr/>`;
              const animalsSorted = Object.keys(stats.counts).sort((x,y)=>stats.counts[y] - stats.counts[x]);
              animalsSorted.forEach(animal => {
                const count = stats.counts[animal] || 0;
                if (count === 0) return;
                const color = (animalColorMap[animal] && animalColorMap[animal].fill) || animalColorMap['unknown'].fill;
                popupHtml += `<div style="margin-top:6px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};margin-right:6px;"></span><strong>${animal.toUpperCase()}:</strong> ${count}</div>`;
                const uniqueDates = Array.from(new Set(stats.dates[animal] || [])).sort();
                popupHtml += `<div style="font-size:12px;margin-top:6px;">${ uniqueDates.length ? uniqueDates.map(d=>`<div>${d}</div>`).join('') : '<div>— none —</div>'}</div>`;
              });

              layer.bindPopup(popupHtml, {maxWidth:360}).openPopup();
            });
          }
        });

        // add by default
        if (polygonLayer) polygonLayer.addTo(map);
      } catch (err) {
        console.error('Failed to load polygons', err);
      }
    }

    // compute barangay stats from filtered points
    function computeBarangayStats(barangay) {
      const animalFilter = animalFilterEl.value;
      const counts = {};
      const dates = {};

      RAW_POINTS.forEach(pt => {
        if (!pt.barangay) return;
        if (pt.barangay !== barangay) return;
        const lat = toNumberSafe(pt.lat), lng = toNumberSafe(pt.lng);
        if (!isValidLatLng(lat, lng)) return;
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

    // add intensity legend (bottomleft)
    const legend = L.control({ position: 'bottomleft' });
    legend.onAdd = function () {
      const div = L.DomUtil.create('div', 'legend');
      div.innerHTML = `<div style="font-weight:700;margin-bottom:6px;">Intensity</div>
        <i style="background:blue"></i> Low<br>
        <i style="background:cyan"></i> Mild<br>
        <i style="background:lime"></i> Moderate<br>
        <i style="background:orange"></i> High<br>
        <i style="background:red"></i> Extreme`;
      return div;
    };
    legend.addTo(map);

    // add animal legend (topright)
    const animalLegend = L.control({ position: 'topright' });
    animalLegend.onAdd = function () {
      const div = L.DomUtil.create('div', 'legend');
      div.style.minWidth = "180px";
      const counts = getAnimalCountsFiltered();
      let html = `<strong>Animal Cases</strong><br>`;
      Object.keys(counts).sort().forEach(a => {
        const emojiMap = {
          dog: "🐶", cat: "🐱", monkey: "🐒", goat: "🐐", rat: "🐀", bat: "🦇",
          pig: "🐖", cow: "🐄", snake: "🐍", bird: "🐦", chicken: "🐔"
        };
        const emoji = emojiMap[a] || "❓";
        const count = counts[a] || 0;
        html += `<div style="margin-bottom:4px;">${emoji} <strong>${a.toUpperCase()}</strong>: ${count}</div>`;
      });
      div.innerHTML = html;
      return div;
    };
    animalLegend.addTo(map);

    // returns counts of animals given current filter
    function getAnimalCountsFiltered() {
      const animalFilter = animalFilterEl.value;
      const counts = {};
      RAW_POINTS.forEach(pt => {
        const lat = toNumberSafe(pt.lat), lng = toNumberSafe(pt.lng);
        if (!isValidLatLng(lat, lng)) return;
        if (animalFilter !== 'all' && pt.animal !== animalFilter) return;
        const a = pt.animal || 'unknown';
        counts[a] = (counts[a] || 0) + 1;
      });
      ANIMALS.forEach(a => { if (!counts[a]) counts[a] = 0; });
      if (!counts['unknown']) counts['unknown'] = 0;
      return counts;
    }

    // rebuild layers: populate heat layers and the combined heat
    function rebuildLayers() {
      const animalFilter = animalFilterEl.value;

      // clear
      Object.values(heatLayers).forEach(h => h.setLatLngs([]));
      combinedHeat.setLatLngs([]);

      // add points
      RAW_POINTS.forEach(pt => {
        const lat = toNumberSafe(pt.lat), lng = toNumberSafe(pt.lng);
        if (!isValidLatLng(lat, lng)) return;
        if (animalFilter !== 'all' && pt.animal !== animalFilter) return;

        const intensity = 0.9;
        const arr = [lat, lng, intensity];

        if (pt.animal && heatLayers[pt.animal]) {
          heatLayers[pt.animal].addLatLng(arr);
        }
        combinedHeat.addLatLng(arr);
      });

      // remove heat layers if present
      Object.keys(heatLayers).forEach(a => { if (map.hasLayer(heatLayers[a])) map.removeLayer(heatLayers[a]); });
      if (map.hasLayer(combinedHeat)) map.removeLayer(combinedHeat);

      // default: show combined heat
      combinedHeat.addTo(map);

      // refresh animal legend
      if (animalLegend) {
        animalLegend.remove();
        animalLegend.addTo(map);
      }
    }

    // initial load
    loadPolygons();
    rebuildLayers();

    // update map view to bounds of points (if any)
    (function fitToPoints() {
      const latlngs = [];
      RAW_POINTS.forEach(pt => {
        const lat = toNumberSafe(pt.lat), lng = toNumberSafe(pt.lng);
        if (!isValidLatLng(lat, lng)) return;
        latlngs.push([lat, lng]);
      });
      if (latlngs.length > 0) {
        try {
          const bounds = L.latLngBounds(latlngs);
          map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
        } catch (e) {
          console.warn('fitBounds failed', e);
        }
      }
    })();

    // animal filter change
    animalFilterEl.addEventListener('change', () => {
      rebuildLayers();
      if (polygonLayer) {
        // redraw polygon popups content by re-adding legend
        if (animalLegend) {
          animalLegend.remove();
          animalLegend.addTo(map);
        }
      }
    });

    // responsiveness
    window.addEventListener('resize', () => map.invalidateSize());
  </script>

  <!-- site scripts -->
  <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
  <script src="./assets/js/plugins/simplebar.min.js"></script>
  <script src="./assets/js/plugins/popper.min.js"></script>
  <script src="./assets/js/icon/custom-icon.js"></script>
  <script src="./assets/js/plugins/feather.min.js"></script>
  <script src="./assets/js/component.js"></script>
  <script src="./assets/js/theme.js"></script>
  <script src="./assets/js/script.js"></script>
  <script src="./assets/js/plugins/wow.min.js"></script>
  <script>
    let ost = 0;
    document.addEventListener('scroll', function () {
      let cOst = document.documentElement.scrollTop;
      if (cOst == 0) {
        document.querySelector('.navbar').classList.add('!bg-transparent');
      } else if (cOst > ost) {
        document.querySelector('.navbar').classList.add('top-nav-collapse');
        document.querySelector('.navbar').classList.remove('default');
        document.querySelector('.navbar').classList.remove('!bg-transparent');
      } else {
        document.querySelector('.navbar').classList.add('default');
        document.querySelector('.navbar').classList.remove('top-nav-collapse');
        document.querySelector('.navbar').classList.remove('!bg-transparent');
      }
      ost = cOst;
    });
    var wow = new WOW({ animateClass: 'animate__animated' });
    wow.init();
  </script>

</body>
</html>
