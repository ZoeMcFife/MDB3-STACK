-- NewSQL I (23 Nov): two clients, one row. Two terminals, each with its own shell:
--   docker compose exec cockroach1 cockroach sql --insecure -d catalogue
-- Alice types in terminal 1, Bob in terminal 2, in the order of the segments.

-- either terminal: the price back to where it started
UPDATE items SET sell = 825 WHERE id = 717;

-- Alice
BEGIN;
SELECT sell FROM items WHERE id = 717;

-- Bob
BEGIN;
SELECT sell FROM items WHERE id = 717;

-- Alice: 825 + 100
UPDATE items SET sell = 925 WHERE id = 717;
COMMIT;

-- Bob: 825 + 100, from what he read
UPDATE items SET sell = 925 WHERE id = 717;
ROLLBACK;

-- Bob, again: the whole transaction, from BEGIN
BEGIN;
SELECT sell FROM items WHERE id = 717;
UPDATE items SET sell = 1025 WHERE id = 717;
COMMIT;

-- both, in turn: BEGIN in both, the UPDATE in both, COMMIT in both
BEGIN;
UPDATE items SET sell = sell + 100 WHERE id = 717;
COMMIT;
