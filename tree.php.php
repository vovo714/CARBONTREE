<?php
// Start a new session or resume an existing one. This is crucial for managing user-specific data across multiple page requests.
session_start();
// Establish a connection to the MySQL database.
// Parameters: host ('localhost'), username ('root'), password (empty), database name ('carbontree').
// The 'or die("Connection Failed")' part will stop the script and display an error message if the connection cannot be established.
$connect = mysqli_connect("localhost", "root", "", "carbontree") or die("Connection Failed");

// Initialize an empty array to store all fetched tree data.
$points = [];
// Execute a SQL query to select all records from the 'tree_data' table.
// The results are ordered by 'tree_id' in descending order, meaning the newest entries appear first.
$result = mysqli_query($connect, "SELECT * FROM tree_data ORDER BY tree_id DESC");
// Loop through each row of the query result.
// mysqli_fetch_assoc() fetches a result row as an associative array, where keys are column names.
// Each fetched row (representing a tree's data) is added to the $points array.
while ($row = mysqli_fetch_assoc($result)) {
    $points[] = $row;
}

// Initialize variables to store information about the tree with the highest and lowest carbon storage.
$highest = null;
$lowest = null;
// Initialize accumulators for total carbon storage and total Above Ground Biomass (AGB).
$totalCarbon = 0;
$totalAGB = 0;
// Initialize an array to store unique tree species names for the dropdown filter.
$speciesList = [];

// Check if the $points array is not empty. This ensures that calculations are only performed if there's actual data.
if (!empty($points)) {
    // If data exists, set the first tree in the $points array as initial values for highest and lowest.
    // This is safe because $points is guaranteed not to be empty here.
    $highest = $points[0];
    $lowest = $points[0];
    // Iterate through each tree's data in the $points array.
    foreach ($points as $point) {
        // Compare the current tree's carbon storage with the current $highest. If higher, update $highest.
        if ($point['carbon_storage'] > $highest['carbon_storage']) $highest = $point;
        // Compare the current tree's carbon storage with the current $lowest. If lower, update $lowest.
        if ($point['carbon_storage'] < $lowest['carbon_storage']) $lowest = $point;
        // Add the current tree's carbon storage to the running total.
        $totalCarbon += $point['carbon_storage'];
        // Add the current tree's AGB to the running total.
        $totalAGB += $point['agb'];
        // Check if the current tree's name (species) is already in the $speciesList.
        // If not, add it to ensure a list of unique species.
        if (!in_array($point['name'], $speciesList)) {
            $speciesList[] = $point['name'];
        }
    }
    // Calculate the average carbon storage by dividing total carbon by the number of trees.
    $average = $totalCarbon / count($points);
    // Get the total count of trees.
    $count = count($points);
} else {
    // If no data is fetched from the database, set average and count to 0 to prevent division-by-zero errors.
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
        /* CSS rule to set a fixed height for the map container. */
        #map { height: 500px; }
        /* CSS rules for chart containers, setting height and maximum width to make them responsive. */
        .chart-container { height: 400px; max-width: 500px; margin: auto; }
        /* Styles for the Leaflet map legend, including background, padding, line height, font size, and shadow. */
        .legend {
            background: white;
            padding: 20px;
            line-height: 2.2;
            font-size: 15px;
            box-shadow: 0 0 12px rgba(0,0,0,0.25);
        }
        /* Styles for the color swatches (icons) within the legend, positioning them correctly. */
        .legend i {
            width: 20px;
            height: 20px;
            float: left;
            margin-right: 10px;
            opacity: 0.9;
        }
        /* Styles for the highlight boxes used to display key statistics (highest, lowest, average). */
        /* Includes a gradient background, border, shadow, rounded corners, padding, text alignment, font size, and weight. */
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

        <div class="mb-8">
            <label class="block mb-2 text-gray-700 font-semibold">Select Species</label>
            <select id="speciesDropdown" class="w-full p-2 rounded border">
                <option value="">-- Select a Species --</option>
                <?php foreach ($speciesList as $species): ?>
                    <option value="<?= htmlspecialchars($species) ?>"><?= htmlspecialchars($species) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="bg-white rounded shadow p-6 mb-8">
            <h3 class="text-xl font-semibold mb-4">📺 Carbon Stock Map</h3>
            <div id="map"></div>
        </div>

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
    // JavaScript variables are populated with PHP data. These will be used by Chart.js and Leaflet.
    const highestLabel = "<?= $highest ? htmlspecialchars($highest['name']) : 'N/A' ?>";
    const lowestLabel = "<?= $lowest ? htmlspecialchars($lowest['name']) : 'N/A' ?>";
    const highestValue = <?= $highest ? $highest['carbon_storage'] : 0 ?>;
    const lowestValue = <?= $lowest ? $lowest['carbon_storage'] : 0 ?>;
    const totalValue = <?= $totalCarbon ?>;

    // Initialize the pie chart using Chart.js.
    const pieChart = new Chart(document.getElementById('pieChart'), {
        type: 'pie', // Specifies the chart type as 'pie'.
        data: {
            // Labels for the pie chart segments: Highest carbon tree, Lowest carbon tree, and all Others.
            labels: ['Highest: ' + highestLabel, 'Lowest: ' + lowestLabel, 'Others'],
            datasets: [{
                // Data values for each segment. 'Others' is calculated as total minus highest and lowest.
                data: [highestValue, lowestValue, totalValue - highestValue - lowestValue],
                // Background colors for the pie chart segments.
                backgroundColor: ['#16a34a', '#ef4444', '#f97316'],
                // Border color and width for the segments.
                borderColor: '#f1f5f9',
                borderWidth: 2
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom', // Position the legend at the bottom of the chart.
                    labels: {
                        color: '#1e293b', // Color of the legend labels.
                        font: {
                            size: 14,    // Font size of the legend labels.
                            weight: 'bold' // Font weight of the legend labels.
                        }
                    }
                }
            }
        }
    });

    // Initialize the Leaflet map.
    // 'map' is the ID of the HTML div element where the map will be displayed.
    // setView([latitude, longitude], zoom_level) sets the initial geographical center and zoom.
    // minZoom and maxZoom define the allowable zoom levels.
    const map = L.map('map', {zoomControl: true, minZoom: 5, maxZoom: 18}).setView([1.5, 110], 6);
    // Add OpenStreetMap tile layer to the map. This provides the base map imagery.
    // '{s}' is a placeholder for subdomains, '{z}' for zoom level, '{x}' and '{y}' for tile coordinates.
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors' // Required attribution for OpenStreetMap.
    }).addTo(map);

    // Create a custom legend control for the map and position it at the bottom right.
    const legend = L.control({position: 'bottomright'});
    // Define what happens when the legend is added to the map.
    legend.onAdd = function () {
        // Create a div element for the legend content.
        const div = L.DomUtil.create('div', 'legend');
        // Set the HTML content of the legend, including color swatches and descriptive labels.
        // Note: The legend colors typically represent the visual mapping on the map. Here, 'High' is shown as green, matching the heatmap's highest intensity, even though tree points might use yellow.
        div.innerHTML = '<i style="background:#ef4444"></i> Low<br>' +
                        '<i style="background:#f97316"></i> Medium<br>' +
                        '<i style="background:#22c55e"></i> High'; // This specific color in the legend HTML is green (#22c55e)
        return div;
    };
    // Add the custom legend to the map.
    legend.addTo(map);

    // Declare variables to hold the GeoJSON and Heatmap layers.
    // These are declared outside the `loadMap` function so they can be accessed and removed globally when the map updates.
    let geojsonLayer, heatLayer;

    // Function to load and display map data, with an optional species filter.
    function loadMap(speciesFilter = '') {
        // If a GeoJSON layer already exists on the map, remove it before adding new data.
        if (geojsonLayer) geojsonLayer.remove();
        // If a Heatmap layer already exists, remove it.
        if (heatLayer) heatLayer.remove();

        // Fetch the tree data from a 'tree.geojson' file.
        fetch('tree.geojson')
            .then(res => res.json()) // Parse the JSON response.
            .then(data => {
                // Filter the GeoJSON features based on the `speciesFilter` if it's provided.
                // If `speciesFilter` is empty, all data features are included.
                const filtered = speciesFilter ? {
                    ...data, // Spreads all properties of the original data object.
                    features: data.features.filter(f => f.properties.name === speciesFilter) // Filters the 'features' array.
                } : data; // If no filter, use the original unfiltered data.

                // Add the filtered GeoJSON data to the map as markers/circles.
                geojsonLayer = L.geoJSON(filtered, {
                    // This function determines how each point feature (tree) is rendered on the map.
                    pointToLayer: function (feature, latlng) {
                        // Get the carbon storage value for the current tree, defaulting to 0 if not present.
                        const carbon = parseFloat(feature.properties.carbon_storage || 0);
                        let color = '#ef4444'; // Default color for low carbon (red).
                        // Conditional logic to assign a color based on carbon storage value.
                        if (carbon > 300) {
                            color = '#facc15'; // **Changed to yellow (#facc15) for high carbon.**
                        } else if (carbon > 100) {
                            color = '#f97316'; // Orange for medium carbon.
                        }
                        // Return a Leaflet circle marker with specified styling.
                        return L.circleMarker(latlng, {
                            radius: 6, // Size of the circle.
                            color,     // Border color of the circle.
                            fillColor: color, // Fill color of the circle.
                            fillOpacity: 0.85, // Opacity of the fill color.
                            weight: 1 // Weight of the border.
                        });
                    },
                    // This function defines actions to be performed for each feature, like binding a popup.
                    onEachFeature: function (feature, layer) {
                        const p = feature.properties; // Access the properties of the current feature.
                        // Bind a popup to the layer, displaying detailed information when clicked.
                        layer.bindPopup(`<b>${p['Tree ID']}</b><br>Name: ${p.name}<br>Scientific: ${p.scientific_name}<br>AGB: ${p.agb} kg<br>Carbon: ${p.carbon_storage} kg`);
                    }
                }).addTo(map); // Add the GeoJSON layer to the map.

                // Prepare the data for the heatmap layer.
                // It's an array of [latitude, longitude, intensity] arrays.
                // Carbon storage is normalized by dividing by 300 to scale it for heatmap intensity.
                const heatData = filtered.features.map(f => [
                    f.geometry.coordinates[1], // Latitude (y-coordinate)
                    f.geometry.coordinates[0], // Longitude (x-coordinate)
                    parseFloat(f.properties.carbon_storage || 0) / 300 // Carbon storage as intensity
                ]);
                // Create and add the heatmap layer to the map.
                heatLayer = L.heatLayer(heatData, {
                    radius: 30, // Radius of the heatmap "blot".
                    blur: 18,   // Amount of blur applied to the heatmap points.
                    gradient: { // Color gradient used for the heatmap.
                        0.0: '#ef4444', // Red for low intensity.
                        0.5: '#f97316', // Orange for medium intensity.
                        1.0: '#22c55e'  // Green for high intensity. (This remains green for the heatmap, as requested for *only* the points to change to yellow)
                    }
                }).addTo(map); // Add the heatmap layer to the map.
            });
    }

    // Call loadMap initially when the page loads to display all tree data.
    loadMap();

    // Add an event listener to the species dropdown.
    // When the selected value changes, call `loadMap` with the new selected species.
    document.getElementById('speciesDropdown').addEventListener('change', function () {
        loadMap(this.value); // `this.value` gets the currently selected option's value.
    });
</script>
</body>
</html>
