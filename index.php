<?php
define("MAIN", "MAIN");

// DEBUGGING ONLY: enable error reporting and display
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// load the common code
require_once __DIR__ . '/lib.php';

// figure out how many items and whether or not to encode as JSON
$N = 1;
$JSON = NULL;
if(isset($_REQUEST['n']) && is_numeric($_REQUEST['n']) && $_REQUEST['n'] >= 1){
    $N = intval($_REQUEST['n']);
}
if(isset($_REQUEST['json'])){
    $JSON = $_REQUEST['json'] ? true : false;
}
if(is_null($JSON)){
    $JSON = $N > 1 ? true : false;
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
$UNIQUE = false;
if(isset($_REQUEST['unique']) && $_REQUEST['unique']) $UNIQUE = true;

// try generate the dummy data
$data = [];
try{
    $f = $FAKER;
    if($UNIQUE){
        $f = $f->unique();
    }
    for($i = 0; $i < $N; $i++){
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
if($JSON){
    header('Content-Type: application/json');
    echo json_encode($data);
}else{
    header('Content-Type: text/plain');
    echo join("\n", $data);
}