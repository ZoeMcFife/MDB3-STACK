<?php

require_once __DIR__ . '/../connect.php';

use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use MongoDB\BSON\UTCDateTime;

class IslandDao
{
    private const MAX_RESIDENTS = 10;

    public function __construct(private readonly Collection $islands) {}

    public function create(string $name, string $owner, string $hemisphere, ?string $nativeFruit = null) : ObjectId
    {
        if (!in_array($hemisphere, ['north', 'south'], true))
        {
            throw new InvalidArgumentException('hemisphere must be north or south');
        }

        $island =
            [
                'islandName' => $name,
                'owner' => $owner,
                'hemisphere' => $hemisphere,
                'creationDate' => new UTCDateTime(),
                'residents' => [],
                'inventory' => [],
            ];

        if ($nativeFruit !== null)
        {
            $island['nativeFruit'] = $nativeFruit;
        }

        return $this->islands->insertOne($island)->getInsertedId();
    }

    public function findById(ObjectId $id): ?object
    {
        return $this->islands->findOne(['_id' => $id]);
    }

    public function findByName(string $name) : ?object
    {
        return $this->islands->findOne(['islandName' => $name]);
    }

    public function findByResident(string $residentName) : Array
    {
        return $this->islands->find(['residents' => $residentName])->toArray();
    }

    public function moveIn(string $name, int $villagerId, string $villagerName) : bool
    {
        $result = $this->islands->updateOne
        (
            [
                'islandName' => $name,
                'residents' => ['$ne' => $villagerId],
                'residents.' . (self::MAX_RESIDENTS - 1) => ['$exists' => false]
            ],
            [
                '$push' => ['residents' => ['villagerName' => $villagerName, 'villagerId' => $villagerId]],
            ]
        );

        return $result->getModifiedCount() === 1;
    }

    public function moveOut(string $name, int $villagerId) : bool
    {
        $result = $this->islands->updateOne
        (
            [
                'islandName' => $name
            ],
            [
                '$pull' => ['residents' => ['villagerId' => $villagerId]],
            ]
        );

        return $result->getModifiedCount() === 1;
    }

    public function addToInventory(string $name, int $itemId, string $itemName, int $count) : void
    {
        if ($count === 0)
        {
            return;
        }

        $updated = $this->islands->updateOne
        (
            [
                'islandName' => $name,
                'inventory.itemId' => $itemId,
            ],
            [
                '$inc' => ['inventory.$.count' => $count],
            ]
        );

        if ($updated->getModifiedCount() === 0)
        {
            $this->islands->updateOne
            (
                [
                    'islandName' => $name
                ],
                [
                    '$push' => ['inventory' => ['itemId' => $itemId, 'itemName' => $itemName, 'count' => $count]],
                ]
            );
        }
    }

    public function rename(string $old, string $new) : bool
    {
        if ($new === $old)
            return false;

        $result = $this->islands->updateOne
        (
            [
                'islandName' => $old
            ],
            [
                '$rename' => ['name' => $new]
            ]
        );

        return $result->getModifiedCount() === 1;
    }

    public function delete(string $name) : bool
    {
        $result = $this->islands->deleteOne
        (
            [
                'islandName' => $name
            ]
        );

        return $result->getDeletedCount() === 1;
    }
}