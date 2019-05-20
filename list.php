<?php
define("MAIN", "MAIN");

// load the common code
require_once __DIR__ . '/lib.php';

// TEMP - force into text mode
header('Content-Type: text/plain');

// create a Faker Documentor
$documentor = new Faker\Documentor($FAKER);

// extract the generators (generates warnings, so suppress them)
$current_error_reporting = error_reporting();
error_reporting(E_ERROR);
$formattersByProvider = $documentor->getFormatters();
error_reporting($current_error_reporting);

// Build formatter lookup — indexed by formatter name, the value is simply true
$formattersLookup = (Object)[];
foreach($formattersByProvider as $providerPath => $formatters){
    // loop through all the formatters in the provider
    foreach($formatters as $formatterDescription => $sampleOutput){
        // extract the formatter name from its description
        $nameMatch = [];
        $formatterName = '';
        preg_match('/^[^(]+/', $formatterDescription, $nameMatch);
        if(isset($nameMatch[0])){
            $formatterName = $nameMatch[0];
        }else{
            error_log("failed to extract name from formatter description, skipping: $formatterDescription");
            continue;
        }
        
        // store the formatter
        $formattersLookup->{$formatterName} = true;
    }
}

// convert the lookup table to a sorted array
$formatters = array_keys(get_object_vars($formattersLookup));
sort($formatters);

// allow corss-origin use
header("Access-Control-Allow-Origin: *");

// render the appropriate response
$type = $REQUESTED_TYPE ? $REQUESTED_TYPE : 'text';
if($type === 'json'){
    header('Content-Type: application/json');
    echo json_encode($formatters);
    exit(0);
}else if($type === 'jsonText'){
    header('Content-Type: text/plain');
    echo json_encode($formatters, JSON_PRETTY_PRINT);
    exit(0);
}
// default to text
header('Content-Type: text/plain');
echo implode("\n", $formatters)."\n";