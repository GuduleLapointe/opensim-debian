#!/bin/bash

project=$(basename $PWD)
branch=$(git rev-parse --abbrev-ref HEAD)
diffs=$(git diff --stat | head -1 | cut -d "|" -f 2)

gitRevision="$project $branch r/$(git log --pretty=oneline | wc -l | sed "s/[^0-9]*//g") (#$(git log -n 1 --pretty="format:%h") $diffs) $(date -u +"%F %T")"

echo $gitRevision
