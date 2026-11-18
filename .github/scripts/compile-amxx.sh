#!/usr/bin/env bash
# Compiles every amxmodx/scripting/*.sma with the official AMX Mod X compiler.
# Usage: compile-amxx.sh <output dir>
#
# amxxpc is a 32-bit Linux binary: the runner needs libc6:i386 and lib32stdc++6.
# A warning fails the build like an error, as for the SourcePawn plugins.
#
# Pinned build, checked 2026-11-18: the newest 1.10 build on amxxdrop (git5488);
# its base package holds amxxpc and every include the plugins use (cstrike, csx,
# dodx, dodfun, ns, tfcx...). To update, change both lines below.
set -euo pipefail

AMXX_URL="https://www.amxmodx.org/amxxdrop/1.10/amxmodx-1.10.0-git5488-base-linux.tar.gz"
AMXX_SHA256="b537f5c5972294afcd5b022e5ffc44627738dfb029ad1b2eab5dc482c8812391"

out="${1:?Usage: compile-amxx.sh <output dir>}"
mkdir -p "$out"
out=$(cd "$out" && pwd)
src=$(cd "$(dirname "$0")/../../amxmodx/scripting" && pwd)

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

curl -fsSL -o "$work/amxx.tar.gz" "$AMXX_URL"
echo "$AMXX_SHA256  $work/amxx.tar.gz" | sha256sum -c -
tar -xzf "$work/amxx.tar.gz" -C "$work" addons/amxmodx/scripting
compiler="$work/addons/amxmodx/scripting"

status=0
for plugin in "$src"/*.sma; do
  name=$(basename "$plugin" .sma)
  echo "::group::${name}.sma"
  # amxxpc looks for amxxpc32.so next to itself, so it runs from its folder. The
  # source is copied there too, so the debug info holds "<name>.sma" and not a path
  # of this machine: the same source gives the same .amxx everywhere.
  cp "$plugin" "$compiler/$name.sma"
  if ! log=$(cd "$compiler" && ./amxxpc "$name.sma" -iinclude -o"$out/$name.amxx" 2>&1); then
    status=1
  fi
  echo "$log"
  if grep -qiE '(warning|error) [0-9]+:' <<<"$log" || [ ! -s "$out/$name.amxx" ]; then
    echo "::error file=amxmodx/scripting/${name}.sma::${name}.sma has compile errors or warnings"
    status=1
  fi
  echo "::endgroup::"
done
exit "$status"
