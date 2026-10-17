<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__ . '/../connect.php';
require_once 'IslandDao.php';
require_once 'VisitDao.php';

// ---------------------------------------------------------------
// Config
// ---------------------------------------------------------------

const ISLAND_COUNT        = 30;
const MAX_VILLAGERS       = 10;   // attempts per island (the DAO enforces the real cap)
const INVENTORY_PICKS     = [5, 25];
const VISITS_PER_ISLAND   = [10, 60];
const VISIT_WINDOW_DAYS   = 90;
const SEED                = 1234; // fixed seed = reproducible data; set to null for true random

if (SEED !== null) {
    mt_srand(SEED);
}

$islands    = $mongo->catalogue->islands;
$visits     = $mongo->catalogue->visits;
$itemsColl  = $mongo->catalogue->items;
$villagersColl = $mongo->catalogue->villagers;

$islandDAO = new IslandDao($islands);
$visitDAO  = new VisitDao($visits);

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

function pick(array $list): mixed
{
    return $list[array_rand($list)];
}

/** Pull a readable name out of a document (string or localized object). */
function nameOf(mixed $doc): string
{
    $name = $doc['name'] ?? (string)$doc['_id'];

    if (is_string($name)) {
        return $name;
    }

    $name = (array)$name;
    return (string)($name['name-USen'] ?? $name['en'] ?? reset($name));
}

/** Random sample of documents from a collection, or the fallback if it is empty. */
function sample($collection, int $size, array $fallback): array
{
    $docs = iterator_to_array($collection->aggregate([['$sample' => ['size' => $size]]]), false);

    if (count($docs) === 0) {
        return $fallback;
    }

    return array_map(fn($d) => [$d['_id'], nameOf($d)], $docs);
}

// ---------------------------------------------------------------
// Pools of random data
// ---------------------------------------------------------------

$firstNames = ["Zoe", "Kirsikka", "Mina", "Jade", "Florence", "Jack", "William", "Nathaniel",
    "Sasha", "Chris", "Blerg", "Luna", "Otto", "Priya", "Hana", "Mateo", "Ingrid", "Felix"];
$lastNames  = ["McFife", "Lumikettu", "Airfryer", "Arli", "Meean'kar", "Storm", "Moi", "Roberts",
    "Hollow", "Pancake", "Wobblesworth", "Nakamura", "Bergmann", "Okafor", "Silva"];

$islandPrefixes = ["Bizarre", "Sunny", "Misty", "Golden", "Snowy", "Lazy", "Wild", "Cozy", "Lost", "Tiny"];
$islandSuffixes = ["Island", "Isles", "Cay", "Atoll", "Shores", "Haven", "Reef", "Key"];

$fruits = ["Apple", "Cherry", "Orange", "Peach", "Pear", "Strawberry", "Pancake"];
$hemispheres = ["north", "south"];

$fallbackVillagers = [[1, "Admiral"], [3, "Agnes"], [13, "Ankha"], [17, "Apollo"], [20, "Audie"],
    [33, "Bella"], [48, "Bob"], [89, "Cleo"], [93, "Coco"], [106, "Daisy"], [112, "Diana"]];
$fallbackItems     = [[4483, "cat nose"], [4672, "curly moustache"], [1001, "oak table"], [1002, "tin can"]];

$villagerPool = sample($villagersColl, 400, $fallbackVillagers);
$itemPool     = sample($itemsColl, 400, $fallbackItems);

$players = [];
for ($i = 0; $i < 25; $i++) {
    $players[] = pick($firstNames) . " " . pick($lastNames);
}
$players = array_values(array_unique($players));

// ---------------------------------------------------------------
// Creating islands
// ---------------------------------------------------------------

echo "Creating " . ISLAND_COUNT . " islands: \n\n";

$created = []; // name => id

for ($i = 0; $i < ISLAND_COUNT; $i++) {
    $owner = pick($firstNames) . " " . pick($lastNames);
    $name  = pick($islandPrefixes) . " " . pick($islandSuffixes);

    if (isset($created[$name])) {
        $name .= " " . ($i + 1); // keep names unique
    }

    // about a third of the islands have no native fruit, like Blerg Islands
    $id = (mt_rand(0, 2) === 0)
        ? $islandDAO->create($name, $owner, pick($hemispheres))
        : $islandDAO->create($name, $owner, pick($hemispheres), pick($fruits));

    $created[$name] = $id;
    echo "  created: $name ($owner)\n";
}

echo "\n";

// ---------------------------------------------------------------
// Adding villagers
// ---------------------------------------------------------------

echo "Adding Villagers: \n\n";

foreach ($created as $islandName => $islandId) {
    $moved = 0;
    $rejected = 0;
    $attempts = mt_rand(3, MAX_VILLAGERS + 2); // sometimes overshoot so the "full" path gets hit too

    for ($i = 0; $i < $attempts; $i++) {
        [$vid, $vname] = pick($villagerPool); // may pick a duplicate on purpose -> returns false

        $ok = $islandDAO->moveIn($islandName, $vid, $vname);
        $ok ? $moved++ : $rejected++;
    }

    echo "  $islandName: $moved moved in, $rejected rejected (duplicate / full)\n";
}

echo "\n";

// ---------------------------------------------------------------
// Removing a few villagers
// ---------------------------------------------------------------

echo "Removing villagers: \n\n";

foreach ($created as $islandName => $islandId) {
    if (mt_rand(0, 2) !== 0) {
        continue;
    }

    $residents = $islandDAO->findByName($islandName)->residents ?? [];
    if (count($residents) === 0) {
        continue;
    }

    $resident = pick(iterator_to_array($residents, false));
    $residentId = is_array($resident) || is_object($resident)
        ? ((array)$resident)['resident'] ?? null
        : $resident;

    if ($residentId !== null) {
        echo "  $islandName: " . $islandDAO->moveOut($islandName, $residentId) . "\n";
    }
}

echo "\n";

// ---------------------------------------------------------------
// Adding items
// ---------------------------------------------------------------

echo "Adding items: \n\n";

foreach ($created as $islandName => $islandId) {
    $picks = mt_rand(...INVENTORY_PICKS);

    for ($i = 0; $i < $picks; $i++) {
        [$itemId, $itemName] = pick($itemPool);
        $islandDAO->addToInventory($islandName, $itemId, $itemName, mt_rand(1, 30));
    }

    echo "  $islandName: $picks item pickups\n";
}

echo "\n";

// ---------------------------------------------------------------
// Visits
// ---------------------------------------------------------------

echo "Visits: \n\n";

$now   = time();
$start = $now - VISIT_WINDOW_DAYS * 86400;

foreach ($created as $islandName => $islandId) {
    $count = mt_rand(...VISITS_PER_ISLAND);

    // generate random timestamps and sort them so visits are recorded chronologically
    $times = [];
    for ($i = 0; $i < $count; $i++) {
        $times[] = mt_rand($start, $now);
    }
    sort($times);

    foreach ($times as $ts) {
        $at = new UTCDateTime(new DateTimeImmutable("@$ts"));
        $visitDAO->record($islandId, pick($players), $at);
    }

    echo "  $islandName: $count visits\n";
}

echo "\n";

// ---------------------------------------------------------------
// Sanity check
// ---------------------------------------------------------------

$firstIsland = array_key_first($created);

echo "Sample island: \n\n";
var_dump($islandDAO->findByName($firstIsland));

echo "\nLatest visits on $firstIsland: \n\n";
var_dump($visitDAO->latest($created[$firstIsland], 20));