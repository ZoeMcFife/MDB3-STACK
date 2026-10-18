<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__ . '/../connect.php';
require_once 'IslandDao.php';
require_once 'VisitDao.php';

$islands = $mongo->catalogue->islands;
$visits = $mongo->catalogue->visits;

$islandDAO = new IslandDao($islands);
$visitDAO = new VisitDao($visits);

// Per island: the number of visits in October 2026 and the time of the last one, busiest island first

$utc = new DateTimeZone('UTC');

$rep1 = $visits->aggregate
(
    [
        [
            '$match' =>
                [
                'visitTime' =>
                    [
                    '$gte' => new UTCDateTime(new DateTimeImmutable('2026-10-01 00:00:00', $utc)),
                    '$lt'  => new UTCDateTime(new DateTimeImmutable('2026-11-01 00:00:00', $utc)),
                ],
            ],
        ],
        [
            '$group' =>
                [
                    '_id' => '$islandName',
                    'count' => ['$sum' => 1]
                ],
        ]
    ],
    [
        'projection' => ['islandName' => 1, 'count' => 1],
    ]
);

foreach ($rep1 as $visit)
{
    echo $visit->islandName . " " . $visit->count . "\n";
}