#!/usr/bin/env bash
set -euo pipefail
mode=${1:-dry-run}
[[ "$mode" == dry-run || "$mode" == publish ]] || exit 2
expected=/home/u343461047/domains/jwusinvest.com.br/public_html/cms
[[ "${HOSTINGER_PATH:-}" == "$expected" ]] || { echo 'Unexpected CMS destination' >&2; exit 1; }
[[ "${HOSTINGER_HOST:-}" =~ ^[a-zA-Z0-9.-]+$ && "${HOSTINGER_USER:-}" =~ ^[a-zA-Z0-9_-]+$ && "${HOSTINGER_PORT:-}" =~ ^[0-9]+$ ]] || exit 2
key=${DEPLOY_KEY_FILE:?}
known=${DEPLOY_KNOWN_HOSTS_FILE:?}
ssh_args=(-F /dev/null -i "$key" -p "$HOSTINGER_PORT" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$known")
remote="$HOSTINGER_USER@$HOSTINGER_HOST"
ssh "${ssh_args[@]}" "$remote" "test \"\$(readlink -f '$expected')\" = '$expected' && test -f '$expected/wp-includes/version.php'"
transport="ssh -F /dev/null -i $key -p $HOSTINGER_PORT -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=$known"
flags=(-az --checksum --no-times --delay-updates --delete --itemize-changes --exclude=tests/ --exclude=fixtures/ --exclude=tools/)
[[ "$mode" != dry-run ]] || flags+=(--dry-run)
for component in plugins/jw-core themes/jw-headless; do
  [[ -d "wp-content/$component" && ! -L "wp-content/$component" ]] || exit 1
  # Destinations must be provisioned by bootstrap, never inferred from a variable.
  ssh "${ssh_args[@]}" "$remote" "test \"\$(readlink -f '$expected/wp-content/$component')\" = '$expected/wp-content/$component' && test -d '$expected/wp-content/$component'"
  rsync "${flags[@]}" -e "$transport" "wp-content/$component/" "$remote:$expected/wp-content/$component/"
done
if [[ "$mode" == publish ]]; then
  python3 - <<'PY' | ssh "${ssh_args[@]}" "$remote" "cd '$expected' && sha256sum --check --quiet -"
from pathlib import Path
from hashlib import sha256
for root in ('wp-content/plugins/jw-core', 'wp-content/themes/jw-headless'):
    for item in sorted(Path(root).rglob('*')):
        if item.is_file() and not set(item.parts).intersection({'tests', 'fixtures', 'tools'}):
            if item.is_symlink(): raise SystemExit('Symlink in product')
            print(sha256(item.read_bytes()).hexdigest()+'  '+item.as_posix())
PY
  echo 'CMS product files transferred and checksums verified. Database and uploads preserved.'
fi
