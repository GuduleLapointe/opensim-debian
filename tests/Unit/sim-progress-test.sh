#!/usr/bin/env bash

# Tests for libexec/sim-progress.awk: the steps of a simulator starting, read in its log.
#
# Run with: tests/lib/bashunit tests/Unit (or vendor/bin/pest)

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# The steps of a log (first argument), for a simulator with so many regions (second)
progress() {
	awk -v regions="${2:-1}" -f "$ROOT/libexec/sim-progress.awk" <<<"$1"
}

function test_plugins_of_modules_are_counted_on_the_total_the_log_announces() {
	local log='2026-10-01 20:51:08,005 INFO  [PLUGINS]: Plugin Loaded: OpenSim
2026-10-01 20:51:08,006 INFO  [PLUGINS]: Plugin Loaded: OpenSim.ApplicationPlugins.RegionModulesController
2026-10-01 20:51:08,007 INFO  [PLUGINS]: Plugin Loaded: OpenSim.ApplicationPlugins.RemoteController
2026-10-01 20:51:08,011 INFO  [PLUGINS]: Plugin Loaded: LindenUDP
2026-10-01 20:51:08,012 INFO  [PLUGINS]: Plugin Loaded: OpenSim.Region.CoreModules
2026-10-01 20:51:08,100 INFO  [REGIONMODULES]: From plugin LindenUDP, (version 0.9.3.0), loaded 1 modules, 0 shared, 1 non-shared 0 unknown
2026-10-01 20:51:08,101 INFO  [REGIONMODULES]: From plugin OpenSim.Region.CoreModules, (version 0.9.3.0), loaded 113 modules, 74 shared, 39 non-shared 0 unknown'

	assert_equals "  LindenUDP 1/2
  OpenSim.Region.CoreModules 2/2" "$(progress "$log")"
}

function test_the_second_report_of_the_plugins_is_not_shown_again() {
	local log="2026-10-01 20:51:08,010 INFO  [PLUGINS]: Plugin Loaded: OpenSim.ApplicationPlugins.RegionModulesController
2026-10-01 20:51:08,011 INFO  [PLUGINS]: Plugin Loaded: LindenUDP
2026-10-01 20:51:08,100 INFO  [REGIONMODULES]: From plugin LindenUDP, (version 0.9.3.0), loaded 1 modules, 0 shared, 1 non-shared 0 unknown
2026-10-01 20:51:08,200 INFO  [REGIONMODULES]: Loading Region's modules
2026-10-01 20:51:08,201 INFO  [REGIONMODULES]: From plugin LindenUDP, (version 0.9.3.0), loaded 1 modules, 0 shared, 1 non-shared 0 unknown"

	assert_equals "  LindenUDP 1/1" "$(progress "$log")"
}

function test_the_database_is_counted_up_to_its_latest_revision() {
	local log="2026-10-01 INFO  [MIGRATIONS]: Upgrading RegionStore to latest revision 66.
2026-10-01 INFO  [MIGRATIONS]: Updating RegionStore to version 65
2026-10-01 INFO  [MIGRATIONS]: Updating RegionStore to version 66"

	assert_equals "  Database RegionStore 65/66
  Database RegionStore 66/66" "$(progress "$log")"
}

function test_a_region_goes_from_its_config_to_the_grid_then_ready() {
	local log='2026-10-01 INFO  [REGION LOADER FILE SYSTEM]: Loaded config for region Sim North
2026-10-01 20:51:08,845 DEBUG [GRID SERVICE]: Region Sim North (607c44e9-3d01-45eb-a07e-937dc72dbadb, 256x256) registered at 1000,1001 with flags RegionOnline
2026-10-01 20:51:09,100 DEBUG [RegionReady]: Region "Sim North" is ready: "OnlineDelay" on channel -800'

	assert_equals '  Sim North: configured (1/2)
  Sim North: registered in the grid (1/2)
  Sim North: ready (1/2)' "$(progress "$log" 2)"
}

function test_other_lines_say_nothing() {
	assert_equals "" "$(progress "2026-10-01 20:51:08,617 DEBUG [WORLD MAP]: Generating map image for Far")"
}

# The first lines a process prints, in a file of their own, before its log says anything
start_progress() {
	awk -v mode=start -f "$ROOT/libexec/sim-progress.awk" <<<"$1"
}

function test_the_first_signs_of_life_are_shown_as_the_process_says_them() {
	local out='2026-10-01 20:51:03,100 INFO  [LOG4NET]: configured log4net using "/etc/opensim/OpenSim.exe.config"
2026-10-01 20:51:03,200 INFO  [OPENSIM MAIN]: System Locale is en-US
2026-10-01 20:51:03,300 INFO  [OPENSIM MAIN]: Environment is supported by OpenSimulator.
2026-10-01 20:51:03,400 INFO  [OPENSIM MAIN]: Reading configuration settings
2026-10-01 20:51:03,500 INFO  [OPENSIM MAIN]: STARTING OPENSIM
2026-10-01 20:51:03,600 INFO  [OPENSIM MAIN]: Logging started to file /var/log/opensim/sim.log
2026-10-01 20:51:03,700 INFO  [PLUGINS]: Plugin Loaded: LindenUDP'

	assert_equals '  configured log4net using "/etc/opensim/OpenSim.exe.config"
  System Locale is en-US
  Environment is supported by OpenSimulator.
  Reading configuration settings
  STARTING OPENSIM
  Logging started to file /var/log/opensim/sim.log' "$(start_progress "$out")"
}

function test_the_output_of_a_screen_session_has_its_control_codes_removed() {
	assert_equals "  STARTING OPENSIM" "$(start_progress $'\033[1;32mSTARTING OPENSIM\033[0m\r')"
}

function test_the_log_says_them_too_with_the_steps() {
	assert_equals "  STARTING OPENSIM" "$(progress "2026-10-01 20:51:03,500 INFO  [OPENSIM MAIN]: STARTING OPENSIM")"
}
