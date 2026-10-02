# How a simulator loads, from its log, as a Robust shows its connectors: the
# plugins of modules it loads with a count, the database it brings up to date,
# then each region configured, in the grid and ready. Read on the standard
# input, prints one line per step, from the start of what it reads.
#
#   awk -v regions=2 -f sim-progress.awk < simulator.log
#
# regions: how many regions the simulator has, for the "1/2" of the region steps.
# mode=start: the output of the process at its very start (what it prints before its log
# has anything), of which only the first lines are steps.

BEGIN {
	if (regions < 1) {
		regions = 1
	}
}

# What the process says first, before its log has any plugin to report: it is the first
# sign of life of a simulator that takes a while to load. Shown as it is said.
tolower($0) ~ /configured log4net using|system locale|environment is supported by opensimulator|reading configuration settings|starting opensim|logging started to file/ {
	line = $0
	gsub(/\033\[[0-9;?]*[A-Za-z]/, "", line)
	gsub(/\r/, "", line)
	sub(/^[0-9-]+ [0-9:,.]+ +[A-Z]+ +(\[[^]]*\]: *)?/, "", line)
	sub(/^[[:space:]]+/, "", line)
	sub(/[[:space:]]+$/, "", line)
	if (line != "") {
		print "  " line
	}
	next
}

mode == "start" {
	next
}

# The plugins of region modules are all the plugins loaded but the ones of the
# framework and of the application: that is how many the log is going to report
/\[PLUGINS\]: Plugin Loaded: / {
	line = $0
	sub(/.*\[PLUGINS\]: Plugin Loaded: /, "", line)
	sub(/[[:space:]]+$/, "", line)
	if (line !~ /^(OpenSim|Robust|OpenSim\.Region\.Framework|OpenSim\.Data|OpenSim\.ApplicationPlugins\..*)$/) {
		plugins++
	}
	next
}

# The log reports the plugins twice, the second time to give them their region
/\[REGIONMODULES\]: Loading Region's modules/ {
	second = 1
	next
}

/\[REGIONMODULES\]: From plugin / {
	if (!second) {
		line = $0
		sub(/.*\[REGIONMODULES\]: From plugin /, "", line)
		sub(/, \(version .*/, "", line)
		shown++
		printf "  %s %d/%d\n", line, shown, (plugins > shown ? plugins : shown)
	}
	next
}

# The database, brought up to date a version at a time on a first start
/\[MIGRATIONS\]: Upgrading .* to latest revision / {
	line = $0
	sub(/.*\[MIGRATIONS\]: Upgrading /, "", line)
	store = line
	sub(/ to latest revision .*/, "", store)
	revision = line
	sub(/.* to latest revision /, "", revision)
	sub(/\..*/, "", revision)
	latest[store] = revision
	next
}

/\[MIGRATIONS\]: Updating .* to version / {
	line = $0
	sub(/.*\[MIGRATIONS\]: Updating /, "", line)
	store = line
	sub(/ to version .*/, "", store)
	version = line
	sub(/.* to version /, "", version)
	printf "  Database %s %d/%d\n", store, version, (latest[store] > version ? latest[store] : version)
	next
}

/\[REGION LOADER FILE SYSTEM\]: Loaded config for region / {
	line = $0
	sub(/.*Loaded config for region /, "", line)
	configured++
	printf "  %s: configured (%d/%d)\n", line, configured, regions
	next
}

/\[GRID SERVICE\]: Region .* registered at / {
	line = $0
	sub(/.*\[GRID SERVICE\]: Region /, "", line)
	if (match(line, / \([0-9a-fA-F-]+, /)) {
		registered++
		printf "  %s: registered in the grid (%d/%d)\n", substr(line, 1, RSTART - 1), registered, regions
	}
	next
}

/\[RegionReady\]: Region ".*" is ready/ {
	line = $0
	sub(/.*\[RegionReady\]: Region "/, "", line)
	sub(/".*/, "", line)
	ready++
	printf "  %s: ready (%d/%d)\n", line, ready, regions
	next
}
