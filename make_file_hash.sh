#!/usr/bin/env bash

find . -type f -exec md5 {} \; | sed -e "s/MD5 (//" -e "s/) = /: /" > file_hash.yaml

find . -type f -exec perl -p -i -e 's/\n/\r\n/g' {} \;

find . -type f -exec md5 {} \; | sed -e "s/MD5 (//" -e "s/) = /: /" > file_hash_crlf.yaml


