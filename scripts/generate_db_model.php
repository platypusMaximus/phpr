#!/usr/bin/php
<?

require_once dirname(__FILE__) . "/../base.php";

/*
 * Generates db models from all user created schemas
 */

$getAllSchemas = "
           SELECT
             *
           FROM
             INFORMATION_SCHEMA.COLUMNS
           WHERE
             table_schema NOT IN (
               'information_schema',
               'mysql',
               'performance_schema')
           ORDER BY
             table_schema,
             table_name,
             ordinal_position";

$rows = \Database\Model\Generic::query($getAllSchemas);
$schema = null;
$table = null;

// each row is a new column in a specific table
foreach ( $rows as $index => $row ) {

    $newSchema = $schema !== $row->TABLE_SCHEMA;
    $newTable = $table !== $row->TABLE_NAME;

    // new schema?
    if ($newSchema) {
        $schema = $row->TABLE_SCHEMA;
        $safeSchema = classSafeName($schema);
    }

    // new table?
    if ($newTable) {
        $table = $row->TABLE_NAME;
        $safeTable = classSafeName($table);
    }

    // does this row belong to a different table the last one?
    if ( $newSchema || $newTable ) {

        // save file if we have one
        if (isset($coreGenerator)) {

            save();
        }

        // create class name
        $namespace = 'DB\\' . $safeSchema;
        $name = $safeTable;
        $coreNamespace = 'DBCore\\' . $safeSchema;
        $coreName = $safeTable;

        // create file path
        $filepath = strtolower( $_SERVER['R_DOCUMENT_ROOT'] . '/db/' . $safeSchema . '/' . $name . '.php');
        $coreFilepath = strtolower($_SERVER['R_DOCUMENT_ROOT'] . '/dbcore/' . $safeSchema . '/' . $coreName . '.php');

        $generator = new \ClassGen\ClassGenGenerator(new \ClassGen\ClassGenClass($name, '\\' . $coreNamespace . '\\' .$coreName, $namespace), $filepath);
        $coreGenerator = new \ClassGen\ClassGenGenerator(new \ClassGen\ClassGenClass($coreName, '\Database\Model', $coreNamespace), $coreFilepath);

        // add table and schema name
        $schemaProperty = new \ClassGen\ClassGenProperty('schema', $row->TABLE_SCHEMA);
        $tableProperty = new \ClassGen\ClassGenProperty('table', $row->TABLE_NAME);

        $schemaProperty->set_const();
        $tableProperty->set_const();

        $coreGenerator->addProperty($schemaProperty);
        $coreGenerator->addProperty($tableProperty);

        // reset these
        $autoIncrementColumn = '';
        $primaryKeys = [];
        $DBColumnsArray = [];

        // add comments to the class
        $date = date('Y/m/d');
        $generator->class->phpDoc =
"/**
  * @author jrobinson (robotically)
  * @date {$date}
  * This file is only generated once
  * Put your class specific code in here
  */";

        $coreGenerator->class->phpDoc =
"/**
  * @author jrobinson (robotically)
  * @date {$date}
  * AUTO-GENERATED FILE
  * DO NOT EDIT THIS FILE BECAUSE IT WILL BE LOST
  * Put your code in " . $generator->class->name . "
  */";

    }

    // add this column to the current file
    $property = new \ClassGen\ClassGenProperty($row->COLUMN_NAME);

    $coreGenerator->addProperty($property);

    // add all columns to helper array
    $DBColumnsArray[$row->COLUMN_NAME] = [];

    // add special properties
    if ( $row->COLUMN_KEY === 'PRI' ) {
        $primaryKeys[] = $row->COLUMN_NAME;
        $DBColumnsArray[$row->COLUMN_NAME][] = \Database\Model::PRIMARY_KEY;
    }

    // parse extra column properties into array
    $extras = explode(',', $row->EXTRA);
    if ( in_array('auto_increment', $extras) ) {
        $autoIncrementColumn = $row->COLUMN_NAME;
        $DBColumnsArray[$row->COLUMN_NAME][] = \Database\Model::AUTO_INCREMENT;
    }


    if ( $rows->isLastRow() ) {

        save();

    }

}

function classSafeName ( $name ) {

    // strip out anything that isn't a letter
    $name = preg_split('/[^a-zA-Z]/', $name, -1, PREG_SPLIT_NO_EMPTY);

    // capitalize first letter in each word
    $name = array_map(
        function($word) {
            return ucwords($word);
        }, $name);

    // glue all words back together
    $name = implode('', $name);

    return $name;

}

function save () {

    global $generator, $coreGenerator, $autoIncrementColumn, $primaryKeys, $DBColumnsArray;

    // add the primary keys and autoincrement columns
    $AIProperty = new \ClassGen\ClassGenProperty('autoIncrementColumn', $autoIncrementColumn);
    $AIProperty->set_const();

    $primaryKeys = new \ClassGen\ClassGenProperty('primaryKeys', $primaryKeys);
    $primaryKeys->set_const();

    $coreGenerator->addProperty($AIProperty);
    $coreGenerator->addProperty($primaryKeys);
    $coreGenerator->addProperty(new \ClassGen\ClassGenProperty('DBColumnsArray', $DBColumnsArray));

    // save file if we have one
    if (isset($coreGenerator)) {
        $coreGenerator->save();
    }

    if ( !file_exists($generator->filepath) ) {
        $generator->save();
    }

}
