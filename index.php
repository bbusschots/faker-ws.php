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

// try generate the dummy data
$data = [];
try{
    $f = $FAKER;
    if($unique){
        $f = $f->unique();
    }
    for($i = 0; $i < $n; $i++){
        $data[] = $f->format($formatter, $args);
    }
}catch(Exception $e){
    returnError('failed to generate data with error: '.$e->getMessage());
}

// figure out whther or not to use the .optional() modifier
// TO DO

// prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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
    
    // return the data appropriately separated
    header('Content-Type: text/plain');
    echo join($separator, $data);
}