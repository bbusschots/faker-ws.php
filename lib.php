<?php
if (!defined("MAIN")) die();

// DEBUGGING ONLY: enable error reporting and display
error_reporting(E_ALL);
ini_set('display_errors', 1);

// function for returning errors
function returnError($error = 'ERROR', $code = 400){
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $error;
    exit(0);
}

// load Composer-managed dependencies
require_once __DIR__.'/vendor/autoload.php';

// figure out what locale to use
$LOCALE = Faker\Factory::DEFAULT_LOCALE;
$LOCALE_RAW = $LOCALE;
$LOCALE_SOURCE = 'Faker default';
if(isset($_REQUEST['locale'])){
    $lang = locale_get_primary_language($_REQUEST['locale']);
    $region = locale_get_region($_REQUEST['locale']);
    if($lang){
        $LOCALE_SOURCE = 'query parameter';
        $LOCALE_RAW = $_REQUEST['locale'];
        $LOCALE = $lang;
        if($region) $LOCALE .= "_$region";
    }
}else if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
    $locales = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $firstLocale = preg_replace('/;.*$/', '', $locales[0]);
    $lang = locale_get_primary_language($firstLocale);
    $region = locale_get_region($firstLocale);
    if($lang){
        $LOCALE_SOURCE = 'Accept-Language HTTP header';
        $LOCALE_RAW = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
        $LOCALE = $lang;
        if($region) $LOCALE .= "_$region";
    }
}

// get the requested response type, if any
$REQUESTED_TYPE = false;
if(isset($_REQUEST['type'])){
    if($_REQUEST['type'] === 'text' || $_REQUEST['type'] === 'json' || $_REQUEST['type'] === 'jsonText'){
        $REQUESTED_TYPE = $_REQUEST['type'];
    }
}

// create a Faker generator
$FAKER = Faker\Factory::create($LOCALE);