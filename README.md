# Moderne Datenbanken (MDB3) WS 2026/27 – course stack

One `docker compose` file for the whole semester: MongoDB (replica set), CockroachDB, Elasticsearch, Kibana and PHP 8.4 with the `mongodb` and `pdo_pgsql` extensions.

## First start (at home, before session 1)

```bash
cd docker
docker compose up -d --build          # first time: builds the PHP image (a few minutes)
docker compose exec php composer install
docker compose exec php php stack.php
```

The last command must print three `ok` lines (MongoDB, CockroachDB, Elasticsearch). Session 1 begins with this check.

## What runs where

| Service | Port | Use |
|---|---|---|
| MongoDB 8.2 (replica set `rs0`; 8.0 and 8.3 refuse to start on Linux kernels 6.19+, see SERVER-121912) | 27017 | Compass: `mongodb://localhost:27017/?directConnection=true` (if 27017 is taken on your machine: `MONGO_PORT=27018 docker compose up -d`) |
| CockroachDB 26.2, single node | 26257, Console 8085 | `docker compose exec cockroach1 cockroach sql --insecure` |
| Elasticsearch 9.1 | 9200 | `curl localhost:9200` |
| Kibana 9.1 | 5601 | Dev Tools console |
| PHP 8.4 + Apache | 8084 | serves `./app`; `docker compose exec php php file.php` |

Inside the PHP container the hosts are `mongodb`, `cockroach1`, `elasticsearch`. `app/stack.php` opens the three connections for the setup check and the importer. Students write their own `app/connect.php` during the sessions (MongoDB in session 2, CockroachDB in session 4, Elasticsearch in session 6); every session's code starts from it.

## The three-node CockroachDB cluster (NewSQL II, A03 task 4)

```bash
docker compose down                                  # stop the single node
docker compose --profile cluster up -d               # three nodes + init
docker compose exec cockroach-node1 cockroach sql --insecure
docker stop cockroach-node2                          # kill a node; queries continue
docker stop cockroach-node3                          # kill a second one; the cluster stalls
docker start cockroach-node3
docker compose --profile cluster down -v             # back to a clean state
```

Nodes 2 and 3 are reachable on 26258/26259 (SQL) and 8086/8087 (Console). A cluster runs without a licence key for seven days after its creation and is only throttled afterwards; `down -v` gives you a fresh cluster. The single node never needs a key.

## Memory

MongoDB ≈ 0.5 GB, CockroachDB single node ≈ 0.5 GB (three nodes ≈ 1.5 GB), Elasticsearch 0.5 GB heap + overhead ≈ 1 GB, Kibana ≈ 1 GB. Give Docker at least 6 GB. If Kibana is too much for your machine, stop it (`docker compose stop kibana`) and use `curl` or PHP for Elasticsearch.

## Reset

```bash
docker compose --profile cluster down -v   # removes all data volumes
```
