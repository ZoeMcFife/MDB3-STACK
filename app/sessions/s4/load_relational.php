<?php
// Session 4 starter: the catalogue as tables. Reads catalogue.items and catalogue.villagers from MongoDB
// and loads them into CockroachDB as items, villagers and villager_furniture (the junction table).
// Run once:  docker compose exec php php sessions/s4/load_relational.php
require __DIR__ . '/../../connect.php';

$crdb->exec("DROP DATABASE IF EXISTS catalogue CASCADE");     // start from nothing, every time
$crdb->exec("CREATE DATABASE catalogue");
$crdb->exec("SET database = catalogue");
$crdb->exec("CREATE TABLE items (
    id INT PRIMARY KEY, name STRING NOT NULL, type STRING NOT NULL,
    buy DECIMAL(10,1), sell DECIMAL(10,1), source STRING)");
$crdb->exec("CREATE TABLE villagers (
    id INT PRIMARY KEY, name STRING NOT NULL UNIQUE, species STRING NOT NULL,
    personality STRING NOT NULL, birthday_month INT NOT NULL, birthday_day INT NOT NULL)");
$crdb->exec("CREATE TABLE villager_furniture (
    villager_id INT NOT NULL REFERENCES villagers(id), position INT NOT NULL,
    item_id INT NOT NULL REFERENCES items(id), PRIMARY KEY (villager_id, position))");

function insertRows(PDO $db, string $sql, array $rows, int $width): int {
    $n = 0;
    foreach (array_chunk($rows, 500) as $chunk) {
        $ph = implode(',', array_fill(0, count($chunk), '(' . implode(',', array_fill(0, $width, '?')) . ')'));
        $stmt = $db->prepare($sql . ' VALUES ' . $ph);
        $stmt->execute(array_merge(...$chunk));
        $n += count($chunk);
    }
    return $n;
}

$items = []; $itemIds = [];
foreach ($mongo->catalogue->items->find([], ['projection' => ['name' => 1, 'type' => 1, 'buy' => 1, 'sell' => 1, 'source' => 1]]) as $i) {
    $items[] = [$i->_id, $i->name, $i->type, $i->buy ?? null, $i->sell ?? null, $i->source ?? null];
    $itemIds[$i->_id] = true;
}
$villagers = []; $junction = []; $skipped = 0;
foreach ($mongo->catalogue->villagers->find([], ['projection' => ['name' => 1, 'species' => 1, 'personality' => 1, 'birthday' => 1, 'furniture' => 1]]) as $v) {
    $villagers[] = [$v->_id, $v->name, $v->species, $v->personality, $v->birthday->month, $v->birthday->day];
    foreach ($v->furniture as $pos => $itemId) {
        if (isset($itemIds[$itemId])) { $junction[] = [$v->_id, $pos, $itemId]; } else { $skipped++; }
    }
}
printf("items:              %d rows\n", insertRows($crdb, 'INSERT INTO items (id, name, type, buy, sell, source)', $items, 6));
printf("villagers:          %d rows\n", insertRows($crdb, 'INSERT INTO villagers (id, name, species, personality, birthday_month, birthday_day)', $villagers, 6));
printf("villager_furniture: %d rows (%d skipped, item not in catalogue)\n", insertRows($crdb, 'INSERT INTO villager_furniture (villager_id, position, item_id)', $junction, 3), $skipped);
