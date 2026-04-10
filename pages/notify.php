<?php
// pages/notify.php
// db.php is already loaded by citizen_dashboard.php before this is included,
// but we guard it here in case notify.php is ever called standalone
if (!function_exists('db')) {
    require_once __DIR__ . '/db.php';
}

// Guard against "constant already defined" if included more than once
if (!defined('ONESIGNAL_APP_ID')) {
    define('ONESIGNAL_APP_ID', '8704d450-f3b9-4bc8-a1a9-a376abd93131');
    define('ONESIGNAL_API_KEY', 'os_v2_app_q4cniuhtxff4rinjun3kxwjrgedvta32adfewofnn3y6alx5gg7fqiakix3dq7236efos6mq7pew7knm4f3kt3qpkx6mn4iirxa4z4y');
}

if (!function_exists('sendOneSignalNotification')) {
    function sendOneSignalNotification(string $title, string $body, array $data = []): bool {
        if (!function_exists('curl_init')) return false;

        $payload = [
            'app_id'            => ONESIGNAL_APP_ID,
            'included_segments' => ['All'],
            'headings'          => ['en' => $title],
            'contents'          => ['en' => $body],
            'data'              => $data,
            'priority'          => 10,
            'ttl'               => 3600,
        ];

        $ch = curl_init('https://onesignal.com/api/v1/notifications');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . ONESIGNAL_API_KEY,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT    => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200);
    }
}

if (!function_exists('maybeSendDisasterNotification')) {
    function maybeSendDisasterNotification(PDO $pdo): void {
        $lockFile = sys_get_temp_dir() . '/mdrrmo_notif_lock.json';

        $lock = [];
        if (file_exists($lockFile)) {
            $lock = json_decode(file_get_contents($lockFile), true) ?? [];
        }

        // ── Priority 1: Active disaster from DB (admin-created) ──
        $stmt     = $pdo->query("SELECT * FROM disasters WHERE status = 'ongoing' ORDER BY level DESC, started_at DESC LIMIT 1");
        $disaster = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($disaster) {
            $key      = 'disaster_' . $disaster['id'];
            $lastSent = $lock[$key] ?? 0;

            if ((time() - $lastSent) > 600) {
                $types  = [
                    'typhoon'    => 'Bagyo',
                    'flood'      => 'Baha',
                    'earthquake' => 'Lindol',
                    'heat'       => 'Init',
                    'landslide'  => 'Landslide',
                    'fire'       => 'Sunog',
                ];
                $levels = [1 => 'Mababa', 2 => 'Katamtaman', 3 => 'Mataas', 4 => 'Sukdulan'];

                $tl    = $types[$disaster['type']] ?? ucfirst($disaster['type']);
                $ll    = $levels[(int)$disaster['level']] ?? 'Signal #' . $disaster['level'];
                $title = "⚠️ MDRRMO Alert: {$tl} Signal #{$disaster['level']}";
                $body  = "{$ll} na antas ng panganib. " . mb_substr($disaster['description'] ?? 'Manatiling alerto at sundin ang mga tagubilin.', 0, 100);

                if (sendOneSignalNotification($title, $body, [
                    'type'  => 'disaster',
                    'level' => (int)$disaster['level'],
                    'disaster_type' => $disaster['type'],
                    'disaster_id'   => (int)$disaster['id'],
                ])) {
                    $lock[$key] = time();
                    file_put_contents($lockFile, json_encode($lock));
                }
            }

            // Disaster takes priority — skip heat check
            return;
        }

        // ── Priority 2: Heat index from weather cache ──
        $cacheFile = sys_get_temp_dir() . '/mdrrmo_weather.json';
        if (!file_exists($cacheFile)) return;

        $weatherData = json_decode(file_get_contents($cacheFile), true);
        if (empty($weatherData['main'])) return;

        $t  = (float)$weatherData['main']['temp'];
        $rh = (float)$weatherData['main']['humidity'];

        // Same Rothfusz formula as citizen_dashboard.php
        $hi = $t;
        if ($t >= 27 && $rh >= 40) {
            $hi = -8.784695 + 1.61139411*$t + 2.338549*$rh
                - 0.14611605*$t*$rh - 0.012308094*($t*$t)
                - 0.016424828*($rh*$rh) + 0.002211732*($t*$t*$rh)
                + 0.00072546*($t*$rh*$rh) - 0.000003582*($t*$t*$rh*$rh);
        }

        // Only notify medium (≥38°C) and above
        if ($hi < 38) return;

        $level = $hi >= 42 ? 'extreme' : ($hi >= 40 ? 'high' : 'medium');
        $key   = 'heat_' . $level . '_' . date('Ymd');

        if ((time() - ($lock[$key] ?? 0)) > 600) {
            $levelLabels = [
                'medium'  => 'Katamtaman',
                'high'    => 'Mataas',
                'extreme' => 'Sukdulan',
            ];
            $ll    = $levelLabels[$level];
            $title = "🌡️ Heat Alert: {$ll} na panganib sa init";
            $body  = "Heat Index: " . round($hi, 1) . "°C sa San Ildefonso, Bulacan. Uminom ng maraming tubig at iwasang lumabas sa tanghali.";

            if (sendOneSignalNotification($title, $body, [
                'type'       => 'heat',
                'level'      => $level,
                'heat_index' => round($hi, 1),
            ])) {
                $lock[$key] = time();
                file_put_contents($lockFile, json_encode($lock));
            }
        }
    }
}