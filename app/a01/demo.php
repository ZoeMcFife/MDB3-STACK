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

echo $islandDAO->findById($island1);
echo $islandDAO->findById($island2);
echo $islandDAO->findById($island3);

// adding villagers

echo $islandDAO->moveIn("Zoe's Bizarre Island", 13, "Ankha");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 20, "Audie");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 1, "Admiral");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 3, "Agnes");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 17, "Apollo");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 33, "Bella");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 48, "Bob");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 89, "Cleo");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 93, "Coco");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 106, "Daisy");
echo $islandDAO->moveIn("Zoe's Bizarre Island", 112, "Diana");

echo ($islandDAO->moveIn("Zoe's Bizarre Island", 112, "Diana")); // duplicate
echo ($islandDAO->moveIn("Zoe's Bizarre Island", 36, "Bertha")); // sorry Bertha, we're full


// removing villagers

echo $islandDAO->moveOut("Zoe's Bizarre Island", 48);


echo ($islandDAO->findByName("Zoe's Bizarre Island")->residents);

// adding items

echo $islandDAO->addToInventory("Zoe's Bizarre Island", 4483, "cat nose", 24);
echo $islandDAO->addToInventory("Zoe's Bizarre Island", 4483, "cat nose", 24);
echo $islandDAO->addToInventory("Zoe's Bizarre Island", 4672, "curly moustache", 1);

echo ($islandDAO->findByName("Zoe's Bizarre Island")->items);

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

foreach ($visits as [$player, $time])
{
    $at = new UTCDateTime(new DateTimeImmutable($time, new DateTimeZone('UTC')));
    echo $visitDAO->record($island1, $player, $at);
}

echo $visitDAO->latest($island1, 20);