-- NewSQL I (23 Nov): behind the scenes. Keys, ranges, joins and an index, on one node.
-- Run in:  docker compose exec cockroach1 cockroach sql --insecure -d catalogue

-- the primary key is the sort order of the table, and the table is cut into ranges along it
SELECT start_key, end_key, range_id FROM [SHOW RANGES FROM INDEX villager_furniture@villager_furniture_pkey];
ALTER TABLE villager_furniture SPLIT AT VALUES (200);
SELECT start_key, end_key, range_id FROM [SHOW RANGES FROM INDEX villager_furniture@villager_furniture_pkey];

-- which range holds a row: the key decides
SELECT range_id FROM [SHOW RANGE FROM TABLE villager_furniture FOR ROW (1, 0)];
SELECT range_id FROM [SHOW RANGE FROM TABLE villager_furniture FOR ROW (250, 0)];

-- a join for one villager: look up each key in the other table
EXPLAIN SELECT i.name, i.sell
FROM villagers v
JOIN villager_furniture vf ON vf.villager_id = v.id
JOIN items i ON i.id = vf.item_id
WHERE v.name = 'Admiral';

-- a join over everything: build a hash table from one side, stream the other through it
EXPLAIN SELECT i.name, count(*) AS owners
FROM villager_furniture vf JOIN items i ON i.id = vf.item_id
GROUP BY i.id, i.name ORDER BY owners DESC LIMIT 5;

-- a filter on columns that are not the key: EXPLAIN recommends an index
EXPLAIN SELECT name, sell FROM items WHERE type = 'houseware' AND sell > 10000;

-- the index it recommends, and the plan afterwards
CREATE INDEX ON items (type, sell) STORING (name);
EXPLAIN SELECT name, sell FROM items WHERE type = 'houseware' AND sell > 10000;
SELECT name, sell FROM items WHERE type = 'houseware' AND sell > 10000 ORDER BY sell DESC LIMIT 5;
