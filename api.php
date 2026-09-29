<?php
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

date_default_timezone_set('Europe/Amsterdam');

if (!file_exists('config.php')) {
    echo json_encode(['error' => 'config.php niet gevonden']);
    exit;
}

$config = require 'config.php';

if (!$config || !is_array($config)) {
    echo json_encode(['error' => 'Ongeldig config.php formaat']);
    exit;
}

$pinLength = isset($config['pin_length']) ? (int)$config['pin_length'] : 6;
if ($pinLength < 3) {
    $pinLength = 3;
} elseif ($pinLength > 6) {
    $pinLength = 6;
}

$student = $_GET['student'] ?? '';
if (!$student) {
    echo json_encode(['error' => 'Geen leerlingnummer opgegeven']);
    exit;
}

if (!ctype_digit($student) || strlen($student) !== $pinLength) {
    echo json_encode(['error' => "Ongeldig leerlingnummer. Het leerlingnummer moet exact uit {$pinLength} cijfers bestaan."]);
    exit;
}

$school = $config['school'];
$token = $config['token'];
$locationFilter = $config['location'] ?? null;

$now = time();
if (isset($_GET['time'])) {
    $now = (int)$_GET['time'];
}

$todayStart = strtotime('today', $now);
$tomorrowEnd = strtotime('tomorrow + 1 day', $now) - 1;

$params = [
    'user' => $student,
    'start' => $todayStart,
    'end' => $tomorrowEnd,
    'access_token' => $token
];

$url = "https://{$school}.zportal.nl/api/v3/appointments?" . http_build_query($params);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo json_encode(['error' => 'Curl fout: ' . $error]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['error' => "Dit rooster kon niet worden geladen", 'response' => $response]);
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Fout bij decoderen Zermelo API response', 'raw' => $response]);
    exit;
}

if (!isset($data['response']['data'])) {
    echo json_encode(['error' => 'Ongeldige response van Zermelo API', 'full_response' => $data]);
    exit;
}

$appointments = $data['response']['data'];

if ($locationFilter && $locationFilter !== 'XX') {
    $appointments = array_filter($appointments, function($app) use ($locationFilter) {
        return isset($app['branch']) && $app['branch'] === $locationFilter;
    });
}

$processedApps = [];
foreach ($appointments as $app) {
    $label = '';
    $isDeviating = false;
    if (!empty($app['startTimeSlotName'])) {
        $label = $app['startTimeSlotName'];
    } elseif (isset($app['startTimeSlot']) && $app['startTimeSlot'] !== null && $app['startTimeSlot'] !== '') {
        $label = $app['startTimeSlot'] . 'e';
    } else {
        $isDeviating = true;
    }

    $startTime = date('H:i', $app['start']);
    $endTime = date('H:i', $app['end']);
    $timeStr = "{$startTime} - {$endTime}";

    if ($isDeviating) {
        $subjectTitle = '';
        if (!empty($app['remark'])) {
            $subjectTitle = trim($app['remark']);
        } elseif (!empty($app['appointmentType'])) {
            $subjectTitle = trim($app['appointmentType']);
        } elseif (!empty($app['type'])) {
            $subjectTitle = trim($app['type']);
        } elseif (!empty($app['subjects'])) {
            $subjectTitle = implode(', ', $app['subjects']);
        } else {
            $subjectTitle = 'Afspraak';
        }
    } else {
        $subjectTitle = isset($app['subjects']) ? implode(', ', $app['subjects']) : '';
    }

    $processedApps[] = [
        'id' => $app['id'],
        'start' => $app['start'],
        'end' => $app['end'],
        'label' => $label,
        'time' => $timeStr,
        'subjects' => $subjectTitle,
        'locations' => isset($app['locations']) ? strtoupper(implode(', ', $app['locations'])) : '',
        'teachers' => isset($app['teachers']) ? strtoupper(implode(', ', $app['teachers'])) : '',
        'cancelled' => $app['cancelled'] ?? false,
        'valid' => $app['valid'] ?? true,
        'lastModified' => $app['lastModified'] ?? 0,
        'isDeviating' => $isDeviating,
    ];
}

$groupedByTime = [];
foreach ($processedApps as $app) {
    $groupedByTime[$app['start']][] = $app;
}

$finalApps = [];
foreach ($groupedByTime as $start => $appsAtTime) {
    usort($appsAtTime, function($a, $b) {
        if ($a['lastModified'] !== $b['lastModified']) {
            return $b['lastModified'] - $a['lastModified'];
        }
        return $b['id'] - $a['id'];
    });

    $validAppsForTime = [];
    $cancelledAppsForTime = [];

    foreach ($appsAtTime as $app) {
        if ($app['cancelled'] || !$app['valid']) {
            $cancelledAppsForTime[] = $app;
        } else {
            $validAppsForTime[] = $app;
        }
    }

    if (empty($validAppsForTime)) {
        if (!empty($cancelledAppsForTime)) {
            $cancelledApp = $cancelledAppsForTime[0];
            $cancelledApp['isFullyCancelled'] = true;
            $finalApps[] = $cancelledApp;
        }
    } else {
        foreach ($validAppsForTime as $validApp) {
            $oldLocations = null;
            $oldTeachers = null;
            
            foreach ($cancelledAppsForTime as $cancelledApp) {
                if ($cancelledApp['subjects'] === $validApp['subjects']) {
                    if ($cancelledApp['locations'] !== $validApp['locations']) {
                        $oldLocations = $cancelledApp['locations'];
                    }
                    if ($cancelledApp['teachers'] !== $validApp['teachers']) {
                        $oldTeachers = $cancelledApp['teachers'];
                    }
                    break;
                }
            }

            if ($oldLocations !== null) {
                $validApp['oldLocations'] = $oldLocations;
            }
            if ($oldTeachers !== null) {
                $validApp['oldTeachers'] = $oldTeachers;
            }
            $finalApps[] = $validApp;
        }
    }
}

usort($finalApps, function($a, $b) {
    return $a['start'] - $b['start'];
});

$lessons_today = [];
$lessons_tomorrow = [];
$tomorrow_ts = strtotime('tomorrow', $now);

foreach ($finalApps as $app) {
    if ($app['start'] < $tomorrow_ts) {
        $lessons_today[] = $app;
    } else {
        $lessons_tomorrow[] = $app;
    }
}

$current_lesson = null;
foreach ($lessons_today as $app) {
    if ($app['start'] <= $now && $app['end'] > $now && empty($app['isFullyCancelled'])) {
        $current_lesson = $app;
        break;
    }
}

$result_next = null;
$result_list_today = [];
$result_list_tomorrow = $lessons_tomorrow;
$message = '';

if ($current_lesson) {
    $result_next = $current_lesson;
    $result_list_today = $lessons_today;
} else {
    $future_today = array_values(array_filter($lessons_today, function($app) use ($now) {
        return $app['start'] > $now && empty($app['isFullyCancelled']);
    }));
    
    if (empty($future_today)) {
        if (!empty($lessons_today)) {
             $result_list_today = $lessons_today;
        }
        $message = 'Geen lessen meer voor vandaag';
    } else if (count($future_today) === 1) {
        $result_next = $future_today[0];
        $result_list_today = $lessons_today;
    } else {
        $result_next = $future_today[0];
        $result_list_today = $lessons_today;
    }
}

echo json_encode([
    'next' => $result_next,
    'today' => $result_list_today,
    'tomorrow' => $result_list_tomorrow,
    'message' => $message,
    'student' => $student,
    'server_time' => $now
]);
