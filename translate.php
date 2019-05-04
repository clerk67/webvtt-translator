<?php

/*
 * Google Cloud Translation API
 *
 * @param  $source  string
 * @param  $target  string
 * @param  $sentences  array
 * @return array
 */
function gcloudTranslator($source, $target, $sentences)
{
    $key = getenv('GOOGLE_CLOUD_API_KEY');
    $query = "key=$key&format=text&target=$target";
    if ($source && $source !== 'auto') {
        $query .= "&source=$source";
    }
    foreach ($sentences as $sentence) {
        $query .= '&q=' . urlencode($sentence);
    }
    $ch = curl_init("https://www.googleapis.com/language/translate/v2?$query");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    $result = [];
    foreach ($response->data->translations as $translation) {
        $result[] = $translation->translatedText;
    }
    return $result;
}

/*
 * IBM Bluemix Watson Language Translator
 *
 * @param  $source  string
 * @param  $target  string
 * @param  $sentences  array
 * @return array
 */
function watsonTranslator($source, $target, $sentences)
{
    if (!$source || $source === 'auto') {
        header('HTTP/1.1 400 Bad Request');
        exit(1);
    }
    $username = getenv('IBM_CLOUD_USERNAME');
    $password = getenv('IBM_CLOUD_PASSWORD');
    $request = [
        'text' => $sentences,
        'source' => $source,
        'target' => $target,
    ];
    $ch = curl_init('https://gateway.watsonplatform.net/language-translator/api/v2/translate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($request),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_USERPWD => "$username:$password",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = json_decode(curl_exec($ch));
    curl_close($ch);

    $result = [];
    foreach ($response->translations as $translation) {
        $result[] = $translation->translation;
    }
    return $result;
}

/*
 * Microsoft Azure Translator Text API
 *
 * @param  $source  string
 * @param  $target  string
 * @param  $sentences  array
 * @return array
 */
function azureTranslator($source, $target, $sentences)
{
    if (!$source || $source === 'auto') {
        $source = '';
    }
    $xml = <<<XML
<TranslateArrayRequest>
    <AppId/>
    <From>$source</From>
    <Options>
        <Category xmlns="http://schemas.datacontract.org/2004/07/Microsoft.MT.Web.Service.V2" />
        <ContentType xmlns="http://schemas.datacontract.org/2004/07/Microsoft.MT.Web.Service.V2">text/plain</ContentType>
        <ReservedFlags xmlns="http://schemas.datacontract.org/2004/07/Microsoft.MT.Web.Service.V2" />
        <State xmlns="http://schemas.datacontract.org/2004/07/Microsoft.MT.Web.Service.V2" />
        <Uri xmlns="http://schemas.datacontract.org/2004/07/Microsoft.MT.Web.Service.V2" />
        <User xmlns="http://schemas.datacontract.org/2004/07/Microsoft.MT.Web.Service.V2" />
    </Options>
    <Texts>
XML;
    foreach ($sentences as $sentence) {
        $xml .=  '<string xmlns="http://schemas.microsoft.com/2003/10/Serialization/Arrays">' . $sentence . '</string>';
    }
    $xml .= <<<XML
    </Texts>
    <To>$target</To>
</TranslateArrayRequest>
XML;

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

    $ch = curl_init('https://api.microsofttranslator.com/v2/Http.svc/TranslateArray');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: text/xml',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = simplexml_load_string(curl_exec($ch));
    curl_close($ch);

    $result = [];
    foreach ($response->TranslateArrayResponse as $translation) {
        $result[] = (string) $translation->TranslatedText;
    }
    return $result;
}

/*
 * Google Cloud Natural Language API
 *
 * @param  $sentences  array
 * @return array
 */
function gcloudSyntaxAnalyzer($sentences)
{
    $request = [
        'document' => [
            'type' => 'PLAIN_TEXT',
            'content' => implode("\n", $sentences),
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
}

$_POST = json_decode(file_get_contents('php://input'), true);

$source = $_POST['source'];
$target = $_POST['target'];
$translator = $_POST['translator'];
$cues = $_POST['cues'];

if (!$source || !$translator || !$cues) {
    header('HTTP/1.1 400 Bad Request');
    exit(1);
}
$sentences = [];
$sentence = '';
for ($i = 0; $i < count($cues); $i++) {
    if ($i > 0 && $cues[$i]['beginning']) {
        $sentences[] = substr($sentence, 0, -1);
        $sentence = '';
    }
    $sentence .= $cues[$i]['originalText'] . ' ';
}
$sentences[] = substr($sentence, 0, -1);

switch ($translator) {
case 'gcloud':
    $translations = gcloudTranslator($source, $target, $sentences);
    break;
case 'watson':
    $translations = watsonTranslator($source, $target, $sentences);
    break;
case 'azure':
    $translations = azureTranslator($source, $target, $sentences);
    break;
default:
    header('HTTP/1.1 400 Bad Request');
    exit(1);
}
$j = 0;
$result = [];
for ($i = 0; $i < count($cues); $i++) {
    if ($cues[$i]['beginning']) {
        $result[] = ['index' => $i, 'translation' => $translations[$j]];
        $j++;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'source' => $source,
    'target' => $target,
    'translations' => $result,
]);
