<?php
define("MAIN", "MAIN");

// DEBUGGING ONLY: enable error reporting and display
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

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
$FORMATTER = NULL;
if(isset($_REQUEST['formatter']) && !empty($_REQUEST['formatter'])){
    $FORMATTER = $_REQUEST['formatter'];
    try{
        $FAKER->getFormatter($FORMATTER);
    }catch(Exception $e){
        returnError("unknown formatter '$FORMATTER'");
    }
}else{
    returnError('no formatter specified');
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
        $data[] = $f->format($FORMATTER);
    }
}catch(Exception $e){
    returnError('failed to generate data with error: '.$e->getMessage());
}

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
    header('Content-Type: text/plain');
    echo join("\n", $data);
}