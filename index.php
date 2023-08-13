<?php

use PierreMiniggio\ConfigProvider\ConfigProvider;
use PierreMiniggio\VideoToFrames\VideoFramer;

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

$cacheFolder = $projectFolder . 'cache';

if (! file_exists($cacheFolder)) {
    mkdir($cacheFolder);
}

$monthYearCacheFolder = $cacheFolder . DIRECTORY_SEPARATOR . $monthYear;

if (! file_exists($monthYearCacheFolder)) {
    mkdir($monthYearCacheFolder);
}

$framer = new VideoFramer();

foreach ($crashLinks as $crashLink) {
    $videoName = base64_encode($crashLink);

    $videoFolderName = $monthYearCacheFolder . DIRECTORY_SEPARATOR . $videoName;

    if (! file_exists($videoFolderName)) {
        mkdir($videoFolderName);
    }

    $videoFileName = $videoFolderName . DIRECTORY_SEPARATOR . 'original_video.mp4';

    if (! file_exists($videoFileName)) {

        if (! $openedVideoFile = fopen($videoFileName, 'wb+')) {
            throw new RuntimeException('File opening error');
        }

        $videoCurl = curl_init($crashLink);
        curl_setopt_array($videoCurl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FILE => $openedVideoFile
        ]);

        curl_exec($videoCurl);
        curl_close($videoCurl);
        fclose($openedVideoFile);
    }

    $frameFolder = $videoFolderName . DIRECTORY_SEPARATOR . 'frames';

    if (! file_exists($frameFolder)) {
        mkdir($frameFolder);
    }

    $framer->frame($videoFileName, $frameFolder . DIRECTORY_SEPARATOR . '%d.png', 30);

    var_dump($videoName);
    var_dump($crashLink);
    die;
}
