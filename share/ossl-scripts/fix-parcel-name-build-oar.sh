#!/usr/bin/env bash

set -e

PGM=$(basename "$0")
PGM_DIR=$(cd "$(dirname "$0")" && pwd)
SCRIPT=${PGM%-build-oar.sh}
SOURCE=${PGM_DIR}/${SCRIPT}-src
OAR=${PGM_DIR}/${SCRIPT}.oar

for command in gtar tar; do
	command -v command && tar=$command && break
done
if [ -z "${tar:-}" ]; then
	echo "$PGM: No tar command found" >&2
	exit 1
fi

echo "$PGM: Using ${command} to build OAR file" >&2

echo "$PGM: Removing old OAR files in ${PGM_DIR}/" >&2
rm -f ${PGM_DIR}/*.oar

cd ${SOURCE}

echo "$PGM: Building OAR file from ${SOURCE}" >&2
${tar} cvfz ${OAR} *
echo "$PGM: OAR file completed:" >&2
echo ${OAR}
