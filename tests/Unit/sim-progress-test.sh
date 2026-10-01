#!/usr/bin/env bash

# Tests for libexec/sim-progress.awk: the steps of a simulator starting, read in its log.
#
# Run with: tests/lib/bashunit tests/Unit (or vendor/bin/pest)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# The steps of a log (first argument), for a simulator with so many regions (second)
progress() {
	awk -v regions="${2:-1}" -f "$ROOT/libexec/sim-progress.awk" <<<"$1"
}

function test_modules_are_counted_for_each_region() {
	local log="2026-10-01 20:51:08,100 DEBUG [REGIONMODULES]: Found shared region module A, class X
2026-10-01 20:51:08,101 DEBUG [REGIONMODULES]: Found non-shared region module B, class Y
2026-10-01 20:51:08,300 DEBUG [REGIONMODULE]: Adding scene Sim North to shared module A
2026-10-01 20:51:08,301 DEBUG [REGIONMODULE]: Adding scene Sim North to non-shared module B
2026-10-01 20:51:08,302 DEBUG [REGIONMODULE]: Adding scene Far to shared module A"

	assert_equals "  Sim North: A 1/2
  Sim North: B 2/2
  Far: A 1/2" "$(progress "$log" 2)"
}

function test_a_region_goes_to_the_grid_then_ready() {
	local log='2026-10-01 20:51:08,845 DEBUG [GRID SERVICE]: Region Far (607c44e9-3d01-45eb-a07e-937dc72dbadb, 256x256) registered at 1000,1000 with flags RegionOnline
2026-10-01 20:51:09,100 DEBUG [RegionReady]: Region "Far" is ready: "OnlineDelay" on channel -800'

	assert_equals '  Far: registered in the grid (1/2)
  Far: ready (1/2)' "$(progress "$log" 2)"
}

function test_a_deferred_module_is_named_without_it() {
	assert_equals "  Far: A 1/1" "$(progress "2026-10-01 DEBUG [REGIONMODULE]: Adding scene Far to shared module A (deferred)")"
}

function test_other_lines_say_nothing() {
	assert_equals "" "$(progress "2026-10-01 20:51:08,617 DEBUG [WORLD MAP]: Generating map image for Far")"
}
