#!/usr/bin/env bash
# Initialise the three-node CockroachDB cluster once (idempotent).
set -e
for i in 1 2 3 4 5 6 7 8 9 10 11 12; do
  if cockroach init --insecure --host=cockroach1:26257 2>/tmp/init.err; then
    echo "cluster initialised"; break
  fi
  if grep -q "already been initialized" /tmp/init.err; then
    echo "cluster already initialised"; break
  fi
  echo "waiting for cockroach1 ..."; sleep 3
done
cockroach node status --insecure --host=cockroach1:26257 || true
