<?php
define("MAIN", "MAIN");

// load the common code
require_once __DIR__ . '/lib.php';

// set the content type
header('Content-Type: text/plain');

// create a Faker Documentor
$documentor = new Faker\Documentor($FAKER);

// extract the generators (generates warnings, so suppress them)
$current_error_reporting = error_reporting();
error_reporting(E_ERROR);
$formattersByProvider = $documentor->getFormatters();
error_reporting($current_error_reporting);

// get the list of providers
$providers = array_keys($formattersByProvider);
sort($providers);

echo "# Locale\n";
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