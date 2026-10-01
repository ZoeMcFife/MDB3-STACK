#!/usr/bin/env bash
# Initialise the single-member MongoDB replica set rs0 (idempotent).
# A replica set is needed for multi-document transactions (Transactions and Consistency).
set -e
until mongosh --host mongodb --quiet --eval "db.adminCommand('ping')" > /dev/null 2>&1; do
  echo "waiting for mongodb ..."; sleep 2
done
STATUS=$(mongosh --host mongodb --quiet --eval "try { rs.status(); print('INITIALIZED'); } catch(e) { print('NOT_INITIALIZED'); }" 2>&1 || echo NOT_INITIALIZED)
if [[ "$STATUS" == *NOT_INITIALIZED* ]]; then
  echo "initialising replica set rs0"
  mongosh --host mongodb --quiet --eval "rs.initiate({_id: 'rs0', members: [{_id: 0, host: 'mongodb:27017'}]})"
  sleep 5
fi
mongosh --host mongodb --quiet --eval "print('replica set: ' + rs.status().set + ', primary: ' + rs.isMaster().ismaster)"
