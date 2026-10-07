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

function test_oscountdown_counts_down_to_the_shutdown_not_to_the_next_warning() {
    eval "$(sed -n '/^osCountdown()/,/^}/p' "$ROOT/libexec/os-helpers")"
    osStep() { echo "$1"; }
    osStepEnd() { :; }
    sleep() { :; }

    assert_equals '  Stopping x in 80s' "$(osCountdown 40 'Stopping x' 40)"
}

function test_oscountdown_stops_when_the_check_succeeds() {
    eval "$(sed -n '/^osCountdown()/,/^}/p' "$ROOT/libexec/os-helpers")"
    osStep() { :; }
    osStepEnd() { :; }
    sleep() { :; }
    nobody() { return 0; }

    osCountdown 40 'x' 0 nobody
    assert_equals '1' "$?"
}

function test_oscountdown_goes_to_the_end_when_the_check_fails() {
    eval "$(sed -n '/^osCountdown()/,/^}/p' "$ROOT/libexec/os-helpers")"
    osStep() { :; }
    osStepEnd() { :; }
    sleep() { :; }
    somebody() { return 1; }

    osCountdown 40 'x' 0 somebody
    assert_equals '0' "$?"
}

function test_oscountrealusers_ignores_npcs_and_children() {
	eval "$(sed -n '/^osCountRealUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
	OSIM_REST_INI=x
	osRest() {
		cat <<'OUT'
2026-10-03 10:00:00,000 INFO  [SCENE]: Removing root agent 11111111-2222-3333-4444-555555555999 from Sim1

Root agents in region Sim1: 3 (root 3, child 1)
Firstname        Lastname         Agent ID                              Type        Position
Ann              Lee              11111111-2222-3333-4444-555555555555  Root        <1, 2, 3>
Bob              NPC              11111111-2222-3333-4444-555555555556  NPC Root    <1, 2, 3>
Cy               Child            11111111-2222-3333-4444-555555555557  Child       <1, 2, 3>

Root agents in region Sim2: 1 (root 1, child 0)
Dee              Roe              11111111-2222-3333-4444-555555555558  Root        <1, 2, 3>
OUT
	}

	assert_equals '2' "$(osCountRealUsers)"
}

function test_oscountrealusers_is_zero_for_empty_regions() {
	eval "$(sed -n '/^osCountRealUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
	OSIM_REST_INI=x
	osRest() { printf '\nRoot agents in region Sim1: 0 (root 0, child 0)\n'; }

	assert_equals '0' "$(osCountRealUsers)"
}

function test_oscountrealusers_does_not_guess_without_a_list() {
    eval "$(sed -n '/^osCountRealUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
    OSIM_REST_INI=x
    osRest() { printf 'something else\n'; }

    assert_equals '' "$(osCountRealUsers)"
}

function test_oscountrealusers_from_a_screen_reads_the_rows_cut_at_80_columns() {
	eval "$(sed -n '/^osCountRealUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
	unset OSIM_REST_INI
	simName=sim
	osScreenExists() { return 0; }
	osConsole() { :; }
	osScreenOutput() { printf '\nRoot agents in region Sim1: 0 (root 0, child 2)\n'; }

	assert_equals '0' "$(osCountRealUsers)"

	# Only an NPC is there (the root count includes it), the type is in the 80 columns
	osScreenOutput() {
		printf '\nRoot agents in region Sim1: 1 (root 1, child 0)\nBob              NPC              11111111-2222-3333-4444-555555555556  NPC Roo\nt    <1, 2, 3>\n'
	}
	assert_equals '0' "$(osCountRealUsers)"

	# A real user whose type was cut off counts
	osScreenOutput() {
		printf '\nRoot agents in region Sim1: 2 (root 2, child 0)\nAnn              Lee              11111111-2222-3333-4444-555555555555\nBob              NPC              11111111-2222-3333-4444-555555555556  NPC Roo\n'
	}
	assert_equals '1' "$(osCountRealUsers)"

	# Root agents without the rows to tell who they are: no answer
	osScreenOutput() { printf '\nRoot agents in region Sim1: 1 (root 1, child 0)\nAnn Lee 1111-truncated\n'; }
	osCountRealUsers >/dev/null
	assert_equals '1' "$?"
}

function test_oscountrealusers_asks_the_console_to_see_all_the_regions() {
	eval "$(sed -n '/^osCountRealUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
	OSIM_REST_INI=x
	# The console is in the last region that came up: the lines sent say to change to root before listing the users
	osRest() {
		cat >"$WORK/asked"
		printf '\nRoot agents in region Sim1: 0 (root 0, child 0)\n'
	}
	osCountRealUsers >/dev/null
	assert_equals "change region root
show users" "$(cat "$WORK/asked")"

	unset OSIM_REST_INI
	simName=sim
	LOG=
	osScreenExists() { return 0; }
	osConsole() { LOG="$LOG|$2"; }
	osScreenOutput() { printf '\nRoot agents in region Sim1: 0 (root 0, child 0)\n'; }
	osCountRealUsers >/dev/null
	assert_equals "|change region root" "$LOG"
}

# Two regions, one with a user: the HTTP port of the simulator tells how many agents each has
fake_regions() {
	mkdir -p "$WORK/regions"
	printf '[Sim1]\nRegionUUID = 11111111-1111-4111-8111-111111111111\n' >"$WORK/regions/Sim1.ini"
	printf '[Sim2]\nRegionUUID = "22222222-2222-4222-8222-222222222222"\n' >"$WORK/regions/Sim2.ini"
	eval "$(sed -n '/^osExpandConst()/,/^}/p' "$ROOT/libexec/os-helpers")"
	eval "$(sed -n '/^osCountUsersHttp()/,/^}/p' "$ROOT/libexec/os-helpers")"
	crudget() {
		case "$3" in
		http_listener_port) echo 9110 ;;
		regionload_regionsdir) echo "$WORK/regions" ;;
		esac
	}
}

function test_oscountusershttp_adds_the_agents_of_every_region() {
	fake_regions
	curl() {
		case "$*" in
		*monitorstats/11111111-1111-4111-8111-111111111111) echo '<data><AgentCountMonitor>1</AgentCountMonitor></data>' ;;
		*monitorstats/22222222-2222-4222-8222-222222222222) echo '<data><AgentCountMonitor>2</AgentCountMonitor></data>' ;;
		esac
	}

	assert_equals '3' "$(osCountUsersHttp x.ini)"
}

function test_oscountusershttp_fails_when_a_region_does_not_answer() {
	fake_regions
	curl() { case "$*" in *11111111*) echo '<data><AgentCountMonitor>0</AgentCountMonitor></data>' ;; *) return 22 ;; esac; }

	osCountUsersHttp x.ini >/dev/null
	assert_equals '1' "$?"
}

function test_oscountusershttp_fails_for_a_simulator_without_region_files() {
	fake_regions
	rm -f "$WORK"/regions/*.ini
	curl() { echo '<data><AgentCountMonitor>0</AgentCountMonitor></data>'; }

	osCountUsersHttp x.ini >/dev/null
	assert_equals '1' "$?"
}

function test_oscountusers_takes_zero_from_the_http_port_without_asking_the_console() {
	eval "$(sed -n '/^osCountUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
	OS_USERS_INI=x.ini
	osCountUsersHttp() { echo 0; }
	osCountRealUsers() { echo "the console was asked" >&2; echo 5; }

	assert_equals '0' "$(osCountUsers 2>&1)"
}

function test_oscountusers_asks_the_console_who_the_agents_are() {
	eval "$(sed -n '/^osCountUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
	OS_USERS_INI=x.ini
	# An NPC is an agent for the simulator, not a user: the console says
	osCountUsersHttp() { echo 1; }
	osCountRealUsers() { echo 0; }

	assert_equals '0' "$(osCountUsers)"
}

function test_oscountusers_counts_the_agents_when_the_console_does_not_answer() {
	eval "$(sed -n '/^osCountUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
	OS_USERS_INI=x.ini
	osCountUsersHttp() { echo 2; }
	osCountRealUsers() { return 1; }

	assert_equals '2' "$(osCountUsers)"
}

function test_oscountusers_fails_when_nothing_answers() {
	eval "$(sed -n '/^osCountUsers()/,/^}/p' "$ROOT/libexec/os-helpers")"
	OS_USERS_INI=x.ini
	osCountUsersHttp() { return 1; }
	osCountRealUsers() { return 1; }

	osCountUsers >/dev/null
	assert_equals '1' "$?"
}

function test_a_silence_is_not_a_user_to_wait_for() {
	eval "$(sed -n '/^osNobodyToWaitFor()/,/^}/p' "$ROOT/libexec/os-helpers")"

	osCountUsers() { return 1; }
	osNobodyToWaitFor
	assert_equals '0' "$?"
	assert_equals '1' "$OS_USERS_UNKNOWN"

	osCountUsers() { echo 0; }
	osNobodyToWaitFor
	assert_equals '0' "$?"
	assert_equals '' "$OS_USERS_UNKNOWN"

	osCountUsers() { echo 2; }
	osNobodyToWaitFor
	assert_equals '1' "$?"
	assert_equals '' "$OS_USERS_UNKNOWN"
}

# A process that is not a child of the test, so that it is gone once killed and not a zombie. Args: what to run first
detached() { bash -c "$1; sleep 300 >/dev/null 2>&1 & echo \$!"; }

# What osForceStop needs: waiting for a process to go, and the signals it sends noted in $WORK/signals
fake_waitexit() {
	osWaitExit() {
		local t=0
		while builtin kill -0 "$2" 2>/dev/null; do
			[ $t -lt $(($3 * 10)) ] || return 1
			sleep 0.1
			t=$((t + 1))
		done
	}
	kill() {
		[ "$1" = -0 ] || echo "$1" >>"$WORK/signals"
		builtin kill "$@"
	}
}

function test_osforcestop_kills_what_ignores_the_term_signal() {
	eval "$(sed -n '/^osForceStop()/,/^}/p' "$ROOT/libexec/os-helpers")"
	fake_waitexit
	pid=$(detached 'trap "" TERM')

	osForceStop test "$pid" 1
	assert_equals '0' "$?"
	assert_equals "-TERM -KILL" "$(tr '\n' ' ' <"$WORK/signals" | sed 's/ $//')"
	assert_equals "gone" "$(builtin kill -0 "$pid" 2>/dev/null && echo alive || echo gone)"
}

function test_osforcestop_stops_at_term_when_that_is_enough() {
	eval "$(sed -n '/^osForceStop()/,/^}/p' "$ROOT/libexec/os-helpers")"
	fake_waitexit
	pid=$(detached ':')

	osForceStop test "$pid" 5
	assert_equals '0' "$?"
	assert_equals "-TERM" "$(tr '\n' ' ' <"$WORK/signals" | sed 's/ $//')"
	assert_equals "gone" "$(builtin kill -0 "$pid" 2>/dev/null && echo alive || echo gone)"
}

function test_a_simulator_needs_an_enabled_region_to_start() {
	eval "$(sed -n '/^osExpandConst()/,/^}/p' "$ROOT/libexec/os-helpers")"
	eval "$(sed -n '/^osHasRegion()/,/^}/p' "$ROOT/libexec/os-helpers")"
	Dir="$WORK/sim/regions"
	mkdir -p "$Dir"

	osHasRegion '${Const|Dir}'
	assert_equals '1' "$?"

	# A region that is disabled is not one to load
	touch "$Dir/Region.ini.disabled"
	osHasRegion '${Const|Dir}'
	assert_equals '1' "$?"

	touch "$Dir/Region.ini"
	osHasRegion '${Const|Dir}'
	assert_equals '0' "$?"
}

function test_a_simulator_that_does_not_say_where_its_regions_are_is_left_alone() {
	eval "$(sed -n '/^osExpandConst()/,/^}/p' "$ROOT/libexec/os-helpers")"
	eval "$(sed -n '/^osHasRegion()/,/^}/p' "$ROOT/libexec/os-helpers")"

	osHasRegion ''
	assert_equals '0' "$?"
}
