#!/usr/bin/env bash

# Tests for functions of libexec/os-helpers that work on files.
#
# Run with: tests/lib/bashunit tests/Unit (or vendor/bin/pest)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Only the function under test: the library does more when it is sourced
eval "$(sed -n '/^cleanupIni()/,/^}/p' "$ROOT/libexec/os-helpers")"

function set_up() {
	WORK=$(mktemp -d "${TMPDIR:-/tmp}/os-helpers-test.XXXXXX")
}

function tear_down() {
	rm -rf "$WORK"
}

function test_cleanupini_strips_the_indentation() {
	printf '[Const]\n    BaseURL = "http://localhost"\n\t;; comment\n' >"$WORK/in.ini"

	assert_equals '[Const]
BaseURL = "http://localhost"
;; comment' "$(cleanupIni "$WORK/in.ini")"
}

function test_cleanupini_strips_windows_line_endings() {
	printf '[Const]\r\n    BaseURL = "http://localhost"\r\n' >"$WORK/in.ini"

	cleanupIni "$WORK/in.ini" >"$WORK/out.ini"

	assert_equals '0' "$(grep -c "$(printf '\r')" "$WORK/out.ini")"
	assert_equals 'BaseURL = "http://localhost"' "$(sed -n 2p "$WORK/out.ini")"
}

function test_cleanupini_gives_nothing_for_a_missing_file() {
	assert_equals '' "$(cleanupIni "$WORK/missing.ini")"
}
