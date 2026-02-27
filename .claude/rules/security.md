---
paths:
  - "includes/**/*.php"
  - "src/**/*.php"
---

# Security Rules (PHP)

## Input Sanitization

| 用途 | 使う関数 |
|------|---------|
| 一般テキスト | `sanitize_text_field()` |
| メールアドレス | `sanitize_email()` |
| URL（保存用） | `esc_url_raw()` |
| 正の整数 | `absint()` |
| 整数（負もあり） | `intval()` |
| HTMLコンテンツ | `wp_kses_post()` |
| スラッグ・キー | `sanitize_key()` |
| ファイル名 | `sanitize_file_name()` |

## Output Escaping

| 用途 | 使う関数 |
|------|---------|
| HTML本文 | `esc_html()` |
| HTML属性 | `esc_attr()` |
| リンクURL | `esc_url()` |
| JS変数 | `esc_js()` |
| CSSプロパティ | `esc_attr()` |
| 翻訳文字列 | `esc_html__()` / `esc_attr__()` |

## Nonce Pattern

```php
// フォーム作成側
wp_nonce_field( 'myplugin_{action}', 'myplugin_nonce' );

// 処理側（必ずセットで実装する）
if ( ! isset( $_POST['myplugin_nonce'] ) ||
     ! wp_verify_nonce(
         sanitize_text_field( wp_unslash( $_POST['myplugin_nonce'] ) ),
         'myplugin_{action}'
     )
) {
    wp_die( esc_html__( 'Security check failed.', 'myplugin' ) );
}
```

## AJAX Pattern

```php
// JS側: nonce埋め込み
wp_localize_script(
    'myplugin-script',
    'mypluginAjax',
    array(
        'nonce' => wp_create_nonce( 'myplugin_ajax' ),
        'url'   => admin_url( 'admin-ajax.php' ),
    )
);

// PHP側: ハンドラ
add_action( 'wp_ajax_myplugin_action', 'myplugin_ajax_handler' );
add_action( 'wp_ajax_nopriv_myplugin_action', 'myplugin_ajax_handler' );

function myplugin_ajax_handler(): void {
    check_ajax_referer( 'myplugin_ajax', 'nonce' );
    if ( ! current_user_can( 'read' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
    }
    // 処理...
    wp_send_json_success( $data );
}
```
