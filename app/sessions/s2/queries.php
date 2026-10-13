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