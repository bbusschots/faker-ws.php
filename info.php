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
    'locale' => $LOCALE,
    'localeSource' => $LOCALE_SOURCE,
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

echo "# Parameter Processing\n";
echo "Parameters are accepted from the following sources (highest precedence to lowest):\n";
if(count($humanSourceList)){
    foreach($humanSourceList as $hs){
        echo "* $hs\n";
    }
    echo "(PHP directive `$orderSource=$rawOrderString`)\n";
}else{
    echo "*no parameters being accetpted!*\n";
}
echo "\n# Locale\n";
echo "* Using: $LOCALE\n";
echo "* Source: $LOCALE_SOURCE\n";
echo "\n# Generators by Provider\n";
foreach($providers as $p){
    echo "\n## $p\n";
    $formatters = array_keys($formattersByProvider[$p]);
    foreach($formatters as $f){
        $desc = str_replace("\n", ' ', $f);
        $desc = preg_replace('/\s+/', ' ', $desc);
        echo "* $desc\n";
    }
}