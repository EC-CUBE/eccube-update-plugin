#!/usr/bin/env bash

NEXT_VERSION=406

NEXT_FROM=$((NEXT_VERSION - 1))
NEXT_TO=$NEXT_VERSION

NEXT_FROM_STR=${NEXT_FROM:0:1}.${NEXT_FROM:1:1}.${NEXT_FROM:2:2}
NEXT_TO_STR=${NEXT_TO:0:1}.${NEXT_TO:1:1}.${NEXT_TO:2:2}

CURRENT_FROM=$((NEXT_VERSION - 2))
CURRENT_TO=$((NEXT_VERSION - 1))

CURRENT_FROM_STR=${CURRENT_FROM:0:1}.${CURRENT_FROM:1:1}.${CURRENT_FROM:2:2}
CURRENT_TO_STR=${CURRENT_TO:0:1}.${CURRENT_TO:1:1}.${CURRENT_TO:2:2}

files=`find . -type f -name '*.php' -or -name '*.twig'`

for file in $files; do
    sed -i '' "s/EccubeUpdater${CURRENT_FROM}to${CURRENT_TO}/EccubeUpdater${NEXT_FROM}to${NEXT_TO}/g" $file
    sed -i '' "s/eccube_updater${CURRENT_FROM}to${CURRENT_TO}/eccube_updater${NEXT_FROM}to${NEXT_TO}/g" $file
    sed -i '' "s/eccube_updater_${CURRENT_FROM}_to_${CURRENT_TO}/eccube_updater_${NEXT_FROM}_to_${NEXT_TO}/g" $file
    sed -i '' "s/${CURRENT_TO_STR}/${NEXT_TO_STR}/g" $file
    sed -i '' "s/${CURRENT_FROM_STR}/${NEXT_FROM_STR}/g" $file
done

sed -i '' "s/EccubeUpdater${CURRENT_FROM}to${CURRENT_TO}/EccubeUpdater${NEXT_FROM}to${NEXT_TO}/g" composer.json
