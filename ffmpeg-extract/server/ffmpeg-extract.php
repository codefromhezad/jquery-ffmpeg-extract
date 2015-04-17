<?php

define('FFMPEG_OUTPUT_FILE', 'tmp/ffmpeg-output.txt');
define('FFMPEG_STATUS_FILE', 'tmp/ffmpeg-status.txt');

define('LAUNCHED_STATUS_ERROR', 'error');
define('LAUNCHED_STATUS_OK', 'ok');

define('FFMPEG_STATUS_STARTED', 'started');
define('FFMPEG_STATUS_FINISHED', 'finished');
define('FFMPEG_STATUS_WORKING', 'working');
define('FFMPEG_STATUS_ERROR', 'error');



$messageStrings = array(
    'error' => array(
        'launch'  => 'An error occured while trying to start the background job',
        'parsing' => 'An error occured while parsing FFMPEG\'s output',
        'unknown' => 'An unexpected error occured: FFMPEG\'s output is empty.'
    )
);



function getErrorMessage($messageStringName) {
    global $messageStrings;
    return isset( $messageStrings['error'][$messageStringName] ) ?
            $messageStrings['error'][$messageStringName] :
            $messageStringName;
}

function renderJSON($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    die;
}

function renderJSONError($messageStringName, $data) {
    renderJSON( array(
        'status'  => FFMPEG_STATUS_ERROR,
        'message' => getErrorMessage($messageStringName),
        'data'    => $data
    ));
}

function getLaunchStatus() {
    $status = @file_get_contents(FFMPEG_STATUS_FILE);
    
    if( $status && trim($status) == LAUNCHED_STATUS_OK ) {
        return true;
    } else {
        return false;
    }
}

function getCurrentOutput() {
    return @file_get_contents(FFMPEG_OUTPUT_FILE);
}




if( isset($_GET['start-source-video']) ) {
    
    $srcVideoFile = escapeshellarg(trim($_GET['start-source-video']));

    exec('./extract-frames.sh '.$srcVideoFile.' > /dev/null');

    if( getLaunchStatus() ) {
        renderJSON(array(
            'status' => FFMPEG_STATUS_STARTED
        ));
    } else {
        renderJSONError('launch', getCurrentOutput());
    }
}




$content = getCurrentOutput();

if($content){
    //get duration of source
    preg_match("/Duration: (.*?), start:/", $content, $matches);

    if( ! count($matches) ) {
        renderJSONError('parsing', $content);
    }

    $rawDuration = $matches[1];

    //rawDuration is in 00:00:00.00 format. This converts it to seconds.
    $ar = array_reverse(explode(":", $rawDuration));
    $duration = floatval($ar[0]);
    if (!empty($ar[1])) $duration += intval($ar[1]) * 60;
    if (!empty($ar[2])) $duration += intval($ar[2]) * 60 * 60;

    //get the time in the file that is already encoded
    preg_match_all("/time=(.*?) bitrate/", $content, $matches);

    $rawTime = array_pop($matches);

    //this is needed if there is more than one match
    if (is_array($rawTime)){$rawTime = array_pop($rawTime);}

    //rawTime is in 00:00:00.00 format. This converts it to seconds.
    $ar = array_reverse(explode(":", $rawTime));
    $time = floatval($ar[0]);
    if (!empty($ar[1])) $time += intval($ar[1]) * 60;
    if (!empty($ar[2])) $time += intval($ar[2]) * 60 * 60;

    //calculate the progress
    $progress = round(($time/$duration) * 100);

    if( $progress >= 100 ) {
        $renderedFiles = glob('dist/*.jpg');

        renderJSON(array(
            'status'   => FFMPEG_STATUS_FINISHED,
            'duration' => $duration,
            'current'  => $time,
            'progress' => 100,
            'files'    => $renderedFiles
        ));
    } else {
        renderJSON(array(
            'status'   => FFMPEG_STATUS_WORKING,
            'duration' => $duration,
            'current'  => $time,
            'progress' => $progress
        ));
    }
}

renderJSONError('unknown', '');
