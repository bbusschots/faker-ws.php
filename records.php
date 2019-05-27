<?php
define("MAIN", "MAIN");

// load the common code
require_once __DIR__ . '/lib.php';

// figure out how many records have been requested
$n = 1;
if(isset($_REQUEST['n']) && is_numeric($_REQUEST['n']) && $_REQUEST['n'] >= 1){
    $n = intval($_REQUEST['n']);
}

// figure out what type to respond with
$type = $n === 1? 'text' : 'json';
if($REQUESTED_TYPE){
    $type = $REQUESTED_TYPE;
}

// figure out the structure of the requested record
$recordDefinition = (object)[];
$fNum = 1;
while(isset($_REQUEST['f'.$fNum])){
    $fieldDef = (object)[];
    try{
        // make sure the formatter exists
        if(isset($FORMATTER_BLACKLIST_LOOKUP->{$_REQUEST['f'.$fNum]})){
            throw new Exception('incompatible formatter');
        }
        $FAKER->getFormatter($_REQUEST['f'.$fNum]);
        
        $fieldDef->formatter = $_REQUEST['f'.$fNum];
        if(isset($_REQUEST['f'.$fNum.'n']) && is_string($_REQUEST['f'.$fNum.'n']) && $_REQUEST['f'.$fNum.'n']){
            $fieldDef->name = $_REQUEST['f'.$fNum.'n'];
        }else{
            $fieldDef->name = $_REQUEST['f'.$fNum];
        }
        $fieldDef->args = [];
        $argNum = 1;
        while(isset($_REQUEST['f'.$fNum.'a'.$argNum])){
            $fieldDef->args[] = $_REQUEST['f'.$fNum.'a'.$argNum];
            $argNum++;
        }
        $fieldDef->unique = isset($_REQUEST['f'.$fNum.'u']) && $_REQUEST['f'.$fNum.'u'] ? true : false;
        $fieldDef->optional = isset($_REQUEST['f'.$fNum.'o']) && $_REQUEST['f'.$fNum.'o'] ? true : false;
        $fieldDef->weight = 0.5;
        $fieldDef->default = (object)[];
        if($fieldDef->optional){
            if(isset($_REQUEST['f'.$fNum.'w']) && is_numeric($_REQUEST['f'.$fNum.'w']) && $_REQUEST['f'.$fNum.'w'] >= 0 && $_REQUEST['f'.$fNum.'w'] <= 1){
                $fieldDef->weight = floatval($_REQUEST['f'.$fNum.'w']);
            }
            if(isset($_REQUEST['f'.$fNum.'d'])){
                $fieldDef->default = $_REQUEST['f'.$fNum.'d'];
            }
        }
    }catch(Exception $e){
        returnError("failed to process details for field $fNum");
    }
    $recordDefinition->{$fieldDef->name} = $fieldDef;
    $fNum++;
}
if(isset($_REQUEST['recordDefinition'])){
    try{
        $parsedRecordDefinition = json_decode($_REQUEST['recordDefinition']);
        if(is_array($parsedRecordDefinition)){
            foreach($parsedRecordDefinition as $parsedFieldDef){
                $fieldDef = (object)[];
                if(!is_object($parsedFieldDef)){
                    returnError("record definition array contained a non-object");
                }
                if(!isset($parsedFieldDef['formatter']) || empty($parsedFieldDef['formatter'])){
                    returnError("record definition array contained an object that does not specify a formatter");
                }
                // make sure the formatter exists
                $FAKER->getFormatter($parsedFieldDef['formatter']);
                
                $fieldDef->formatter = $parsedFieldDef['formatter'];
                if(is_string($parsedFieldDef['name']) && !empty($parsedFieldDef['name'])){
                    $fieldDef->name = $parsedFieldDef['name'];
                }else{
                    $fieldDef->name = $parsedFieldDef['formatter'];
                }
                $recordDefinition->{$fieldDef->name} = $fieldDef;
                if(isset($parsedFieldDef['args'])){
                    if(is_array($parsedFieldDef['args'])){
                        $fieldDef->args = $parsedFieldDef['args'];
                    }else{
                        returnError("record definition array contained an object that specifies invalid args (must be an array)");
                    }
                }else{
                    $fieldDef->args = [];
                }
                $fieldDef->unique = isset($parsedFieldDef['unique']) && $parsedFieldDef['unique'] ? true : false;
                $fieldDef->optional = isset($parsedFieldDef['optional']) && $parsedFieldDef['optional'] ? true : false;
                $fieldDef->weight = 0.5;
                $fieldDef->default = '';
                if($fieldDef->optional){
                    if(isset($parsedFieldDef['weight']) && is_numeric($parsedFieldDef['weight']) && $parsedFieldDef['weight'] >= 0 && $parsedFieldDef['weight'] <= 1){
                        $fieldDef->weight = floatval($parsedFieldDef['weight']);
                    }
                    if(isset($parsedFieldDef['default'])){
                        $fieldDef->default = $parsedFieldDef['default'];
                    }
                }
            }
        }else{
            returnError("record definition not an array");
        }
    }catch(Exception $e){
        returnError("failed to parse record definition as JSON");
    }
}
if(count(get_object_vars($recordDefinition)) < 1){
    returnError('no fields specified');
}

// figure out whether or not to implement unique globally
// NOTE: at least for now, this is implemented by forcing every field to implement unique
$unique = false;
if(isset($_REQUEST['unique']) && $_REQUEST['unique']) $unique = true;

// figure out whether or not to run the generated records through the .optional()
$optional = isset($_REQUEST['optional']) && $_REQUEST['optional'] ? true : false;
$weight = 0.5;
$default = (object)[];
if($optional){
    if(isset($_REQUEST['weight']) && is_numeric($_REQUEST['weight']) && $_REQUEST['weight'] >= 0 && $_REQUEST['weight'] <= 1){
        $weight = floatval($_REQUEST['weight']);
    }
    if(isset($_REQUEST['default'])){
        $default = $_REQUEST['default'];
    }
}

//
// Generate the dummy data
//

// enable the customer error handler that converts warnings to errors
enableWarningToErrorConversion();

// try generate the records
$records = [];
try{
    foreach($recordDefinition as $fieldName => $field){
        $field->generator = $FAKER;
        if($unique || $field->unique){
            $field->generator = $field->generator->unique();
        }
    }
    for($i = 0; $i < $n; $i++){
        $r = (object)[];
        foreach($recordDefinition as $fieldName => $field){
            $fd = $recordDefinition->{$fieldName};
            if($fd->optional){
                $r->{$fd->name} = $fd->generator->optional($fd->weight, $fd->default)->format($fd->formatter, $fd->args);
            }else{
                $r->{$fd->name} = $fd->generator->format($fd->formatter, $fd->args);
            }
        }
        if($optional){
            $records[] = $FAKER->optional($weight, $default)->passthrough($r);
        }else{
            $records[] = $r;
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
    echo json_encode($records);
}else if($type === 'jsonText'){
    header('Content-Type: text/plain');
    echo json_encode($records, JSON_PRETTY_PRINT);
}else{
    // figure out what seperator to use
    $separator = isset($_REQUEST['separator']) ? $_REQUEST['separator'] : "\n";
    $valueSeparator = isset($_REQUEST['valueSeparator']) ? $_REQUEST['valueSeparator'] : ": ";
    $recordSeparator = isset($_REQUEST['recordSeparator']) ? $_REQUEST['recordSeparator'] : "\n\n";
    
    // return the data appropriately separated
    header('Content-Type: text/plain');
    $recordStrings = [];
    foreach($records as $record){
        $fieldStrings = [];
        foreach($record as $name => $value){
            $fieldStrings[] = $name.$valueSeparator.filterToPlainText($value);
        }
        $recordStrings[] = join($separator, $fieldStrings);
    }
    echo join($recordSeparator, $recordStrings);
}