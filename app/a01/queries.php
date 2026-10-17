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

// Query 2 - All islands whose inventory has at least three clay furnaces (item 717)

