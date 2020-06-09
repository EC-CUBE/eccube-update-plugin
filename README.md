### EC-CUBE アップデートプラグイン

EC-CUBE 4.0.x のマイナーバージョンアップを行うプラグインです。

### アップデートプラグイン作成手順

- ソースコード内のバージョン表記を置換

```sh
// NEXT_VERSIONを更新して保存
vi bin/replace_version.sh

NEXT_VERSION=404

// スクリプト実行
bin/replace_version.sh
```

- 更新ファイルを作成

```sh
// FROM, TOを更新して保存
vi bin/update_file_hash.sh

FROM=4.0.3
TO=4.0.4-rc

// スクリプト実行
bin/update_file_hash.sh
```
