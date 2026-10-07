#!/usr/bin/env bash

# Tests for what `opensim setup` checks before it asks anything: the files of the grids belong to the system user, the setup
# runs as the person who started it and needs sudo for them.
#
# Run with: tests/lib/bashunit tests/Unit (or vendor/bin/pest)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Only the function under test: the script does more when it is sourced
eval "$(sed -n '/^setupNeedsSystemUser()/,/^}/p' "$ROOT/bin/opensim")"

# The script ends with its own helper, the test only says what it would have said
end() { echo "ended $1: $2"; return "$1"; }

function test_the_setup_goes_on_when_sudo_gives_it_the_system_user() {
	SystemUser=opensim-test-system-user
	sudo() { return 0; }

	assert_equals "" "$(setupNeedsSystemUser)"
}

function test_the_setup_stops_at_once_when_sudo_does_not() {
	# root needs no sudo, there is nothing to check
	[ "$(id -u)" != 0 ] || return 0
	SystemUser=opensim-test-system-user
	sudo() { return 1; }

	assert_contains "ended 1: the setup needs sudo" "$(setupNeedsSystemUser)"
	assert_contains "opensim-test-system-user" "$(setupNeedsSystemUser)"
}

function test_the_setup_needs_nothing_when_the_install_has_no_system_user() {
	SystemUser=
	sudo() { return 1; }

	assert_equals "" "$(setupNeedsSystemUser)"
}

function test_the_setup_needs_nothing_for_the_system_user_itself() {
	SystemUser=$(id -un)
	sudo() { return 1; }

	assert_equals "" "$(setupNeedsSystemUser)"
}
