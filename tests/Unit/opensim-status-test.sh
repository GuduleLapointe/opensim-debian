#!/usr/bin/env bash

# Tests for the summary line of `opensim status`.
#
# Run with: tests/lib/bashunit tests/Unit (or vendor/bin/pest)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Only the function under test: the script does more when it is sourced
eval "$(sed -n '/^statusLine()/,/^}/p' "$ROOT/bin/opensim")"

function test_the_instances_down_are_counted_when_there_are_some() {
	assert_equals "2 up, 1 down - cpu 1.0%" "$(statusLine 2 1 "cpu 1.0%" | cat)"
}

function test_the_instances_down_are_not_counted_when_there_are_none() {
	assert_equals "3 up - cpu 1.0%" "$(statusLine 3 0 "cpu 1.0%" | cat)"
}
