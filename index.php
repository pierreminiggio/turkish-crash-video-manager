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

$isPixelDarkEnough = function (array $pixel): bool {          
    if ($pixel['red'] > 2) {
        return false;
    }

    if ($pixel['green'] > 2) {
        return false;
    }

    if ($pixel['blue'] > 2) {
        return false;
    }

    if ($pixel['alpha'] > 0) {
        return false;
    }

    return true;
};

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

    $frameFolderContent = scandir($frameFolder);
    $frameFolderContent = array_filter($frameFolderContent, fn ($frameFileName) => $frameFileName !== '.' && $frameFileName !== '..');

    $fps = 30;

    if (! $frameFolderContent) {
        $framer->frame($videoFileName, $frameFolder . DIRECTORY_SEPARATOR . '%04d.png', $fps);
    }

    $frameFolderContent = scandir($frameFolder, SCANDIR_SORT_DESCENDING);
    $frameFolderContent = array_filter($frameFolderContent, fn ($frameFileName) => $frameFileName !== '.' && $frameFileName !== '..');
    
    $lastNonOutroFrame = null;

    foreach ($frameFolderContent as $frameName) {
        $frameFileName = $frameFolder . DIRECTORY_SEPARATOR . $frameName;
        $frameImage = imagecreatefrompng($frameFileName);

        $frameImageWidth = imagesx($frameImage);
        $frameImageHeight = imagesy($frameImage);
    
        $thresholdToAvoidBlackBars = 100;
        $frameLeft = 0;
        $frameTop = 0 + $thresholdToAvoidBlackBars;
        $frameRight = $frameImageWidth - 1;
        $frameBottom = $frameImageHeight - (1 + $thresholdToAvoidBlackBars);
        $topLeftPixel = imagecolorsforindex($frameImage, imagecolorat($frameImage, $frameLeft, $frameTop));
        $topRightPixel = imagecolorsforindex($frameImage, imagecolorat($frameImage, $frameRight, $frameTop));
        $bottomLeftPixel = imagecolorsforindex($frameImage, imagecolorat($frameImage, $frameLeft, $frameBottom));
        $bottomRightPixel = imagecolorsforindex($frameImage, imagecolorat($frameImage, $frameRight, $frameBottom));

        if (
            $isPixelDarkEnough($topLeftPixel)
            && $isPixelDarkEnough($topRightPixel)
            && $isPixelDarkEnough($bottomLeftPixel)
            && $isPixelDarkEnough($bottomRightPixel)
        ) {
            continue;
        }

        $lastNonOutroFrame = $frameName;
        break;
    }

    if ($lastNonOutroFrame === null) {
        echo 'No last non outro frame found for ' . $videoName;
        die;
    }

    $explodedLastNonOutroFrame = explode('.', $lastNonOutroFrame, 2);
    $lastNonOutroFrameNumber = (int) $explodedLastNonOutroFrame[0];

    $truncatedOutroFileName = $videoFolderName . DIRECTORY_SEPARATOR . 'truncated_outro.mp4';

    if (! file_exists($truncatedOutroFileName)) {

        // ffmpeg returns an error with original resolution,
        // and upscaled and resized resolution works great enough
         
        /*$originalVideoResolution = shell_exec(
            'ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 '
            . $videoFileName
        );

        if (! $originalVideoResolution) {
            echo 'No original video resolution found';
            die;
        }

        $truncatedVideoResolution = $originalVideoResolution;*/

        $truncatedVideoResolution = '1920x1080';

        shell_exec(
            'ffmpeg -r '
            . $fps
            . ' -f image2 -s '
            . $truncatedVideoResolution
            . ' -start_number 1 -i '
            . $frameFolder
            . DIRECTORY_SEPARATOR
            . '%04d.png'
            . ' -vframes '
            . $lastNonOutroFrameNumber
            . ' -vcodec libx264 -crf 25 -pix_fmt yuv420p '
            . $truncatedOutroFileName
        );
    }
    
    if (! file_exists($truncatedOutroFileName)) {
        echo 'Error while creating truncated outro file name for ' . $videoFolderName;
        die;
    }

    var_dump($truncatedOutroFileName);
}
