#!/usr/bin/env bash

find . -type f -exec md5 {} \; | sed -e "s/MD5 (//" -e "s/) = /: /" > file_hash.yaml
