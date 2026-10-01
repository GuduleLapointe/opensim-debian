# How a simulator loads, from its log: each region module added to each region,
# with a count, then the region registered in the grid and ready. Read on the
# standard input, prints one line per step, from the start of what it reads.
#
#   awk -v regions=2 -f sim-progress.awk < simulator.log
#
# regions: how many regions the simulator has, for the "1/2" of the last steps.

BEGIN {
	if (regions < 1) {
		regions = 1
	}
}

# The modules found, so the total a region goes through
/\[REGIONMODULES\]: Found (non-)?shared region module/ {
	found++
	next
}

/\[REGIONMODULE\]: Adding scene / {
	line = $0
	sub(/.*\[REGIONMODULE\]: Adding scene /, "", line)
	if (match(line, / to (non-)?shared module /)) {
		region = substr(line, 1, RSTART - 1)
		module = substr(line, RSTART + RLENGTH)
		sub(/ \(deferred\)$/, "", module)
		loaded[region]++
		total = found > loaded[region] ? found : loaded[region]
		printf "  %s: %s %d/%d\n", region, module, loaded[region], total
	}
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
