#!/usr/bin/env bash

# Tests for the shell scripts of the repository: they parse, and bash scripts
# use the env shebang.
#
# Run with: tests/lib/bashunit tests/Unit (or vendor/bin/pest)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# The shell scripts of the repository, one per line: "interpreter path"
scripts() {
	local file line
	for file in "$ROOT"/bin/* "$ROOT"/libexec/* "$ROOT"/install/* "$ROOT"/share/cron.hourly/* \
		"$ROOT"/packaging/* "$ROOT"/packaging/container/* "$ROOT"/tests/Packaging/*; do
		[ -f "$file" ] && [ ! -L "$file" ] || continue
		line=$(head -n 1 "$file")
		case "$line" in
			'#!'*bash*) echo "bash $file" ;;
			'#!/bin/sh'* | '#!/usr/bin/env sh'*) echo "sh $file" ;;
		esac
	done
}

function test_scripts_parse() {
	local failures="" interpreter file
	while read -r interpreter file; do
		"$interpreter" -n "$file" 2>/dev/null || failures="$failures ${file#"$ROOT"/}"
	done < <(scripts)

	assert_equals "" "$failures"
}

function test_bash_scripts_use_env_bash() {
	local failures="" interpreter file
	while read -r interpreter file; do
		[ "$interpreter" = bash ] || continue
		[ "$(head -n 1 "$file")" = '#!/usr/bin/env bash' ] || failures="$failures ${file#"$ROOT"/}"
	done < <(scripts)

	assert_equals "" "$failures"
}
