#!/usr/bin/env bash

# Tests for libexec/load-helpers: the scripts of the kit use the bash-helpers the kit has, not the
# first one of the PATH (an outdated copy of composer global, for instance).
#
# Run with: tests/lib/bashunit tests/Unit (or vendor/bin/pest)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

function set_up() {
	WORK=$(mktemp -d "${TMPDIR:-/tmp}/load-helpers-test.XXXXXX")
}

function tear_down() {
	rm -rf "$WORK"
}

# A bash-helpers that loads, and has nothing the scripts use
fake_helpers() {
	mkdir -p "$1"
	printf '#!/usr/bin/env bash\nOLD_HELPERS_LOADED=yes\n' >"$1/bash-helpers"
	chmod +x "$1/bash-helpers"
}

function test_the_helpers_of_the_kit_come_before_the_ones_of_the_path() {
	fake_helpers "$WORK/global"

	local loaded
	loaded=$(PATH="$WORK/global:$PATH" bash -c ". '$ROOT/libexec/load-helpers' && echo \"\$BASH_HELPERS \${OLD_HELPERS_LOADED:-}\"" 2>&1 | tail -1)

	assert_equals "$ROOT/vendor/magicoli/bash-tools/src/lib/bash-helpers " "$loaded"
}

function test_the_helpers_are_the_kits_whatever_the_name_of_the_script() {
	fake_helpers "$WORK/global"
	printf '#!/usr/bin/env bash\n. "%s/libexec/load-helpers" && declare -F debug >/dev/null && echo loaded\n' "$ROOT" >"$WORK/anyname"

	assert_equals "loaded" "$(cd "$WORK" && PATH="$WORK/global:$PATH" bash anyname 2>&1 | tail -1)"
}

function test_helpers_without_what_the_scripts_use_are_refused_with_a_clear_message() {
	mkdir -p "$WORK/kit/libexec"
	cp "$ROOT/libexec/load-helpers" "$WORK/kit/libexec/"
	fake_helpers "$WORK/kit/vendor/magicoli/bash-tools/bin"

	local out
	out=$(bash -c ". '$WORK/kit/libexec/load-helpers'" 2>&1)

	assert_contains "too old" "$out"
}
