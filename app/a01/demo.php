<?php

use MongoDB\BSON\UTCDateTime;

require_once __DIR__ . '/../connect.php';
require_once 'IslandDao.php';
require_once 'VisitDao.php';

$islands = $mongo->catalogue->islands;
$visits = $mongo->catalogue->visits;

$islandDAO = new IslandDao($islands);
$visitDAO = new VisitDao($visits);

// Creating three islands

$island1 = $islandDAO->create("Zoe's Bizarre Island", "Zoe McFife", "north", "Strawberry");
$island2 = $islandDAO->create("Blerg Islands", "Blerg", "south");
$island3 = $islandDAO->create("Snow", "Kirsikka Lumikettu", "north", "Pancake");

echo "Islands: \n";

var_dump($islandDAO->findById($island1));
var_dump($islandDAO->findById($island2));
var_dump($islandDAO->findById($island3));

echo "\n";

// adding villagers

echo "Adding Villagers: \n\n";

echo $islandDAO->moveIn("Zoe's Bizarre Island", 13, "Ankha") . "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 20, "Audie"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 1, "Admiral"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 3, "Agnes"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 17, "Apollo"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 33, "Bella"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 48, "Bob"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 89, "Cleo"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 93, "Coco"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 106, "Daisy"). "\n";
echo $islandDAO->moveIn("Zoe's Bizarre Island", 112, "Diana"). "\n";

echo "expecting - false two times: \n";
echo ($islandDAO->moveIn("Zoe's Bizarre Island", 112, "Diana")). "\n"; // duplicate
echo ($islandDAO->moveIn("Zoe's Bizarre Island", 36, "Bertha")). "\n"; // sorry Bertha, we're full


// removing villagers

echo "Removing villager: \n";
echo $islandDAO->moveOut("Zoe's Bizarre Island", 48). "\n";

echo "all villagers: \n\n";

var_dump(($islandDAO->findByName("Zoe's Bizarre Island")->residents));

echo "\n";

// adding items

$islandDAO->addToInventory("Zoe's Bizarre Island", 4483, "cat nose", 24);
$islandDAO->addToInventory("Zoe's Bizarre Island", 4483, "cat nose", 24);
$islandDAO->addToInventory("Zoe's Bizarre Island", 4672, "curly moustache", 1);

echo "Inventory: cat nose -> 48 ; moustache -> 1\n\n";

var_dump(($islandDAO->findByName("Zoe's Bizarre Island")->inventory));

echo "\n";

// visits

// let a clanker make the array
$visits = [
    ["Kirsikka Lumikettu",        '2026-08-18 09:12'],
    ["Blerg",                     '2026-08-21 17:45'],
    ["Mina Airfryer",             '2026-08-24 20:03'],
    ["Jade Arli",                 '2026-08-27 11:30'],
    ["Florence Meean'kar",        '2026-08-30 15:18'],
    ["Jack Storm",                '2026-09-02 08:55'],
    ["William Storm",             '2026-09-05 19:40'],
    ["Captain Nathaniel Storm",   '2026-09-08 13:22'],
    ["Sasha Moi",                 '2026-09-11 21:07'],
    ["Chris Roberts",             '2026-09-14 10:15'],
    ["Kirsikka Lumikettu",        '2026-09-17 16:48'],
    ["Jack Storm",                '2026-09-20 12:01'],
    ["Blerg",                     '2026-09-23 18:36'],
    ["Jade Arli",                 '2026-09-26 09:59'],
    ["Mina Airfryer",             '2026-09-29 14:27'],
    ["Florence Meean'kar",        '2026-10-02 20:50'],
    ["Sasha Moi",                 '2026-10-06 11:11'],
    ["Chris Roberts",             '2026-10-10 17:33'],
    ["Captain Nathaniel Storm",   '2026-10-14 08:42'],
    ["William Storm",             '2026-10-17 03:00'],
];

echo "Visits: \n \n";

foreach ($visits as [$player, $time])
{
    $at = new UTCDateTime(new DateTimeImmutable($time, new DateTimeZone('UTC')));
    echo $visitDAO->record($island1, $player, $at). "\n";
}

echo "\n";

echo "visit vardump: \n\n";

var_dump($visitDAO->latest($island1, 20));