---
paths:
  - "tests/**/*.php"
---

# Testing Rules

## TDDワークフロー（必ず守る）

```
1. 失敗するテストを先に書く（Red）
2. テストをパスする最小限のコードを書く（Green）
3. リファクタリングする（Refactor）
```

## テストクラスの雛形

```php
<?php
/**
 * Test class for [機能名].
 *
 * @package MyPlugin\Tests
 */

class Test_MyPlugin_Feature extends WP_UnitTestCase {

    /**
     * Set up each test.
     */
    public function set_up(): void {
        parent::set_up();
        // テスト前準備
    }

    /**
     * Tear down each test.
     */
    public function tear_down(): void {
        // テスト後クリーンアップ
        parent::tear_down();
    }

    /**
     * 正常系テスト例
     */
    public function test_returns_expected_value_on_valid_input(): void {
        $result = myplugin_some_function( 'valid_input' );
        $this->assertEquals( 'expected', $result );
    }

    /**
     * 異常系テスト例
     */
    public function test_returns_false_on_invalid_input(): void {
        $result = myplugin_some_function( '' );
        $this->assertFalse( $result );
    }

    /**
     * REST APIテスト例
     */
    public function test_rest_endpoint_returns_200_for_authenticated_user(): void {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
        $request  = new WP_REST_Request( 'GET', '/myplugin/v1/items' );
        $response = rest_do_request( $request );
        $this->assertEquals( 200, $response->get_status() );
    }

    /**
     * セキュリティテスト例（未認証は401/403を返すか）
     */
    public function test_rest_endpoint_requires_auth(): void {
        wp_set_current_user( 0 ); // 未認証
        $request  = new WP_REST_Request( 'POST', '/myplugin/v1/items' );
        $response = rest_do_request( $request );
        $this->assertContains( $response->get_status(), array( 401, 403 ) );
    }
}
```

## テスト命名規則

```
test_{何を}_{どんな条件で}_{何が期待される}()

例:
test_save_settings_returns_true_on_valid_data()
test_save_settings_returns_false_on_empty_input()
test_rest_endpoint_returns_200_for_subscriber()
```

## テスト実行コマンド

```bash
# 特定ファイルのみ（推奨・高速）
./vendor/bin/phpunit tests/test-settings.php

# 特定メソッドのみ
./vendor/bin/phpunit --filter test_save_settings_returns_true

# 全テスト（CIのみ）
composer run test
```

## ルール

- テストファイル名: `test-{機能名}.php`
- 1テストメソッド = 1アサーション（できるだけ）
- セキュリティ機能（nonce/権限）には必ず異常系テストを書く
- 全テストスイートはCIでのみ実行（ローカルは単体実行）
