-- NewSQL I (23 Nov): your own tables, and the CSVs of the starter imported the CockroachDB way.
-- Before:  docker compose cp app/sessions/s4 cockroach1:/tmp/s4
--          docker compose exec cockroach1 cockroach userfile upload --insecure -r /tmp/s4 /
-- Run in:  docker compose exec cockroach1 cockroach sql --insecure

-- the database and the tables: types, keys, references, checks, as in Relational Databases
DROP DATABASE IF EXISTS catalogue CASCADE;  -- for a second run
CREATE DATABASE catalogue;
USE catalogue;
CREATE TABLE items (
    id INT PRIMARY KEY, name STRING NOT NULL, type STRING NOT NULL,
    buy DECIMAL(10,1), sell DECIMAL(10,1), source STRING);
CREATE TABLE villagers (
    id INT PRIMARY KEY, name STRING NOT NULL UNIQUE, species STRING NOT NULL,
    personality STRING NOT NULL,
    birthday_month INT NOT NULL CHECK (birthday_month BETWEEN 1 AND 12),
    birthday_day INT NOT NULL);
CREATE TABLE villager_furniture (
    villager_id INT NOT NULL REFERENCES villagers(id), position INT NOT NULL,
    item_id INT NOT NULL REFERENCES items(id),
    PRIMARY KEY (villager_id, position));

-- IMPORT INTO: a bulk load that runs as a job, spread over the nodes that hold the table
IMPORT INTO items CSV DATA ('userfile:///s4/items.csv') WITH skip = '1', nullif = '';
IMPORT INTO villagers CSV DATA ('userfile:///s4/villagers.csv') WITH skip = '1';
IMPORT INTO villager_furniture CSV DATA ('userfile:///s4/villager_furniture.csv') WITH skip = '1';

-- the jobs, and what IMPORT did not check
SELECT split_part(description, ' ', 3) AS target, status FROM [SHOW JOBS] WHERE job_type = 'IMPORT' ORDER BY created DESC LIMIT 3;
SELECT constraint_name, validated FROM [SHOW CONSTRAINTS FROM villager_furniture];

-- checking afterwards, and the statistics the planner needs
ALTER TABLE villager_furniture VALIDATE CONSTRAINT villager_furniture_villager_id_fkey;
ALTER TABLE villager_furniture VALIDATE CONSTRAINT villager_furniture_item_id_fkey;
ALTER TABLE villagers VALIDATE CONSTRAINT check_birthday_month;
ANALYZE items;
ANALYZE villagers;
ANALYZE villager_furniture;
