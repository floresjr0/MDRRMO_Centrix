<?php
// Simple script to fetch weather for San Ildefonso and store it in weather_snapshots.
// Configure an API key and schedule this via cron or Windows Task Scheduler.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

$pdo = db();

if (!defined('WEATHER_API_KEY') || empty(WEATHER_API_KEY)) {
    echo "WEATHER_API_KEY not configured\n";
    exit;
}

// Example using OpenWeatherMap current weather API.
// Coordinates roughly for San Ildefonso, Bulacan.
$lat = 15.0828;
$lon = 120.9417;
$url = sprintf(
    'https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&appid=%s&units=metric',
    urlencode($lat),
    urlencode($lon),
    urlencode(WEATHER_API_KEY)
);

$json = @file_get_contents($url);
if ($json === false) {
    echo "Failed to fetch weather data\n";
    exit(1);
}

$data = json_decode($json, true);
if (!is_array($data) || empty($data['main'])) {
    echo "Unexpected weather API response\n";
    exit(1);
}

$tempC = (float)$data['main']['temp'];
$humidity = (float)$data['main']['humidity'];

// Simple heat index approximation (Steadman's formula for °C, rough).
// For production, you may want a more accurate formula or use the API's own feels_like.
$t = $tempC;
$rh = $humidity;
$heatIndex = $t;
if ($t >= 27 && $rh >= 40) {
    $heatIndex = -8.784695 + 1.61139411*$t + 2.338549*$rh
        - 0.14611605*$t*$rh - 0.012308094*($t*$t)
        - 0.016424828*($rh*$rh) + 0.002211732*($t*$t*$rh)
        + 0.00072546*($t*$rh*$rh) - 0.000003582*($t*$t*$rh*$rh);
}

// Derive comfort level
$level = 'low';
if ($heatIndex >= 41) {
    $level = 'extreme';
} elseif ($heatIndex >= 38) {
    $level = 'high';
} elseif ($heatIndex >= 32) {
    $level = 'medium';
}

$condition = $data['weather'][0]['description'] ?? 'N/A';

$stmt = $pdo->prepare("INSERT INTO weather_snapshots (temp_c, humidity, heat_index, condition_text, level)
                       VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$tempC, $humidity, $heatIndex, $condition, $level]);

echo "Weather snapshot stored: {$tempC}C, {$humidity}%, HI {$heatIndex}, level {$level}\n";

