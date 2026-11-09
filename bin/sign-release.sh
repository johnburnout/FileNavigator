#!/usr/bin/env bash
# Signe l'archive d'une release GitHub et publie la signature (filenavigator.zip.sig).
# À lancer sur la machine qui détient la clé privée, une fois le workflow « Release » terminé.
#
# Usage : bin/sign-release.sh v1.3.5
# Clé privée : $WPFN_SIGNING_KEY, sinon ~/.config/filenavigator/release-signing.key
set -euo pipefail

TAG="${1:?Usage : bin/sign-release.sh vX.Y.Z}"
KEY="${WPFN_SIGNING_KEY:-$HOME/.config/filenavigator/release-signing.key}"
ASSET=filenavigator.zip
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIR="$(mktemp -d)"
trap 'rm -rf "$DIR"' EXIT

# Attendre la fin du workflow de release s'il est encore en cours
RUN_ID="$(gh run list --workflow=release.yml --branch "$TAG" --limit 1 --json databaseId --jq '.[0].databaseId' 2>/dev/null || true)"
if [ -n "$RUN_ID" ]; then
  echo "Attente du workflow Release ($RUN_ID)…"
  gh run watch "$RUN_ID" --exit-status > /dev/null
fi

gh release download "$TAG" --pattern "$ASSET" --dir "$DIR"
echo "SHA-256 : $(shasum -a 256 "$DIR/$ASSET" | cut -d' ' -f1)"
php "$ROOT/bin/sign-file.php" "$KEY" "$DIR/$ASSET" > "$DIR/$ASSET.sig"
gh release upload "$TAG" "$DIR/$ASSET.sig" --clobber
echo "Signature publiée pour $TAG."
