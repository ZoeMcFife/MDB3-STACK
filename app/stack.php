<?php
// Moderne Datenbanken WS 2026/27 - the stack's own check. Run inside the stack:
//   docker compose exec php php stack.php
// It opens the three connections and prints one line per system. The importer uses it too.
// You write your own connect.php in the sessions; this file is only here so the setup can be checked.
require __DIR__ . '/vendor/autoload.php';

$mongo = new MongoDB\Client('mongodb://mongodb:27017/?replicaSet=rs0');
$crdb = new PDO('pgsql:host=cockroach1;port=26257;dbname=defaultdb;user=root');
$crdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$es = Elastic\Elasticsearch\ClientBuilder::create()->setHosts(['http://elasticsearch:9200'])->build();

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $ping = $mongo->getManager()->executeCommand('admin', new MongoDB\Driver\Command(['ping' => 1]))->toArray()[0];
    echo "MongoDB:       ok (ping=" . $ping->ok . ")\n";
    echo "CockroachDB:   " . $crdb->query("SELECT version()")->fetchColumn() . "\n";
    echo "Elasticsearch: " . $es->info()['version']['number'] . "\n";
}
