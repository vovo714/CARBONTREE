<?php
session_start();
$connect = mysqli_connect("localhost", "root", "", "carbontree") or die("Connection Failed");

// Fetch all data
$points = [];
$result = mysqli_query($connect, "SELECT * FROM tree_data ORDER BY tree_id DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $points[] = $row;
}

$highest = null;
$lowest = null;
$totalCarbon = 0;
$totalAGB = 0;
$speciesList = [];

if (!empty($points)) {
    $highest = $points[0];
    $lowest = $points[0];
    foreach ($points as $point) {
        if ($point['carbon_storage'] > $highest['carbon_storage']) $highest = $point;
        if ($point['carbon_storage'] < $lowest['carbon_storage']) $lowest = $point;
        $totalCarbon += $point['carbon_storage'];
        $totalAGB += $point['agb'];
        if (!in_array($point['name'], $speciesList)) {
            $speciesList[] = $point['name'];
        }
    }
    $average = $totalCarbon / count($points);
    $count = count($points);
} else {
    $average = 0;
    $count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Carbon Stock Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
  <style>
    #map { height: 500px; }
    .chart-container { height: 400px; max-width: 500px; margin: auto; }
    .legend {
      background: white;
      padding: 20px;
      line-height: 2.2;
      font-size: 15px;
      box-shadow: 0 0 12px rgba(0,0,0,0.25);
    }
    .legend i {
      width: 20px;
      height: 20px;
      float: left;
      margin-right: 10px;
      opacity: 0.9;
    }
    .highlight-box {
      background: linear-gradient(to top left, #f1f5f9, #dbeafe);
      border: 2px solid #cbd5e1;
      box-shadow: 0 4px 14px rgba(0,0,0,0.18);
      border-radius: 1rem;
      padding: 1.5rem;
      text-align: center;
      font-size: 1.25rem;
      font-weight: 700;
      color: #1e293b;
    }
  </style>
</head>
<body class="bg-gray-100">
  <div class="container mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold text-center mb-6">🌳 Carbon Monitoring Dashboard</h1>

    <!-- Top Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white p-6 rounded-lg shadow text-center">
        <div class="text-gray-500">Total Carbon Stock</div>
        <div class="text-3xl font-bold text-green-600"><?php echo number_format($totalCarbon, 2); ?> kg</div>
      </div>
      <div class="bg-white p-6 rounded-lg shadow text-center">
        <div class="text-gray-500">Total AGB Stock</div>
        <div class="text-3xl font-bold text-yellow-600"><?php echo number_format($totalAGB, 2); ?> kg</div>
      </div>
      <div class="bg-white p-6 rounded-lg shadow text-center">
        <div class="text-gray-500">Total Trees</div>
        <div class="text-3xl font-bold text-blue-600"><?php echo $count; ?></div>
      </div>
    </div>

    <!-- Species Dropdown -->
    <div class="mb-8">
      <label class="block mb-2 text-gray-700 font-semibold">Select Species</label>
      <select id="speciesDropdown" class="w-full p-2 rounded border">
        <option value="">-- Select a Species --</option>
        <?php foreach ($speciesList as $species): ?>
          <option value="<?= htmlspecialchars($species) ?>"><?= htmlspecialchars($species) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Carbon Stock Map -->
    <div class="bg-white rounded shadow p-6 mb-8">
      <h3 class="text-xl font-semibold mb-4">🗺️ Carbon Stock Map</h3>
      <div id="map"></div>
    </div>

    <!-- Pie Chart Section with Highlight Boxes -->
    <div class="bg-white rounded shadow p-6 mb-8">
      <h3 class="text-xl font-semibold mb-6">📊 Carbon Stock Distribution</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="highlight-box">Highest: <?= $highest ? htmlspecialchars($highest['name']) . ' - ' . $highest['carbon_storage'] . ' kg' : 'N/A' ?></div>
        <div class="highlight-box">Lowest: <?= $lowest ? htmlspecialchars($lowest['name']) . ' - ' . $lowest['carbon_storage'] . ' kg' : 'N/A' ?></div>
        <div class="highlight-box">Average: <?= number_format($average, 2) ?> kg</div>
      </div>
      <div class="chart-container">
        <canvas id="pieChart"></canvas>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded shadow p-6">
      <h3 class="text-xl font-semibold mb-4">📋 Recent Measurements</h3>
      <table class="min-w-full">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-4 py-2">Tree Name</th>
            <th class="px-4 py-2">Scientific Name</th>
            <th class="px-4 py-2">AGB (kg)</th>
            <th class="px-4 py-2">Carbon Storage (kg)</th>
            <th class="px-4 py-2">Coordinates</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($points as $p): ?>
          <tr class="border-b">
            <td class="px-4 py-2"><?= htmlspecialchars($p['name']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($p['scientific_name']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($p['agb']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($p['carbon_storage']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($p['latitude']) ?>, <?= htmlspecialchars($p['longitude']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

<script>
  const highestLabel = "<?= $highest ? htmlspecialchars($highest['name']) : 'N/A' ?>";
  const lowestLabel = "<?= $lowest ? htmlspecialchars($lowest['name']) : 'N/A' ?>";
  const highestValue = <?= $highest ? $highest['carbon_storage'] : 0 ?>;
  const lowestValue = <?= $lowest ? $lowest['carbon_storage'] : 0 ?>;
  const totalValue = <?= $totalCarbon ?>;

  const pieChart = new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: {
      labels: ['Highest: ' + highestLabel, 'Lowest: ' + lowestLabel, 'Others'],
      datasets: [{
        data: [highestValue, lowestValue, totalValue - highestValue - lowestValue],
        backgroundColor: ['#0f766e', '#be123c', '#f59e0b'],
        borderColor: '#f1f5f9',
        borderWidth: 2
      }]
    },
    options: {
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            color: '#1e293b',
            font: {
              size: 14,
              weight: 'bold'
            }
          }
        }
      }
    }
  });

  const map = L.map('map').setView([1.5, 110], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  const legend = L.control({position: 'bottomright'});
  legend.onAdd = function () {
    const div = L.DomUtil.create('div', 'legend');
    div.innerHTML = '<i style="background:#1d4ed8"></i> Low<br>' +
                    '<i style="background:#facc15"></i> Medium<br>' +
                    '<i style="background:#ef4444"></i> High';
    return div;
  };
  legend.addTo(map);

  let geojsonLayer, heatLayer;

  function loadMap(speciesFilter = '') {
    if (geojsonLayer) geojsonLayer.remove();
    if (heatLayer) heatLayer.remove();

    fetch('tree.geojson')
      .then(res => res.json())
      .then(data => {
        const filtered = speciesFilter ? {
          ...data,
          features: data.features.filter(f => f.properties.name === speciesFilter)
        } : data;

        geojsonLayer = L.geoJSON(filtered, {
          pointToLayer: function (feature, latlng) {
            const carbon = parseFloat(feature.properties.carbon_storage || 0);
            let color = '#1d4ed8';
            if (carbon > 300) {
              color = '#ef4444';
            } else if (carbon > 100) {
              color = '#facc15';
            }
            return L.circleMarker(latlng, {
              radius: 6,
              color,
              fillColor: color,
              fillOpacity: 0.85,
              weight: 1
            });
          },
          onEachFeature: function (feature, layer) {
            const p = feature.properties;
            layer.bindPopup(`<b>${p['Tree ID']}</b><br>Name: ${p.name}<br>Scientific: ${p.scientific_name}<br>AGB: ${p.agb} kg<br>Carbon: ${p.carbon_storage} kg`);
          }
        }).addTo(map);

        const heatData = filtered.features.map(f => [
          f.geometry.coordinates[1],
          f.geometry.coordinates[0],
          parseFloat(f.properties.carbon_storage || 0) / 500
        ]);
        heatLayer = L.heatLayer(heatData, {radius: 25, blur: 15}).addTo(map);
      });
  }

  loadMap();

  document.getElementById('speciesDropdown').addEventListener('change', function () {
    loadMap(this.value);
  });
</script>
</body>
</html>
