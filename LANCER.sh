#!/usr/bin/env bash
set -e
cd -- "$(dirname -- "$0")"
if ! command -v node >/dev/null 2>&1; then
  echo 'Node.js 22.12 ou plus récent est nécessaire.'
  exit 1
fi
if ! command -v php >/dev/null 2>&1 || ! command -v composer >/dev/null 2>&1; then
  echo 'PHP et Composer sont nécessaires pour le backend Laravel.'
  exit 1
fi
if [ ! -d vendor ]; then composer install --no-interaction; fi
if [ ! -d node_modules ]; then npm ci; fi
printf '\nLigue 1 Live : ouvrir http://127.0.0.1:5173\nArrêt : Ctrl+C\n\n'
npm run dev
