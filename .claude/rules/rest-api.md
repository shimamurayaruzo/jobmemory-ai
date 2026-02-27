---
paths:
  - "includes/class-api.php"
  - "src/api/**"
---

# REST API Rules

## エンドポイント登録パターン

```php
add_action( 'rest_api_init', function() {
    register_rest_route(
        'myplugin/v1',
        '/items',
        array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => array(
                    'per_page' => array(
                        'type'              => 'integer',
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
            ),
        )
    );
} );
```

## 権限チェック

```php
// ❌ 本番では絶対に使わない
'permission_callback' => '__return_true'

// ✅ 必ず適切な権限チェックを実装
public function get_items_permissions_check( WP_REST_Request $request ): bool {
    return current_user_can( 'read' );
}
```

## レスポンス形式

```php
// 成功
return new WP_REST_Response( $data, 200 );

// エラー
return new WP_Error(
    'myplugin_not_found',
    __( 'Item not found.', 'myplugin' ),
    array( 'status' => 404 )
);
```

## ルール

- ネームスペースは `myplugin/v1` で統一
- `args` の `sanitize_callback` を必ず設定
- `permission_callback` の `__return_true` は禁止（開発時のみ許可）
- バージョンはURLパスに含める（`/v1/`）
