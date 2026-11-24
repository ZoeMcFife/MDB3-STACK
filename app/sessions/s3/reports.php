<?php
// Session 3: a $lookup report and a validation error, from PHP.
// Create this file as app/sessions/s3/reports.php and run it with:
//   docker compose exec php php sessions/s3/reports.php
require __DIR__ . '/../../connect.php';

// the most owned furniture with names: the pipeline from mongosh, as arrays
$rows = $mongo->catalogue->villagers->aggregate([
    ['$unwind' => '$furniture'],
    ['$group'  => ['_id' => '$furniture', 'owners' => ['$sum' => 1]]],
    ['$sort'   => ['owners' => -1, '_id' => 1]],
    ['$limit'  => 5],
    ['$lookup' => ['from' => 'items', 'localField' => '_id', 'foreignField' => '_id', 'as' => 'item']],
    ['$unwind' => '$item'],
    ['$project' => ['_id' => 0, 'item' => '$item.name', 'owners' => 1]],
]);
foreach ($rows as $row) {
    printf("%-22s %2d owners\n", $row->item, $row->owners);
}

// a validator refuses from PHP too: the exception carries code 121 and the failing rule
try {
    $mongo->sandbox->villagers->insertOne(['name' => 'Nobody', 'species' => 'Cat', 'personality' => 'Grumpy',
                                           'birthday' => '13-1', 'furniture' => [], 'schemaVersion' => 2]);
} catch (MongoDB\Driver\Exception\BulkWriteException $e) {
    $err = $e->getWriteResult()->getWriteErrors()[0];
    echo 'refused: code ', $err->getCode(), ', ', $err->getMessage(), "\n";
    $why = $err->getInfo()->details->schemaRulesNotSatisfied[0]->propertiesNotSatisfied[0];
    echo 'field: ', $why->propertyName, ', rule: ', $why->details[0]->operatorName, "\n";
}
