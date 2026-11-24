#!/bin/sh
# Builds the two installable ZIP files of FredKI The Agent from this repository.
#
#   marketplace  the plugin as listed in Moodle Marketplace (no link to book packages)
#   direct       the plugin as downloaded from fredki.com / GitHub: the same code plus the card
#                "Book more courses and messages" on the activation page (editions/direct/direct-edition.patch)
#
# Usage: tools/build-editions.sh [output-dir]      (run inside a git checkout)
set -eu
root=$(git rev-parse --show-toplevel)
out=${1:-$root/build}
mkdir -p "$out"
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
for edition in marketplace direct; do
    dir=$tmp/$edition/fredkitheagent
    mkdir -p "$dir"
    git -C "$root" archive HEAD | tar -x -C "$dir"
    rm -rf "$dir/.github" "$dir/dist" "$dir/editions" "$dir/tools"
    if [ "$edition" = direct ]; then
        patch -s -d "$dir" -p1 < "$root/editions/direct/direct-edition.patch"
    fi
    version=$(sed -n "s/.*\$plugin->release = '\(.*\)';.*/\1/p" "$dir/version.php")
    (cd "$tmp/$edition" && rm -f "$out/block_fredkitheagent_${version}_$edition.zip" \
        && zip -qr "$out/block_fredkitheagent_${version}_$edition.zip" fredkitheagent)
    echo "$out/block_fredkitheagent_${version}_$edition.zip"
done
