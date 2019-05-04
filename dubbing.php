<?php

$_POST = json_decode(file_get_contents('php://input'), true);

$timetable = $_POST['cues'];
$inputfile = $_POST['inputfile'];
if (!$timetable || !$inputfile) {
    header('HTTP/1.1 400 Bad Request');
    exit(1);
}

$outputfile = 'output' . rand(0, 9999) . '.mp4';

$cmd = "/usr/local/bin/ffmpeg -i $inputfile ";
for ($i = 0; $i < count($timetable); $i++) {
    $cmd .= "-i $i.mp3 ";
}
$cmd .= "-vcodec copy -filter_complex '";
for ($i = 1; $i <= count($timetable); $i++) {
    $delay = $timetable[$i - 1]['start'] * 1000;
    $cmd .= "[$i:a]adelay={$delay}[$i:delayed];";
}
$cmd .= "[0:a]volume=1/2[0:quiet];[0:quiet]";
for ($i = 1; $i <= count($timetable); $i++) {
    $cmd .= "[$i:delayed]";
}
$cmd .= "amix=inputs=" . (count($timetable) + 1) . ":dropout_transition=3600,volume=12' -y $outputfile 2>&1";
exec($cmd, $output, $return_var);

header('Content-Type: application/json');
echo json_encode([
    'filename' => $outputfile,
    'ffmpeg_command' => $cmd,
    'ffmpeg_output' => $output,
]);
