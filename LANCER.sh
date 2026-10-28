#!/usr/bin/env bash
set -e
cd -- "$(dirname -- "$0")"
if ! command -v node >/dev/null 2>&1; then
  echo 'Node.js 22.12 ou plus récent est nécessaire.'
  exit 1
fi
if [ ! -d node_modules ]; then npm ci; fi
printf '\nLigue 1 Live : ouvrir http://127.0.0.1:5173\nArrêt : Ctrl+C\n\n'
npm run dev
