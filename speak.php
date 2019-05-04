<?php

$_POST = json_decode(file_get_contents('php://input'), true);
$index = urlencode($_POST['index']);
$text = urlencode($_POST['text']);
$language = urlencode($_POST['language']);
$gender = urlencode($_POST['gender']);
if (!$text || !$language || !$gender) {
    header('HTTP/1.1 400 Bad Request');
    exit(1);
}

$secret = getenv('AZURE_CLIENT_SECRET');
$ch = curl_init('https://api.cognitive.microsoft.com/sts/v1.0/issueToken');
$data = json_encode('{body}');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($data),
        'Ocp-Apim-Subscription-Key: ' . $secret,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$token = curl_exec($ch);
curl_close($ch);

$query = "text=$text&language=$language&format=audio/mp3&options=$gender";
$ch = curl_init("https://api.microsofttranslator.com/V2/Http.svc/Speak?$query");
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
curl_close($ch);

file_put_contents("$index.mp3", $response);
header('Content-Type: application/json');
echo json_encode(['filename' => "$index.mp3"]);
