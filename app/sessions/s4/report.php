<?php
// Session 6 (23 Nov), NewSQL I: the same database from PHP. It is the PDO you know. Steps 2 and 3 are optional.
// Create this file as app/sessions/s4/report.php and run it with:
//   docker compose exec php php sessions/s4/report.php
require __DIR__ . '/../../connect.php';
$crdb->exec("SET database = catalogue");

// the GROUP BY report, printed from PHP
$rows = $crdb->query("SELECT type, count(*) AS n, round(avg(sell)) AS avg_sell FROM items
                      GROUP BY type ORDER BY n DESC LIMIT 3");
foreach ($rows as $row) {
    printf("%-10s %4d items, avg %6d Bells\n", $row['type'], $row['n'], $row['avg_sell']);
}

// the transfer as a PDO transaction: a failure in between rolls everything back
function ownersOf(PDO $db, int $item): string {
    $names = $db->query("SELECT v.name FROM villager_furniture vf JOIN villagers v ON v.id = vf.villager_id
                         WHERE vf.item_id = $item ORDER BY v.name")->fetchAll(PDO::FETCH_COLUMN);
    return $names ? implode(', ', $names) : 'nobody';
}
// a known starting point, so the script can run again: Boone has the furnace, Admiral does not
$crdb->exec("DELETE FROM villager_furniture WHERE item_id = 717
             AND villager_id IN (SELECT id FROM villagers WHERE name IN ('Admiral', 'Boone'))");
$crdb->exec("INSERT INTO villager_furniture (villager_id, position, item_id) SELECT id, 100, 717 FROM villagers WHERE name = 'Boone'");
try {
    $crdb->beginTransaction();
    $crdb->exec("DELETE FROM villager_furniture WHERE villager_id = (SELECT id FROM villagers WHERE name = 'Boone') AND item_id = 717");
    throw new RuntimeException('connection lost');
    $crdb->exec("INSERT INTO villager_furniture (villager_id, position, item_id) SELECT id, 100, 717 FROM villagers WHERE name = 'Admiral'");
    $crdb->commit();
} catch (RuntimeException $e) {
    $crdb->rollBack();
    echo 'rolled back: 717 is owned by ', ownersOf($crdb, 717), "\n";
}

// and committed: the two rows change together
$crdb->beginTransaction();
$crdb->exec("DELETE FROM villager_furniture WHERE villager_id = (SELECT id FROM villagers WHERE name = 'Boone') AND item_id = 717");
$crdb->exec("INSERT INTO villager_furniture (villager_id, position, item_id) SELECT id, 100, 717 FROM villagers WHERE name = 'Admiral'");
$crdb->commit();
echo 'committed:   717 is owned by ', ownersOf($crdb, 717), "\n";
