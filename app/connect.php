<?php

global $mongo;

require __DIR__ . '/vendor/autoload.php';

$mongo = new MongoDB\Client("mongodb://mongodb:27017/?replicaSet=rs0");

$items = $mongo->catalogue->items;

