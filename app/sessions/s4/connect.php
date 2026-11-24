<?php
// connect.php: the connections of this course, written in the sessions.
// MongoDB came in CRUD and Queries, CockroachDB comes in NewSQL I (23 Nov), Elasticsearch in Search I (24 Nov).
require __DIR__ . '/vendor/autoload.php';

$mongo = new MongoDB\Client('mongodb://mongodb:27017/?replicaSet=rs0');

// CockroachDB speaks the Postgres wire protocol, so this is the PDO from Relational Databases with the
// pgsql driver. Port 26257 is CockroachDB's; the user root and no password are the stack's insecure mode.
$crdb = new PDO('pgsql:host=cockroach1;port=26257;dbname=defaultdb;user=root');
$crdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
