<?php

require_once __DIR__ . '/../../connect.php';

$items = $mongo->catalogue->items;

$villagers = $mongo->catalogue->villagers;

$cursor = $items->find(
    ['type' => 'fish', 'whereHow' => 'River'],
    [
        'projection' => ['name' => 1, 'sell' => 1],
        'sort' => ['sell' => -1],
        'limit' => 3
    ]
);

foreach ($cursor as $item)
{
    echo $item->name.' : '.$item->sell."\n";
}

$admiral = $villagers->findOne(
    ['name' => 'Admiral']
);

echo $admiral->name.' : '.$admiral->home->flooring.' and owns '.count($admiral->furniture).' furniteres.'."\n";

$missing = $villagers->findOne(['name' => 'Missing']);

var_dump($missing);

$expensive = $items->countDocuments
(
    [
        'type' => ['$ne' => 'housewares'],
        'sell' => ['$gte' => 10000],
    ]
);

echo $expensive . " expensive\n";

$sandbox = $mongo->sandbox;

$result = $sandbox->villagers->insertOne
(
    [
        'name' => 'Zucker',
        'species' => 'Octopus',
        'furniture' => []
    ]
);

echo 'inserted - ' . $result->getInsertedId() . "\n";

$update = $sandbox->villagers->updateMany
(
    ['name' => 'Zucker'],
    ['$push' => ['furniture' => 717]]
);

echo "matched " . $update->getMatchedCount() . " - modified " . $update->getModifiedCount() . "\n";


$perType = $items->aggregate
(
    [
        'group' =>
        [
            '_id' => '$type',
            'n' => ['$sum' => 1],
            'avgSell' => ['$avg' => '$sell']
        ]
    ],
    [
        '$sort' => ['n' => -1]
    ],
    [
        '$limit' => 3
    ]
);

var_dump($perType);

