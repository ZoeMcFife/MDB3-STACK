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

echo "Per island: the number of visits in October 2026 and the time of the last one, busiest island first\n";
echo "\n";

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
                '_id'       => '$islandName',
                'visits'    => ['$sum' => 1],
                'lastVisit' => ['$max' => '$visitTime'],
            ],
        ],
        [
            '$sort' => ['visits' => -1]
        ],
        [
            '$project' =>
            [
                '_id'       => 0,
                'islandName' => '$_id',
                'visits'    => 1,
                'lastVisit' => 1,
            ],
        ],
    ]
);

foreach ($rep1 as $visit)
{
    echo $visit->islandName . " " . $visit->visits . " Last Visit: " . $visit->lastVisit->toDateTime()->format('Y-m-d H:i:s') . "\n";
}

echo "\n";

// Visitors who came more than four times in total, most frequent first

echo "Visitors who came more than four times in total, most frequent first\n";
echo "\n";

$rep2 = $visits->aggregate
(
    [
        [
            '$group' =>
                [
                    '_id'       => ['$visitor', '$islandName'],
                    'visits'    => ['$sum' => 1],
                ],
        ],
        [
            '$match' =>
            [
                'visits' => ['$gte' => 5]
            ],
        ],
        [
            '$sort' => ['visits' => -1]
        ],
        [
            '$project' =>
                [
                    '_id'       => 0,
                    'visitEvent' => '$_id',
                    'visits'    => 1,
                ],
        ],
    ]
);

foreach ($rep2 as $visit)
{
    echo $visit->visitEvent[0] . " - " . $visit->visitEvent[1] . " - " . $visit->visits . "\n";
}

echo "\n";
