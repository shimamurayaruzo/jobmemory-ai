# WordPress Plugin — Project Rules

> このファイルはClaude Codeが**毎セッション冒頭に自動で読み込む**プロジェクトルールです。
> 公式ドキュメント https://code.claude.com/docs/en/memory に準拠した構成です。

---

## Project Overview

WordPressプラグイン開発プロジェクト。
- **WordPress**: 6.4+
- **PHP**: 8.1+
- **JavaScript**: React + @wordpress/scripts（ES Modules）
- **Coding Standard**: [WordPress Coding Standards (WPCS)](https://developer.wordpress.org/coding-standards/)

詳細アーキテクチャ → @docs/architecture.md
利用可能コマンド → @package.json / @composer.json

---

## Common Commands

```bash
# 開発サーバー起動
npm run start

# ビルド
npm run build

# PHPコーディング規約チェック
composer run phpcs

# PHP自動修正
composer run phpcbf

# PHPUnitテスト実行
composer run test

# 単一テストファイル実行（全体を回さない）
./vendor/bin/phpunit tests/test-TARGET.php

# JS型チェック
npm run type-check

# JSリント
npm run lint:js
```

---

## Workflow Rules

- コードを変更したあとは必ず `composer run phpcs` でチェックする
- テストは単体で実行する（全テストスイートは重いので避ける）
- UIを変更した場合は変更前後のスクリーンショットを比較する
- PRを出す前に `composer run test` と `npm run build` が通ることを確認する

---

## Branch Strategy

```
main     → 本番リリース済み（直接コミット禁止）
develop  → 開発統合
  └── feature/issue-{番号}-{機能名}
  └── fix/issue-{番号}-{内容}
  └── hotfix/issue-{番号}-{内容}
```

**コミットメッセージ形式:**

```
feat: 設定ページにダークモードを追加 (#12)
fix: 保存ボタンが500エラーになる問題を修正 (#15)
security: エスケープ漏れを修正 (#18)
test: REST APIエンドポイントのテストを追加 (#20)
```

---

## Security — MUST follow

> ⚠️ セキュリティルールはすべてのコード生成で例外なく適用すること

**入力（DB・処理に渡す前）は必ずサニタイズ:**
```php
sanitize_text_field()  // テキスト
sanitize_email()       // メール
esc_url_raw()          // URL（保存時）
absint()               // 正の整数
wp_kses_post()         // HTML許可コンテンツ
```

**出力（HTML表示前）は必ずエスケープ:**
```php
esc_html()   // 本文テキスト
esc_attr()   // 属性値
esc_url()    // リンクURL
esc_js()     // JS内変数
```

**フォーム・AJAX処理では必ずNonce検証:**
```php
// フォーム
wp_nonce_field( 'myplugin_action', 'myplugin_nonce' );
wp_verify_nonce( $_POST['myplugin_nonce'], 'myplugin_action' );

// AJAX
check_ajax_referer( 'myplugin_ajax', 'nonce' );
```

**権限チェックを必ず実施:**
```php
current_user_can( 'manage_options' )  // 管理者機能
current_user_can( 'edit_post', $id )  // 投稿編集
```

**DB操作は必ず `$wpdb->prepare()` を使う:**
```php
$wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}table WHERE id = %d", $id ) );
```

---

## PHP Coding Standards

```php
// 命名: スネークケース（関数・変数・フック）
function myplugin_get_settings() {}
add_action( 'myplugin_after_save', 'callback' );

// 命名: アッパーキャメル（クラス）
class MyPlugin_Admin {}

// 命名: 全大文字（定数）
define( 'MYPLUGIN_VERSION', '1.0.0' );

// インデント: タブ（スペース不可）
// 制御構造の括弧内にスペース
if ( true === $flag ) {}

// Yoda条件（定数・リテラルを左辺に）
if ( 'publish' === $post->post_status ) {}

// 配列: long syntax
$args = array( 'key' => 'value' );  // [] は使わない

// die()ではなく wp_die() を使う
wp_die( esc_html__( 'Error', 'myplugin' ) );
```

---

## Internationalization (i18n)

すべての表示文字列をi18n関数でラップすること:

```php
__( 'Text', 'myplugin' )          // 文字列返却
_e( 'Text', 'myplugin' )          // 直接出力
_n( '%d item', '%d items', $n, 'myplugin' )  // 複数形
esc_html__( 'Text', 'myplugin' )  // エスケープ付き返却
esc_html_e( 'Text', 'myplugin' )  // エスケープ付き出力
```

---

## DO NOT (禁止操作)

- `main` / `master` ブランチへの直接コミット
- `$wpdb->query()` での未prepareなSQL実行
- `eval()` / `extract()` / `shell_exec()` / バッククォート演算子
- `@` エラー制御演算子
- ショートタグ `<?=` の使用（必ず `<?php echo` ）
- `die()` / `exit()` の直接呼び出し（`wp_die()` を使う）
- Nonce・権限チェックなしのフォーム・AJAX処理
- `add_action()` / `add_filter()` へのクロージャ直接渡し（`remove_action` できなくなる）
- APIキー・パスワード等のフロントエンド（JS/HTML）への露出

---

## Directory Structure

```
my-plugin/
├── my-plugin.php          # メインファイル（ヘッダー・初期化）
├── uninstall.php          # アンインストール処理
├── composer.json
├── package.json
├── includes/
│   ├── class-plugin.php   # コアクラス（シングルトン）
│   ├── class-admin.php
│   ├── class-api.php      # REST API
│   └── class-db.php
├── src/                   # JSソース（ビルド前）
├── build/                 # ビルド済みJS/CSS
├── languages/
└── tests/
    ├── bootstrap.php
    └── test-*.php
```

---

## TDD Workflow

```
1. テストを先に書く（Red）
2. Claude Codeにテストをパスするコードを書かせる（Green）
3. リファクタリング（Refactor）
```

**単一テスト実行例:**
```bash
./vendor/bin/phpunit tests/test-api.php --filter test_endpoint_returns_200
```

---

## Modular Rules

詳細ルールはトピック別ファイルで管理（自動ロード）:

- セキュリティ詳細 → @.claude/rules/security.md
- REST API規約 → @.claude/rules/rest-api.md
- テスト規約 → @.claude/rules/testing.md
- GitHub Actionsワークフロー → @.github/workflows/ci.yml
