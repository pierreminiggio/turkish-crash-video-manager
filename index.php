<?php

use PierreMiniggio\ConfigProvider\ConfigProvider;

$projectFolder = __DIR__ . DIRECTORY_SEPARATOR;

require $projectFolder . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$configProvider = new ConfigProvider($projectFolder);

$config = $configProvider->get();

$turkishCrashApiConfig = $config['turkish_crash_api'];
$turkishCrashApiUrl = $turkishCrashApiConfig['url'];

$nextCurl = curl_init($turkishCrashApiUrl . '/next');
curl_setopt($nextCurl, CURLOPT_RETURNTRANSFER, 1);
$nextResponse = curl_exec($nextCurl);
curl_close($nextCurl);

if (! $nextResponse) {
    echo 'No next response';
    die;
}

$nextJsonResponse = json_decode($nextResponse, true);

if (! $nextJsonResponse) {
    echo 'No next JSON response';
    die;
}

if (empty($nextJsonResponse['next'])) {
    echo 'No next key in next JSON response';
    die;
}

$monthYear = $nextJsonResponse['next'];

$periodCurl = curl_init($turkishCrashApiUrl . '/period/' . $monthYear);
curl_setopt($periodCurl, CURLOPT_RETURNTRANSFER, 1);
$periodResponse = curl_exec($periodCurl);
curl_close($periodCurl);

if (! $periodResponse) {
    echo 'No period response';
    die;
}

$periodJsonResponse = json_decode($periodResponse, true);

if (! $periodJsonResponse) {
    echo 'No period JSON response';
    die;
}

if (empty($periodJsonResponse['crashes'])) {
    echo 'No crashes key in period JSON response';
    die;
}

$crashLinks = $periodJsonResponse['crashes'];

foreach ($crashLinks as $crashLink) {
    var_dump($crashLink);
}
