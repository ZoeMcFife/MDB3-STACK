<?php

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

class VisitDao
{
    public function __construct(private readonly Collection $visits) {}

    public function record(ObjectId $islandId, string $visitor, UTCDateTime $visitTime) : ObjectId
    {
        $visit =
            [
                'visitor' => $visitor,
                'islandId' => $islandId,
                'visitTime' => $visitTime,
            ];

        return $this->visits->insertOne($visit)->getInsertedId();
    }

    public function latest(ObjectId $islandId, int $n) : array
    {
        if ($n <= 0)
            return [];

        return $this->visits->find
        (
            [
                'islandId' => $islandId
            ],
            [
                'sort' => ['visitTime' => -1],
                'limit' => $n
            ]
        );
    }
}