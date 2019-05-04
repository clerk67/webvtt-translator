<?php

$_POST = json_decode(file_get_contents('php://input'), true);
$cues = $_POST['cues'];
$content = array_map(function ($cue) {
    return $cue['originalText'];
}, $cues);

$request = [
    'document' => [
        'type' => 'PLAIN_TEXT',
        'content' => implode("\n", $content),
    ],
    'encodingType' => 'UTF8',
];

$key = getenv('GOOGLE_CLOUD_API_KEY');
$ch = curl_init("https://language.googleapis.com/v1/documents:analyzeSyntax?key=$key");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = json_decode(curl_exec($ch), true);

$cueOffsets = [];
foreach ($response['sentences'] as $sentence) {
    $cueOffsets[] = $sentence['text']['beginOffset'];
}

$sentenceOffsets = [];
$currentOffset = null;
foreach ($response['tokens'] as $token) {
    if ($currentOffset === null) {
        $currentOffset = $token['text']['beginOffset'];
    }
    if ($token['partOfSpeech']['form'] === 'FINAL_ENDING') {
        $sentenceOffsets[] = $currentOffset;
        $currentOffset = null;
    }
}

$result = [];
foreach ($sentenceOffsets as $offset) {
    if (in_array($offset, $cueOffsets)) {
        $result[] = array_search($offset, $cueOffsets);
    }
}

$duration = 0;
$characters = 0;
foreach ($cues as $cue) {
    $duration += $cue['end'] - $cue['start'];
    $characters += strlen($cue['originalText']);
}

$cps = $characters / $duration;
for ($i = 1; $i < count($cues); $i++) {
    $interval = $cues[$i]['start'] - $cues[$i - 1]['end'];
    if ($interval < 0.2 && $interval < 5 / $cps) {
        $result = array_values(array_diff($result, [$i]));
    } elseif ($interval > 1 && $interval > 15 / $cps) {
        $result[] = $i;
        sort($result, SORT_NUMERIC);
        $result = array_values(array_unique($result));
    }
}

header('Content-Type: application/json');
echo json_encode([
    'language' => $response['language'],
    'sentences' => $response['sentences'],
    'tokens' => $response['tokens'],
    'beginnings' => $result,
]);
