## EC-CUBE アップデートプラグイン

EC-CUBE 4.2.x のマイナーバージョンアップを行うプラグインです。

## 事前準備

スクリプトは macOS での実行を想定しています。
`gcp` コマンドが利用できない場合は事前にインストールをお願いします。

```sh
brew install coreutils
```

## アップデートプラグイン作成手順

### 1. ソースコード内のバージョン表記を置換

```sh
# VERSIONの情報を更新する
vi bin/replace_version.sh
```

NEXT_VERSIONには、アップデート後のバージョンを指定します。
例えば、4.2.2の場合は422と記載します。

```sh
NEXT_VERSION=422
```

OVERRIDEの部分は、前回更新したものが何から何への更新だったのかを記載します。
例えば、4.2.0から4.2.1への更新だった場合は以下のような記載になります

```sh
OVERRIDE_CURRENT_FROM=420
OVERRIDE_CURRENT_TO=421
OVERRIDE_CURRENT_FROM_STR=4.2.0
OVERRIDE_CURRENT_TO_STR=4.2.1
```

* スクリプト実行

```sh
bin/replace_version.sh
```

### 2. 更新ファイルを作成

```sh
# FROM, TOを更新して保存
vi bin/update_file_hash.sh
```

ソースコードの取得元となるそれぞれのFrom/Toのバージョンを記載します。

```sh
FROM=4.2.1
TO=4.2.2
```

- 補足
上記のFROM/TOの変数は以下のように、ソースコードの取得の際に使用します。
もし、テスト用にPreReleaseのバージョンなどで試したい場合は、変数の値を適切なものに書き換えてください。
（例えば、`4.2.2-20230616` のように）

```sh
curl -L https://github.com/EC-CUBE/ec-cube/releases/download/${FROM}/eccube-${FROM}.tar.gz | tar xz --strip-components 1
curl -L https://github.com/EC-CUBE/ec-cube/releases/download/${TO}/eccube-${TO}.tar.gz | tar xz --strip-components 1
```

### 3. スクリプト実行

```sh
# 実行します（実行権限があるかを念のためご確認ください）
bin/update_file_hash.sh
```

### 4. アップデートで特別な対応が必要な場合はスクリプトを修正

アップデート時にファイルを別で作成したり削除する、コマンドを叩く必要がある、などの場合はその処理を以下のファイルに追加してください。

```sh
vi Controller/Admin/ConfigController.php
```

### 5. リリースノートを修正

アップデートプラグインを実際に使用するタイミングで、プラグインの画面に記載しておく内容があれば記載するようにします。
例えば脆弱性の対応がアップデートの内容に含まれるような場合、その注意点を記載したりします。

```sh
vi Resource/template/admin/config.twig
```