#!/usr/bin/env bash
# Checks that the database updater scripts follow the rules described in CLAUDE.md:
#   - numbered updaters in web/updater/ are contiguous,
#   - SET @DBVERSION in sql/install.sql equals the highest updater number,
#   - new updaters have the IN_UPDATER guard and set $dbversion to their own number,
#   - existing updaters are never modified, renamed or deleted (only when BASE_REF is set).
#
# Usage: check-updaters.sh [BASE_REF]
#   BASE_REF: git ref to compare against (e.g. origin/master). Without it, the
#             "never edit an existing updater" check is skipped and only the
#             highest updater is checked for the guard and $dbversion.

set -euo pipefail

UPDATER_DIR="web/updater"
INSTALL_SQL="sql/install.sql"
BASE_REF="${1:-}"
status=0

error() {
    local file="$1"; shift
    echo "::error file=${file}::$*"
    status=1
}

# Numbered updaters, sorted numerically.
mapfile -t numbers < <(git ls-files "${UPDATER_DIR}/" \
    | sed -nE "s#^${UPDATER_DIR}/([0-9]+)\.php\$#\1#p" | sort -n)

if [ "${#numbers[@]}" -eq 0 ]; then
    error "$UPDATER_DIR" "No numbered updater scripts found."
    exit 1
fi

highest="${numbers[-1]}"
echo "Updaters: ${numbers[0]}..${highest} (${#numbers[@]} files)"

prev=""
for n in "${numbers[@]}"; do
    if [ -n "$prev" ] && [ "$n" -ne $((prev + 1)) ]; then
        error "${UPDATER_DIR}/${n}.php" "Updater numbering has a gap: ${prev}.php is followed by ${n}.php."
    fi
    prev="$n"
done

# install.sql must start fresh installs at the highest updater version.
install_version=$(sed -nE 's/^SET @DBVERSION *= *"?([0-9]+)"?.*/\1/p' "$INSTALL_SQL" | head -n1)
if [ -z "$install_version" ]; then
    error "$INSTALL_SQL" "Could not find 'SET @DBVERSION' in ${INSTALL_SQL}."
elif [ "$install_version" -ne "$highest" ]; then
    error "$INSTALL_SQL" "SET @DBVERSION is ${install_version} but the highest updater is ${highest}.php. Keep them equal."
else
    echo "install.sql @DBVERSION = ${install_version} (matches highest updater)"
fi

check_updater_content() {
    local n="$1" file="${UPDATER_DIR}/${1}.php"
    if ! grep -q "IN_UPDATER" "$file"; then
        error "$file" "Updater ${n}.php does not check IN_UPDATER."
    fi
    if ! tr -d '\r' < "$file" | grep -qE "^[[:space:]]*\\\$dbversion[[:space:]]*=[[:space:]]*${n}[[:space:]]*;"; then
        error "$file" "Updater ${n}.php must set \$dbversion = ${n};"
    fi
}

if [ -n "$BASE_REF" ]; then
    # Existing updaters must never change: databases past that version will not run them again.
    while IFS=$'\t' read -r change path rest; do
        case "$change" in
            A) ;;
            *) error "${rest:-$path}" "Existing updater ${path} was changed (${change}). Never edit an existing updater; add a new <N>.php instead." ;;
        esac
    done < <(git diff --name-status "${BASE_REF}...HEAD" -- "${UPDATER_DIR}/[0-9]*.php")

    mapfile -t added < <(git diff --name-only --diff-filter=A "${BASE_REF}...HEAD" -- "${UPDATER_DIR}/[0-9]*.php" \
        | sed -nE "s#^${UPDATER_DIR}/([0-9]+)\.php\$#\1#p")
    for n in "${added[@]}"; do
        echo "New updater: ${n}.php"
        check_updater_content "$n"
    done
else
    check_updater_content "$highest"
fi

exit "$status"
