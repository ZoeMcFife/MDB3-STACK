<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__ . '/../connect.php';
require_once 'IslandDao.php';
require_once 'VisitDao.php';

date_default_timezone_set('UTC');

// ---------------------------------------------------------------
// Config
// ---------------------------------------------------------------

const ISLAND_COUNT    = 30;
const MAX_VILLAGERS   = 10;       // attempts per island (the DAO enforces the real cap)
const INVENTORY_PICKS = [5, 25];
const SEED            = 1234;     // fixed seed = reproducible data; set to null for true random

// Exercise 1: Admiral (villager 1) lives on exactly this many islands
const ADMIRAL_ID      = 1;
const ADMIRAL_ISLANDS = 6;

// Exercise 2: clay furnaces (item 717). One inner array = one island, one number = one pickup.
// Islands summing to 3+ must match, the last three are near misses (1, 2 and 1+1).
const FURNACE_ID      = 717;
const FURNACE_PICKUPS = [[3], [2, 2], [7], [12], [20], [1], [2], [1, 1]];

// Exercise 3 + report 1: fixed visit windows, so "October 2026" is always populated
const BEFORE_OCTOBER          = ['2026-07-20 00:00:00', '2026-09-30 23:59:59'];
const OCTOBER                 = ['2026-10-01 00:00:00', '2026-10-16 23:59:59'];
const SHOWCASE_OCTOBER_VISITS = 14;   // the first island gets the most October visits

// Report 2: weighted regulars, plus occasional visitors with an exact total.
// 1-4 visits must NOT show up in the report, 5 and 6 must.
const REGULARS = [
    "Kirsikka Lumikettu" => 14, "Blerg" => 12, "Jack Storm" => 10, "Mina Airfryer" => 9,
    "Jade Arli" => 8, "Sasha Moi" => 6, "Chris Roberts" => 5, "Florence Meean'kar" => 4,
];
const OCCASIONAL_VISITS = [1, 1, 2, 2, 3, 3, 4, 4, 4, 5, 5, 6];

if (SEED !== null) {
    mt_srand(SEED);
}

$islands       = $mongo->catalogue->islands;
$visits        = $mongo->catalogue->visits;
$itemsColl     = $mongo->catalogue->items;
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

/** Name of one specific document, so planted data matches the catalogue. */
function lookupName($collection, int $id, string $fallback): string
{
    $doc = $collection->findOne(['_id' => $id]);
    return $doc ? nameOf($doc) : $fallback;
}

/** Pick a key from [key => weight]. */
function weightedPick(array $weights): string
{
    $roll = mt_rand(1, array_sum($weights));

    foreach ($weights as $key => $weight) {
        $roll -= $weight;
        if ($roll <= 0) {
            return $key;
        }
    }

    return array_key_last($weights);
}

function randomTime(array $window): int
{
    return mt_rand(strtotime($window[0]), strtotime($window[1]));
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

$fallbackVillagers = [[1, "Admiral"], [3, "Agnes"], [13, "Ankha"], [17, "Apollo"], [20, "Audie"],
    [33, "Bella"], [48, "Bob"], [89, "Cleo"], [93, "Coco"], [106, "Daisy"], [112, "Diana"]];
$fallbackItems     = [[4483, "cat nose"], [4672, "curly moustache"], [1001, "oak table"], [1002, "tin can"]];

// Admiral and the clay furnace are only ever placed on purpose, never by the random pools
$villagerPool = array_values(array_filter(
    sample($villagersColl, 400, $fallbackVillagers),
    fn($v) => $v[0] != ADMIRAL_ID
));
$itemPool = array_values(array_filter(
    sample($itemsColl, 400, $fallbackItems),
    fn($i) => $i[0] != FURNACE_ID
));

// ---------------------------------------------------------------
// Creating islands
// ---------------------------------------------------------------

echo "Creating " . ISLAND_COUNT . " islands: \n\n";

$created = []; // name => id
$southOrNoFruit = 0;

for ($i = 0; $i < ISLAND_COUNT; $i++) {
    do {
        $name = pick($islandPrefixes) . " " . pick($islandSuffixes);
    } while (isset($created[$name]));

    $owner      = pick($firstNames) . " " . pick($lastNames);
    $hemisphere = ($i % 3 === 0) ? "south" : "north";   // every 3rd island is southern
    $fruit      = ($i % 5 === 0) ? null : pick($fruits); // every 5th island has no native fruit

    $created[$name] = ($fruit === null)
        ? $islandDAO->create($name, $owner, $hemisphere)
        : $islandDAO->create($name, $owner, $hemisphere, $fruit);

    if ($hemisphere === "south" || $fruit === null) {
        $southOrNoFruit++;
    }

    echo "  created: $name ($owner, $hemisphere, " . ($fruit ?? "no fruit") . ")\n";
}

$names    = array_keys($created);
$showcase = $names[0];

echo "\n";

// ---------------------------------------------------------------
// Adding villagers
// ---------------------------------------------------------------

echo "Adding Villagers: \n\n";

// Admiral first, so a full island can't turn him away
$admiralName = lookupName($villagersColl, ADMIRAL_ID, "Admiral");
$shuffled = $names;
shuffle($shuffled);
$admiralIslands = array_slice($shuffled, 0, ADMIRAL_ISLANDS);

foreach ($admiralIslands as $islandName) {
    echo "  $islandName: Admiral moved in -> " . $islandDAO->moveIn($islandName, ADMIRAL_ID, $admiralName) . "\n";
}

echo "\n";

$placed = []; // islandName => [villager ids that actually moved in]

foreach ($names as $islandName) {
    $moved = 0;
    $rejected = 0;
    $attempts = mt_rand(3, MAX_VILLAGERS + 2); // sometimes overshoot so the "full" path gets hit too

    for ($i = 0; $i < $attempts; $i++) {
        [$vid, $vname] = pick($villagerPool); // may pick a duplicate on purpose -> returns false

        if ($islandDAO->moveIn($islandName, $vid, $vname)) {
            $placed[$islandName][] = $vid;
            $moved++;
        } else {
            $rejected++;
        }
    }

    echo "  $islandName: $moved moved in, $rejected rejected (duplicate / full)\n";
}

echo "\n";

// ---------------------------------------------------------------
// Removing a few villagers (never Admiral, he isn't in $placed)
// ---------------------------------------------------------------

echo "Removing villagers: \n\n";

foreach ($names as $islandName) {
    if (empty($placed[$islandName]) || mt_rand(0, 2) !== 0) {
        continue;
    }

    echo "  $islandName: " . $islandDAO->moveOut($islandName, pick($placed[$islandName])) . "\n";
}

echo "\n";

// ---------------------------------------------------------------
// Adding items
// ---------------------------------------------------------------

echo "Adding items: \n\n";

// clay furnaces first, so a full inventory can't drop them
$furnaceName = lookupName($itemsColl, FURNACE_ID, "clay furnace");
shuffle($shuffled);
$furnaceIslands = array_slice($shuffled, 0, count(FURNACE_PICKUPS));

foreach (FURNACE_PICKUPS as $k => $pickups) {
    foreach ($pickups as $quantity) {
        $islandDAO->addToInventory($furnaceIslands[$k], FURNACE_ID, $furnaceName, $quantity);
    }

    echo "  {$furnaceIslands[$k]}: " . array_sum($pickups) . " clay furnaces\n";
}

echo "\n";

foreach ($names as $islandName) {
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

// 1. decide when each island gets visited: [islandName, timestamp, player]
$slots = [];

foreach ($names as $islandName) {
    $october = ($islandName === $showcase) ? SHOWCASE_OCTOBER_VISITS : mt_rand(0, 10);
    $earlier = mt_rand(5, 40);

    for ($i = 0; $i < $october; $i++) {
        $slots[] = [$islandName, randomTime(OCTOBER), null];
    }
    for ($i = 0; $i < $earlier; $i++) {
        $slots[] = [$islandName, randomTime(BEFORE_OCTOBER), null];
    }

    echo "  $islandName: $october visits in October, $earlier before\n";
}

// 2. decide who visits: occasional visitors get an exact total, regulars fill the rest
shuffle($slots);

$occasionals = [];
while (count($occasionals) < count(OCCASIONAL_VISITS)) {
    $player = pick($firstNames) . " " . pick($lastNames);

    if (!isset(REGULARS[$player]) && !in_array($player, $occasionals, true)) {
        $occasionals[] = $player;
    }
}

$next = 0;
foreach ($occasionals as $k => $player) {
    for ($v = 0; $v < OCCASIONAL_VISITS[$k]; $v++) {
        $slots[$next++][2] = $player;
    }
}
for (; $next < count($slots); $next++) {
    $slots[$next][2] = weightedPick(REGULARS);
}

// 3. record everything in chronological order
usort($slots, fn($a, $b) => $a[1] <=> $b[1]);

foreach ($slots as [$islandName, $ts, $player]) {
    $at = new UTCDateTime(new DateTimeImmutable("@$ts"));
    $visitDAO->record($created[$islandName], $player, $at);
}

echo "\n  " . count($slots) . " visits recorded\n\n";

// ---------------------------------------------------------------
// What was planted (compare with your query results)
// ---------------------------------------------------------------

$furnaceMatches = count(array_filter(FURNACE_PICKUPS, fn($p) => array_sum($p) >= 3));
$frequentVisitors = count(REGULARS) + count(array_filter(OCCASIONAL_VISITS, fn($n) => $n > 4));

echo "Expected results: \n\n";
echo "  Query 1: Admiral lives on " . ADMIRAL_ISLANDS . " islands: " . implode(", ", $admiralIslands) . "\n";
echo "  Query 2: $furnaceMatches islands with 3+ clay furnaces\n";
echo "  Query 3: use '$showcase' (" . SHOWCASE_OCTOBER_VISITS . " visits in October 2026)\n";
echo "  Query 4: $southOrNoFruit islands are southern or have no native fruit\n";
echo "  Report 1: '$showcase' is busiest in October 2026 (" . SHOWCASE_OCTOBER_VISITS . " visits)\n";
echo "  Report 2: $frequentVisitors visitors with more than four visits\n\n";

echo "Latest visits on $showcase: \n\n";
var_dump($visitDAO->latest($created[$showcase], 5));