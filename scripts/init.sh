#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ -e .env ]]; then echo ".env already exists; leaving it unchanged." >&2; exit 1; fi
umask 077
cp .env.example .env
key=$(openssl rand -base64 32)
db_password=$(openssl rand -hex 24)
demo_password=$(openssl rand -hex 16)
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$key|; s|^DB_PASSWORD=.*|DB_PASSWORD=$db_password|; s|^DEMO_PASSWORD=.*|DEMO_PASSWORD=$demo_password|" .env
echo "Created .env with random secrets. Read DEMO_PASSWORD locally to sign in after seeding."
