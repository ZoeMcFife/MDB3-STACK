<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__ . '/../connect.php';
require_once 'IslandDao.php';
require_once 'VisitDao.php';

$islands = $mongo->catalogue->islands;
$visits = $mongo->catalogue->visits;

$islandDAO = new IslandDao($islands);
$visitDAO = new VisitDao($visits);

// Query 1 - All islands where villager 1 (Admiral) lives, names only

echo "Query 1: \n";

$islandsWithAdmiral =
    $islands->find
    (
        [
            'residents.villagerName' => 'Admiral'
        ],
        [
            'projection' => ['islandName' => 1]
        ]
    );

foreach ($islandsWithAdmiral as $island)
{
    echo $island->islandName . "\n";
}

echo "\n";

// Query 2 - All islands whose inventory has at least three clay furnaces (item 717)

echo "Query 2: \n";

$islandsWithThreeClayFurnaces =
    $islands->find(
        [
            'inventory' => [
                '$elemMatch' => [
                    'itemId' => 717,
                    'count'  => ['$gte' => 3],
                ],
            ],
        ],
        [
            'projection' => ['islandName' => 1, 'inventory' => 1],
        ]
    );

foreach ($islandsWithThreeClayFurnaces as $island)
{
    echo $island->islandName . "\n";
    foreach ($island->inventory as $inventory)
    {
        if ($inventory->itemId == 717)
        {
            echo $inventory->itemName . "\n";
            echo $inventory->count . "\n";
        }


    }
    echo "\n";
}

echo "\n";

// Query 3 - the five newest visits to one island in October 2026, newest first

echo "Query 3: \n";

$islandEx3 = $islands->findOne
(
    [
        'islandName' => "Snowy Shores"
    ]
);

$utc = new DateTimeZone('UTC');

$fiveNewest =
    $visits->find
    (
        [
            'islandId' => $islandEx3->_id,
            'visitTime' => [
                '$gte' => new UTCDateTime(new DateTimeImmutable('2026-10-01 00:00:00', $utc)),
                '$lt'  => new UTCDateTime(new DateTimeImmutable('2026-11-01 00:00:00', $utc)),
            ]
        ],
        [
            'sort' => ['visitTime' => -1],
            'limit' => 5
        ]
    );

foreach ($fiveNewest as $visit)
{
    echo $visit->visitor . "\n";
    echo $visit->visitTime . "\n";
    echo "\n";
}

echo "\n";

// Query 4 - All islands that are in the southern hemisphere or have no native fruit recorded

echo "Query 4: \n";

$islandSouthOrNoFruit =
    $islands->find
    (
        [
            '$or' =>
                [['hemisphere' => 'south'],
                ['nativeFruit' => ['$exists' => false]]]
        ]
    );

foreach ($islandSouthOrNoFruit as $island)
{
    echo $island->islandName . "\n";
    echo $island->hemisphere . "\n";
    echo $island->nativeFruit . "\n";
    echo "\n";
}