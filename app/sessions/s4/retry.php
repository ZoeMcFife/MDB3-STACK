<?php
// Session 6 (23 Nov): serializable means "one of you goes first". The other one retries.
// Runs on the single node and on the cluster. Create this file as app/sessions/s4/retry.php and run it with:
//   docker compose exec php php sessions/s4/retry.php
require __DIR__ . '/../../connect.php';

// two clients, two connections: Alice and Bob both want to raise the
// furnace's price by 100
function client(): PDO {
    $dsn = 'pgsql:host=cockroach1;port=26257;dbname=catalogue;user=root';
    $c = new PDO($dsn);
    $c->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $c;
}
$alice = client();
$bob = client();
$read = "SELECT sell FROM items WHERE id = 717";
$write = "UPDATE items SET sell = ? WHERE id = 717";
$crdb->exec("SET database = catalogue");
$crdb->prepare($write)->execute([825]);

// the interleaving by hand: both read, then both write
$alice->beginTransaction();
$bob->beginTransaction();
$a = $alice->query($read)->fetchColumn();  // Alice reads 825
$b = $bob->query($read)->fetchColumn();  // Bob reads 825
$alice->prepare($write)->execute([$a + 100]);
$alice->commit();  // Alice commits 925
try {
    $bob->prepare($write)->execute([$b + 100]);
    $bob->commit();
    echo "Bob committed too\n";
} catch (PDOException $e) {
    echo "Bob: SQLSTATE ", $e->getCode(), ": ",
         substr($e->getMessage(), 0, 90), "...\n";
    $bob->rollBack();
}
echo "after both: sell = ", $crdb->query($read)->fetchColumn(), "\n";

// the retry loop: run the whole transaction again until it commits
function withRetry(PDO $db, callable $txn, int $maxAttempts = 5): int {
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $db->beginTransaction();
            $txn($db);
            $db->commit();
            return $attempt;
        } catch (PDOException $e) {
            $db->rollBack();
            // only serialization failures are retried
            if ($e->getCode() !== '40001') { throw $e; }
            usleep(50000 * $attempt);  // back off a little
        }
    }
    throw new RuntimeException("gave up after $maxAttempts attempts");
}
$raise = function (PDO $db) use ($read, $write) {
    $sell = $db->query($read)->fetchColumn();
    $db->prepare($write)->execute([$sell + 100]);
};

// the same interleaving, but Bob's transaction is the retried kind
$alice->beginTransaction();
$aliceSell = $alice->query($read)->fetchColumn();
// Bob starts and reads while Alice is still open ...
$bob->beginTransaction();
$bobSell = $bob->query($read)->fetchColumn();
// ... Alice commits first ...
$alice->prepare($write)->execute([$aliceSell + 100]);
$alice->commit();
// ... and Bob's first attempt fails, so he runs the whole transaction
// again through withRetry
try {
    $bob->prepare($write)->execute([$bobSell + 100]);
    $bob->commit();
    $attempts = 1;
} catch (PDOException $e) {
    $bob->rollBack();
    $attempts = 1 + withRetry($bob, $raise);
}
echo "Bob needed $attempts attempt(s); after both: sell = ",
     $crdb->query($read)->fetchColumn(), "\n";
