#!/bin/sh

if [ -d ec-cube ]
then
    rm -rf ec-cube
fi
mkdir ec-cube
cd ec-cube
curl http://downloads.ec-cube.net/src/eccube-4.0.0.tar.gz | tar xz --strip-components 1
git init .
git add .
git commit -m 'first commit'
curl http://downloads.ec-cube.net/src/eccube-4.0.1.tar.gz | tar xz --strip-components 1
git diff --name-only > update_files.txt
git reset --hard HEAD
while read file
do
    md5 $file | sed -e "s/MD5 (//" -e "s/) = /: /" >> ../file_hash.yaml
    perl -p -i -e 's/\n/\r\n/g' $file
    md5 $file | sed -e "s/MD5 (//" -e "s/) = /: /" >> ../file_hash_crlf.yaml
done < update_files.txt
