<?php
define("MAIN", "MAIN");

// load the common code
require_once __DIR__ . '/lib.php';

// figure out how many items have been requested
$n = 1;
if(isset($_REQUEST['n']) && is_numeric($_REQUEST['n']) && $_REQUEST['n'] >= 1){
    $n = intval($_REQUEST['n']);
}

// figure out what type to respond with
$type = $n === 1? 'text' : 'json';
if($REQUESTED_TYPE){
    $type = $REQUESTED_TYPE;
}

// figure out which formatter to use
$formatter = NULL;
if(isset($_REQUEST['formatter']) && !empty($_REQUEST['formatter'])){
    $formatter = $_REQUEST['formatter'];
    if(isset($FORMATTER_BLACKLIST_LOOKUP->{$formatter})){
        returnError("incompatible formatter '$formatter'");
    }
    try{
        $FAKER->getFormatter($formatter);
    }catch(Exception $e){
        returnError("unknown formatter '$formatter'");
    }
}else{
    returnError('no formatter specified');
}

// figure out what args to pass, if any
$args = [];
if(isset($_REQUEST['args'])){
    try{
        $parsedArgs = json_decode($_REQUEST['args']);
        if(!is_array($parsedArgs)) returnError("args did not parse to an array");
        $args = $parsedArgs;
    }catch(Exception $e){
        returnError("failed to parse args as JSON");
    }
}else{
    $argNum = 1;
    while(isset($_REQUEST['arg'.$argNum])){
        $args[] = $_REQUEST['arg'.$argNum];
        $argNum++;
    }
}

// figure out whether or not unique values are required
$unique = false;
if(isset($_REQUEST['unique']) && $_REQUEST['unique']) $unique = true;

// figure out whether or not to use the .optional() modifier
$optional = isset($_REQUEST['optional']) && $_REQUEST['optional'] ? true : false;
$weight = 0.5;
$default = '';
if($optional){
    if(isset($_REQUEST['weight']) && is_numeric($_REQUEST['weight']) && $_REQUEST['weight'] >= 0 && $_REQUEST['weight'] <= 1){
        $weight = floatval($_REQUEST['weight']);
    }
    if(isset($_REQUEST['default'])){
        $default = $_REQUEST['default'];
    }
}

//
// generate the dummy data
//

$data = []; // an array to store the data

// enable the customer error handler that converts warnings to errors
enableWarningToErrorConversion();

// try generate the data
try{
    $f = $FAKER;
    if($unique){
        $f = $f->unique();
    }
    for($i = 0; $i < $n; $i++){
        if($optional){
            $data[] = $f->optional($weight, $default)->format($formatter, $args);
        }else{
            $data[] = $f->format($formatter, $args);
        }
    }
}catch(Exception $e){
    returnError('failed to generate data with error: '.$e->getMessage());
}

// restore the default error handling
restore_error_handler();

//
// Send the generated data
//

// prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// allow corss-origin use
header("Access-Control-Allow-Origin: *");

//return the appropriate content type & content
if($type === 'json'){
    header('Content-Type: application/json');
    echo json_encode($data);
}else if($type === 'jsonText'){
    header('Content-Type: text/plain');
    echo json_encode($data, JSON_PRETTY_PRINT);
}else{
    // figure out what seperator to use
    $separator = isset($_REQUEST['separator']) ? $_REQUEST['separator'] : "\n";
    
    // filter the data
    $filteredData = array_map(
        function($val){
            if(is_object($val) || isAssociativeArray($val)){
                return filterObjectLikeToPlainText($val);
            }else if(is_array($val)){
                // deal with the value as a list
                return filterListLikeToPlainText($val);
            }
            return filterToPlainText($val);
        },
        $data
    );
    
    // return the data appropriately separated
    header('Content-Type: text/plain');
    echo join($separator, $filteredData);
}