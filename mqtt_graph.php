
<?php
/*
 * EE470 MQTT Assignment - Section 4.2F2
 * MQTT Potentiometer Sensor Dashboard
 *
 * Displays potentiometer readings stored
 * in the Hostinger MySQL database.
 *
 * ESP8266 -> HiveMQ -> Python -> MySQL
 */

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

// Reuse existing database connection
// Same connection used in sensor_display.php
require_once dirname(__DIR__) . '/ee470_config.php';

// Retrieve TIMESTAMP values in UTC
$db->exec("SET time_zone = '+00:00'");

// California time zone
$californiaTimeZone = new DateTimeZone(
    'America/Los_Angeles'
);

// Convert a UTC timestamp to California time
function californiaTime($timestamp) {

    global $californiaTimeZone;

    if (empty($timestamp)) {
        return "No timestamp";
    }

    $date = new DateTime(
        $timestamp,
        new DateTimeZone('UTC')
    );

    $date->setTimezone($californiaTimeZone);

    return $date->format('Y-m-d h:i:s A T');
}

// Retrieve the latest 50 MQTT readings
$stmt = $db->query(
    "SELECT id, potentiometer_value, time_received
     FROM mqtt_sensor_data
     ORDER BY id DESC
     LIMIT 50"
);

$readings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Arrange data from oldest to newest
$readings = array_reverse($readings);

// Prepare graph data
$times = [];
$values = [];

foreach ($readings as $row) {

    // Convert UTC timestamp to California time
    $times[] = californiaTime(
        $row['time_received']
    );

    // Store potentiometer value
    $values[] = (int)$row['potentiometer_value'];
}

// Latest sensor information
$latestValue = count($values) > 0
    ? end($values)
    : "No data";

$latestTime = count($times) > 0
    ? end($times)
    : "No readings yet";

// Total readings in database
$totalStmt = $db->query(
    "SELECT COUNT(*) FROM mqtt_sensor_data"
);

$totalReadings = (int)$totalStmt->fetchColumn();

// Escape text before displaying in HTML
function safe($value) {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>EE470 MQTT Sensor Dashboard</title>

    <!-- Chart.js library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #243247;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #667085;
            margin-bottom: 30px;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .stat-card {
            background: #eef5e8;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 15px;
            margin: 0 0 12px;
        }

        .stat-card p {
            font-size: 26px;
            font-weight: bold;
            margin: 0;
            color: #3f7a30;
        }

        .chart-box {
            position: relative;
            height: 380px;
            width: 100%;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }

        th {
            background: #78a85a;
            color: white;
        }

        tr:nth-child(even) {
            background: #eef5e8;
        }

        .center {
            text-align: center;
        }

        button {
            background: #78a85a;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 15px;
            cursor: pointer;
        }

        button:hover {
            background: #639247;
        }

        .info {
            line-height: 1.8;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .section {
                padding: 15px;
            }

            .chart-box {
                height: 280px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>EE470 MQTT Sensor Dashboard</h1>

    <p class="subtitle">
        ESP8266 Potentiometer Monitoring Using HiveMQ
    </p>

    <!-- Latest sensor information -->
    <div class="section">

        <h2 class="center">Latest Sensor Information</h2>

        <div class="stats">

            <div class="stat-card">
                <h3>Latest Potentiometer Value</h3>
                <p><?= safe($latestValue) ?></p>
            </div>

            <div class="stat-card">
                <h3>Total Stored Readings</h3>
                <p><?= $totalReadings ?></p>
            </div>

        </div>

        <p class="center">
            <strong>Last Received:</strong>
            <?= safe($latestTime) ?>
        </p>

        <p class="center">
            ADC Range: 0 - 1023
        </p>

    </div>

    <!-- MQTT graph -->
    <div class="section">

        <h2 class="center">
            Potentiometer Readings Over Time
        </h2>

        <div class="chart-box">
            <canvas id="mqttChart"></canvas>
        </div>

    </div>

    <!-- Sensor data table -->
    <div class="section">

        <h2 class="center">
            MQTT Sensor Measurements
        </h2>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Potentiometer Value</th>
                        <th>Time Received (Pacific)</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($readings as $row): ?>

                    <tr>
                        <td>
                            <?= (int)$row['id'] ?>
                        </td>

                        <td>
                            <?= (int)$row['potentiometer_value'] ?>
                        </td>

                        <td>
                            <?= safe(
                                californiaTime(
                                    $row['time_received']
                                )
                            ) ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                <?php if (empty($readings)): ?>

                    <tr>
                        <td colspan="3">
                            No MQTT readings available.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <!-- MQTT system information -->
    <div class="section">

        <h2 class="center">MQTT System Information</h2>

        <div class="info">

            <p>
                <strong>Device:</strong>
                ESP8266 NodeMCU
            </p>

            <p>
                <strong>Sensor:</strong>
                3296 Potentiometer
            </p>

            <p>
                <strong>Broker:</strong>
                broker.hivemq.com
            </p>

            <p>
                <strong>Protocol:</strong>
                MQTT
            </p>

            <p>
                <strong>Publish Interval:</strong>
                15 seconds
            </p>

            <p>
                <strong>Database:</strong>
                Hostinger MySQL
            </p>

            <p>
                <strong>Relay:</strong>
                Python MQTT Subscriber
            </p>

            <p>
                <strong>Time Zone:</strong>
                Pacific Time (PST/PDT)
            </p>

        </div>

        <div class="center">
            <button onclick="location.reload()">
                Refresh Data
            </button>
        </div>

    </div>

</div>

<script>

// MQTT timestamps and values from PHP
const mqttTimes = <?= json_encode(
    $times,
    JSON_HEX_TAG | JSON_HEX_AMP |
    JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const mqttValues = <?= json_encode($values) ?>;

// Create potentiometer graph
new Chart(
    document.getElementById('mqttChart'),
    {
        type: 'line',

        data: {
            labels: mqttTimes,

            datasets: [{
                label: 'Potentiometer Reading',
                data: mqttValues,
                borderColor: '#78a85a',
                backgroundColor: 'rgba(120,168,90,0.15)',
                borderWidth: 2,
                tension: 0.2,
                fill: true,
                pointRadius: 4
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Time Received (Pacific Time)'
                    }
                },

                y: {
                    min: 0,
                    max: 1023,

                    title: {
                        display: true,
                        text: 'Potentiometer Value (ADC)'
                    }
                }
            }
        }
    }
);

</script>

</body>
</html>
