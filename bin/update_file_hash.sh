#!/usr/bin/env bash

BASE_DIR=$(pwd)
WORK_DIR=${BASE_DIR}/work
FROM=4.0.3
TO=4.0.4-rc3

# 差分チェックの対象外ファイルの一覧
# ここで指定したファイルは, プラグインの差分チェック時の対象外になります
SKIP_FILES=(
composer.json
composer.lock
symfony.lock
vendor/autoload.php
vendor/composer/autoload_classmap.php
vendor/composer/autoload_files.php
vendor/composer/autoload_namespaces.php
vendor/composer/autoload_psr4.php
vendor/composer/autoload_real.php
vendor/composer/autoload_static.php
vendor/composer/installed.json
)

if [ -d ${WORK_DIR} ]
then
    rm -rf ${WORK_DIR}
fi

echo -n '' > ${BASE_DIR}/Resource/file_hash/file_hash.yaml
echo -n '' > ${BASE_DIR}/Resource/file_hash/file_hash_crlf.yaml
mkdir -p ${WORK_DIR}/ec-cube
mkdir -p ${WORK_DIR}/update_file

cd ${WORK_DIR}/ec-cube
curl http://downloads.ec-cube.net/src/eccube-${FROM}.tar.gz | tar xz --strip-components 1
git init .
git add .
git commit -m 'first commit'
curl http://downloads.ec-cube.net/src/eccube-${TO}.tar.gz | tar xz --strip-components 1
git add .
git diff --name-only --cached > ${WORK_DIR}/update_files.txt

# アップデートファイルの作成
while read file
do
    if [ -f $file ]
    then
        gcp --parents $file ${WORK_DIR}/update_file
    fi
done < ${WORK_DIR}/update_files.txt

cd ${WORK_DIR}/update_file
tar cvzf ${BASE_DIR}/Resource/update_file.tar.gz ./*

cd ${WORK_DIR}/ec-cube
git reset --hard HEAD

# composer.jsonのコピー
cp -f composer.json ${BASE_DIR}/Resource/file_hash/composer.json

# 更新対象ファイルの一覧を作成
while read file
do
    # 不要ファイルはスキップ
    for skip in "${SKIP_FILES[@]}"; do
      if [[ "$skip" = "$file" ]]; then
          continue 2
      fi
    done

    if [ -f $file ]
    then
        md5 $file | sed -e "s/MD5 (//" -e "s/) = /: /" >> ${BASE_DIR}/Resource/file_hash/file_hash.yaml
        perl -p -i -e 's/\n/\r\n/g' $file
        md5 $file | sed -e "s/MD5 (//" -e "s/) = /: /" >> ${BASE_DIR}/Resource/file_hash/file_hash_crlf.yaml
    fi
done < ${WORK_DIR}/update_files.txt

cd ${BASE_DIR}
rm -rf ${WORK_DIR}
