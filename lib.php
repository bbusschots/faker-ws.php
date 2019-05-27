<?php
if (!defined("MAIN")) die();

// DEBUGGING ONLY: enable error reporting and display
error_reporting(E_ALL);
ini_set('display_errors', 1);

// function to enable a customer error handler which converts warnings to errors
// NOTE: the customer error handler will remain active until restore_error_handler() is called
function enableWarningToErrorConversion(){
    set_error_handler(
        function($errno, $errstr){
            throw new Exception($errstr);
        },
        E_WARNING
    );
}

// function for returning errors
function returnError($error = 'ERROR', $code = 400){
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $error;
    exit(0);
}

// define formatter blacklist
$FORMATTER_BLACKLIST = [
    'calculateRoutingNumberChecksum', // a utility function wrongly exposed as a formatter
    'file', // coppies a file on the local disk, not sensible or desirable over the web!
    'getDefaultTimezone', // a utility function wrongly exposed as a formatter
    'image', // creates a file on disk, not sensible or desireable over the web!
    'optional', // modifier wrongly exposed as a formatter
    'passthrough', // modifier wrongly exposed as a formatter
    'randomElement', // requires an array as an argument, no way to do that ATM
    'randomElements', // requires an array as an argument, no way to do that ATM
    'randomKey', // a utility function wrongly exposed as a formatter
    'setDefaultTimezone', // a utility function wrongly exposed as a formatter
    'shuffleArray', // a utility function wrongly exposed as a formatter (actual formatter is just shuffle)
    'shuffleString', // a utility function wrongly exposed as a formatter (actual formatter is just shuffle)
    'toLower', // a utility function wrongly exposed as a formatter
    'toUpper', // a utility function wrongly exposed as a formatter
    'unique', // modifier wrongly exposed as a formatter
    'valid' // modifier wrongly exposed as a formatter
];
$FORMATTER_BLACKLIST_LOOKUP = (Object)[];
foreach($FORMATTER_BLACKLIST as $f){
    $FORMATTER_BLACKLIST_LOOKUP->{$f} = true;
}

// load Composer-managed dependencies
require_once __DIR__.'/vendor/autoload.php';

// apply parameter aliases
$PARAMETER_ALIASES = (object)[
    'arrayPostfix' => ['apost', 'arrPost'],
    'arrayPrefix' => ['apre', 'arrPre'],
    'arrayQuote' => ['aq', 'arrQuote'],
    'arraySeparator' => ['as', 'arrSep'],
    'default' => ['d', 'def'],
    'formatter' => ['f'],
    'locale' => ['l', 'loc'],
    'objectSeparator' => ['os', 'objSep'],
    'objectValueQuote' => ['ovq', 'objValQuote'],
    'objectValueSeparator' => ['ovs', 'objValSep'],
    'optional' => ['o', 'opt'],
    'recordSeparator' => ['rs', 'recsep'],
    'separator' => ['s', 'sep'],
    'type' => ['t', 'want'],
    'unique' => ['u'],
    'valueSeparator' => ['vs', 'valsep'],
    'weight' => ['w']
];
foreach($PARAMETER_ALIASES as $param => $aliases){
    foreach(array_reverse($aliases) as $alias){
        if(!isset($_REQUEST[$param]) && isset($_REQUEST[$alias])){
            $_REQUEST[$param] = $_REQUEST[$alias];
        }
    }
}

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

//
// --- Utility Functions ---
//

// function to determine of a value is an associative array, i.e. an array that has at
// least one non-integer key. Empty arrays will never be returned as associative!
function isAssociativeArray($val){
    if(!isset($val)) return false;
    if(!is_array($val)) return false;
    if(count($val) == 0) return false;
    return array_reduce(
        array_keys($val),
        function($carry, $item){
            return $carry && is_int($item) ? true : false;
        },
        true
    ) ? false : true;
}

// function for converting PHP variables for use in plain-text returns.
// This function does not do recursion — nested variables are replaced with a textual string describing them like 'ARRAY' or 'EMPTY ARRAY'
// NOTE: assuming no nested objects or arrays, only handling simple name-value pairs
function filterToPlainText($val){
    // render undefined as UNDEFINED
    if(!isset($val)) return 'UNDEFINED';
    
    // short-circuit strings
    if(is_string($val)) return $val;
    
    // render null as NULL
    if(is_null($val)) return 'NULL';
    
    // render nan as NaN
    if(is_double($val) && is_nan($val)) return 'NaN';
    
    // render booleans as TRUE or FALSE
    if(is_bool($val)) return $val ? 'TRUE' : 'FALSE';
    
    // deal with Arrays
    if(is_array($val)){
        // short-circuit empty arrays
        if(count($val) == 0) return 'EMPTY ARRAY';
        
        // return the appropriate array type
        return isAssociativeArray($val) ? 'ASSOCIATIVE ARRAY' : 'ARRAY';
    }
    
    // deal with Objects
    if(is_object($val)){
        if(count($val) == 0) return 'EMPTY OBJECT';
        if(is_a($val, 'DateTime')) return $val->format(DateTime::ISO8601);
        return 'OBJECT';
    }
    
    // convert other values to strings
    return "$val";
}

// a function to filter an object-like value to plain text.
// this function does not support nested datastructures - they will be flattened to a descriptive string like 'ASSOCIATIVE ARRAY'
$OBJECT_SEPARATOR = isset($_REQUEST['objectSeparator']) ? $_REQUEST['objectSeparator'] : ' | ';
$OBJECT_VALUE_SEPARATOR = isset($_REQUEST['objectValueSeparator']) ? $_REQUEST['objectValueSeparator'] : ': ';
$OBJECT_VALUE_QUOTE = isset($_REQUEST['objectValueQuote']) ? $_REQUEST['objectValueQuote'] : '';
function filterObjectLikeToPlainText($val){
    global $OBJECT_SEPARATOR, $OBJECT_VALUE_SEPARATOR, $OBJECT_VALUE_QUOTE;
    if(!(is_array($val) && count($val) > 0)) return filterToPlainText($val);
    $arrayVal = $val;
    if(is_object($val)){
        if(count($val) == 0) return filterToPlainText($val);
        $arrayVal = get_object_vars($val);
    }
    $ansParts = [];
    foreach($arrayVal as $k => $v){
        array_push($ansParts, $k.$OBJECT_VALUE_SEPARATOR.$OBJECT_VALUE_QUOTE.filterToPlainText($v).$OBJECT_VALUE_QUOTE);
    }
    return implode($OBJECT_SEPARATOR, $ansParts);
}

// a function to filter a list-like value to plain text.
// this function does not support nested datastructures - they will be flattened to a descriptive string like 'ARRAY'
$ARRAY_SEPARATOR = isset($_REQUEST['arraySeparator']) ? $_REQUEST['arraySeparator'] : ', ';
$ARRAY_PREFIX = isset($_REQUEST['arrayPrefix']) ? $_REQUEST['arrayPrefix'] : '';
$ARRAY_POSTFIX = isset($_REQUEST['arrayPostfix']) ? $_REQUEST['arrayPostfix'] : '';
$ARRAY_QUOTE = isset($_REQUEST['arrayQuote']) ? $_REQUEST['arrayQuote'] : '';
function filterListLikeToPlainText($val){
    global $ARRAY_SEPARATOR, $ARRAY_PREFIX, $ARRAY_POSTFIX, $ARRAY_QUOTE;
    if(!is_array($val)) return filterToPlainText($val);
    $ansParts = [];
    foreach($val as $e){
        array_push($ansParts, $ARRAY_QUOTE.filterToPlainText($e).$ARRAY_QUOTE);
    }
    return $ARRAY_PREFIX.implode($ARRAY_SEPARATOR, $ansParts).$ARRAY_POSTFIX;
}

// function for extracting the name from a formatter description
function nameFromFromatterDesc($fd){
    $nameMatch = [];
    preg_match('/^[^(]+/', $fd, $nameMatch);
    if(isset($nameMatch[0])) return $nameMatch[0];
    throw new Exception("failed to extract name from formatter description: $fd");
}