-- NewSQL I (23 Nov): SQL as you know it, on the tables of import.sql. Segment s07 is optional.
-- Run in:  docker compose exec cockroach1 cockroach sql --insecure -d catalogue

SHOW TABLES;
SELECT (SELECT count(*) FROM items) AS items, (SELECT count(*) FROM villagers) AS villagers,
       (SELECT count(*) FROM villager_furniture) AS ownerships;

-- the $unwind + $group + $lookup report of Aggregation II, as the JOIN it always was
SELECT i.name, count(*) AS owners
FROM villager_furniture vf JOIN items i ON i.id = vf.item_id
GROUP BY i.id, i.name
ORDER BY owners DESC, i.id
LIMIT 5;

-- the $group of Aggregation I
SELECT type, count(*) AS n, round(avg(sell)) AS avg_sell
FROM items
GROUP BY type
ORDER BY n DESC
LIMIT 5;

-- HAVING, and a subquery: villagers who own something worth more than 10,000 Bells
SELECT type, round(avg(sell)) AS avg_sell FROM items GROUP BY type HAVING avg(sell) > 5000 ORDER BY 2 DESC;
SELECT name FROM villagers
WHERE id IN (SELECT villager_id FROM villager_furniture vf JOIN items i ON i.id = vf.item_id WHERE i.sell > 10000)
ORDER BY name LIMIT 5;

-- the constraint the validator had to imitate
ALTER TABLE items ADD CONSTRAINT sell_not_negative CHECK (sell >= 0);
UPDATE items SET sell = -1 WHERE id = 717;

-- the DDL difference that bites: SERIAL is not a counter here
CREATE TABLE visits (
    id SERIAL PRIMARY KEY,
    villager_id INT NOT NULL REFERENCES villagers(id),
    visitor STRING NOT NULL,
    at TIMESTAMPTZ NOT NULL DEFAULT now());
INSERT INTO visits (villager_id, visitor) VALUES (1, 'oliver'), (1, 'mia') RETURNING id;
SHOW CREATE TABLE visits;

-- a transaction: the transfer from the Transactions and Consistency slides, in the language where it is the default
BEGIN;
DELETE FROM villager_furniture WHERE villager_id = (SELECT id FROM villagers WHERE name = 'Admiral') AND item_id = 717;
INSERT INTO villager_furniture (villager_id, position, item_id)
  SELECT v.id, (SELECT coalesce(max(position), -1) + 1 FROM villager_furniture WHERE villager_id = v.id), 717
  FROM villagers v WHERE v.name = 'Boone';
COMMIT;
SELECT v.name FROM villager_furniture vf JOIN villagers v ON v.id = vf.villager_id WHERE vf.item_id = 717 ORDER BY v.name;
