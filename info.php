<?php
define("MAIN", "MAIN");

// load the common code
require_once __DIR__ . '/lib.php';

// set the content type
header('Content-Type: text/plain');

// create a Faker Documentor
$documentor = new Faker\Documentor($FAKER);

// build up a data object, starting with the locale
$info = (object)[
    'locale' => (object)[
        'using' => $LOCALE,
        'source' => $LOCALE_SOURCE,
        'rawValue' => $LOCALE_RAW
    ],
    'providers' => []
];

// gather the parameter processing data
$orderSource = 'request_order';
$rawOrderString = ini_get($orderSource);
if(empty($rawOrderString)){
    $orderSource = 'variables_order';
    $rawOrderString = ini_get($orderSource);
}
$rawSourceList = str_split($rawOrderString);
$rawActiveSourceList = [];
foreach($rawSourceList as $s){
    if(preg_match('/^[GPS]$/', $s)){
        $rawActiveSourceList[] = $s;
    }
}
$humanSourceList = [];
foreach($rawActiveSourceList as $s){
    if($s === 'G'){
        $humanSourceList[] = 'Query String Parameters';
    }else if($s === 'P'){
        $humanSourceList[] = 'Post Data';
    }else if($s === 'C'){
        $humanSourceList[] = 'Cookies';
    }
}
$humanSourceList = array_reverse($humanSourceList);
$info->parameterProcessing = (object)[
    'request_order' => ini_get('request_order'),
    'variables_order' => ini_get('variables_order'),
    'sourceDirective' => $orderSource,
    'directiveValue' => $rawOrderString,
    'humanSourceList' => $humanSourceList,
    'numSources' => count($humanSourceList)
];

// extract the generators (generates warnings, so suppress them)
$current_error_reporting = error_reporting();
error_reporting(E_ERROR);
$formattersByProvider = $documentor->getFormatters();
error_reporting($current_error_reporting);

// build a list of generators by provider
$providers = array_keys($formattersByProvider);
sort($providers);
foreach($providers as $p){
    $formatters = array_keys($formattersByProvider[$p]);
    $providerInfo = (object)[
        'name' => $p,
        'formatters' => [],
        'numFormatters' => count($formatters)
    ];
    foreach($formatters as $f){
        $desc = str_replace("\n", ' ', $f);
        $desc = preg_replace('/\s+/', ' ', $desc);
        $providerInfo->formatters[] = $desc;
    }
    $info->providers[] = $providerInfo;
}
$info->numProviders = count($info->providers);

// allow corss-origin use
header("Access-Control-Allow-Origin: *");

// render the appropriate response
$type = $REQUESTED_TYPE ? $REQUESTED_TYPE : 'text';
if($type === 'json'){
    header('Content-Type: application/json');
    echo json_encode($info);
    exit(0);
}else if($type === 'jsonText'){
    header('Content-Type: text/plain');
    echo json_encode($info, JSON_PRETTY_PRINT);
    exit(0);
}
// default to text
$m = new Mustache_Engine([
    'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views')
]);
header('Content-Type: text/plain');
echo $m->render('info-text', $info);