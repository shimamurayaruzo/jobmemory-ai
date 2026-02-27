<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JMAI_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_jmai_generate_job', [ $this, 'ajax_generate_job' ] );
        add_action( 'wp_ajax_jmai_save_feedback', [ $this, 'ajax_save_feedback' ] );
        add_action( 'wp_ajax_jmai_save_job', [ $this, 'ajax_save_job' ] );
        add_action( 'wp_ajax_jmai_reset_memory', [ $this, 'ajax_reset_memory' ] );
    }

    public function add_menu(): void {
        add_menu_page(
            'JobMemory AI',
            'JobMemory AI',
            'manage_options',
            'jobmemory-ai',
            [ $this, 'render_generate_page' ],
            'dashicons-format-aside',
            30
        );

        add_submenu_page(
            'jobmemory-ai',
            '求人生成',
            '求人生成',
            'manage_options',
            'jobmemory-ai',
            [ $this, 'render_generate_page' ]
        );

        add_submenu_page(
            'jobmemory-ai',
            '設定',
            '設定',
            'manage_options',
            'jobmemory-ai-settings',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'jobmemory-ai',
            'Memory確認',
            'Memory確認',
            'manage_options',
            'jobmemory-ai-memory',
            [ $this, 'render_memory_page' ]
        );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'jobmemory-ai' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'jmai-admin',
            JMAI_PLUGIN_URL . 'assets/admin.css',
            [],
            JMAI_VERSION
        );

        wp_enqueue_script(
            'jmai-admin',
            JMAI_PLUGIN_URL . 'assets/admin.js',
            [ 'jquery' ],
            JMAI_VERSION,
            true
        );

        wp_localize_script( 'jmai-admin', 'jmai', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'jmai_nonce' ),
        ] );
    }

    /* ─── 求人生成画面 ─── */

    public function render_generate_page(): void {
        $api_key = get_option( 'jmai_openai_api_key', '' );
        ?>
        <div class="wrap jmai-wrap">
            <h1>JobMemory AI - 求人生成</h1>
            <p class="jmai-description">GAIS会員企業向けAI求人生成ツール</p>

            <?php if ( empty( $api_key ) ) : ?>
                <div class="notice notice-warning">
                    <p>OpenAI APIキーが未設定です。<a href="<?php echo esc_url( admin_url( 'admin.php?page=jobmemory-ai-settings' ) ); ?>">設定画面</a>でAPIキーを入力してください。</p>
                </div>
            <?php endif; ?>

            <form id="jmai-generate-form">
                <div class="jmai-card">
                    <h2>基本情報</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="job_title">職種名 <span class="jmai-required">*</span></label></th>
                            <td><input type="text" id="job_title" name="job_title" class="regular-text" placeholder="例：AIエンジニア、プロンプトエンジニア" required /></td>
                        </tr>
                    </table>
                </div>

                <div class="jmai-card">
                    <h2>自社の魅力・優位性</h2>
                    <p class="description">入力するほど良い求人文が生成されます</p>
                    <table class="form-table">
                        <tr>
                            <th><label for="recruitment_background">募集背景</label></th>
                            <td><textarea id="recruitment_background" name="recruitment_background" class="large-text" rows="2" placeholder="例：事業拡大のため、新規プロジェクト立ち上げのため"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="job_description">仕事内容の補足</label></th>
                            <td><textarea id="job_description" name="job_description" class="large-text" rows="2" placeholder="例：LLMを活用した社内ツール開発"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="company_strengths">自社の強み・魅力</label></th>
                            <td><textarea id="company_strengths" name="company_strengths" class="large-text" rows="2" placeholder="例：リモートワーク可、フレックス制度、AI研修充実"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="work_culture">職場環境・カルチャー</label></th>
                            <td><textarea id="work_culture" name="work_culture" class="large-text" rows="2" placeholder="例：少人数チーム、フラットな組織、挑戦を歓迎"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="salary_benefits">給与・待遇</label></th>
                            <td><textarea id="salary_benefits" name="salary_benefits" class="large-text" rows="2" placeholder="例：年収500-800万円、書籍購入補助あり"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="ideal_candidate">求める人物像</label></th>
                            <td><textarea id="ideal_candidate" name="ideal_candidate" class="large-text" rows="2" placeholder="例：自走できる方、新しい技術に興味がある方"></textarea></td>
                        </tr>
                    </table>
                </div>

                <p>
                    <button type="submit" class="button button-primary button-hero" id="jmai-generate-btn">AIで求人文を生成（3パターン）</button>
                </p>
            </form>

            <div id="jmai-loading" style="display:none;">
                <div class="jmai-loading-spinner">
                    <span class="spinner is-active"></span>
                    <span>3パターン生成中...（約30秒）</span>
                </div>
            </div>

            <div id="jmai-result-area" style="display:none;">
                <div class="jmai-card">
                    <h2>生成結果</h2>

                    <div class="jmai-tabs">
                        <button class="jmai-tab active" data-pattern="a">パターンA<br><small>スタンダード</small></button>
                        <button class="jmai-tab" data-pattern="b">パターンB<br><small>挑戦的</small></button>
                        <button class="jmai-tab" data-pattern="c">パターンC<br><small>カジュアル</small></button>
                    </div>

                    <div class="jmai-tab-content" id="pattern_a"></div>
                    <div class="jmai-tab-content" id="pattern_b" style="display:none;"></div>
                    <div class="jmai-tab-content" id="pattern_c" style="display:none;"></div>
                </div>

                <div class="jmai-card">
                    <h2>フィードバック（任意）</h2>
                    <p class="description">改善点を入力するとMemoryに蓄積され、次回以降の生成に反映されます</p>
                    <textarea id="jmai-feedback" class="large-text" rows="3" placeholder="この求人文の改善点があれば入力してください"></textarea>

                    <div class="jmai-actions">
                        <button type="button" class="button" id="jmai-save-feedback-btn">フィードバックを保存</button>
                        <button type="button" class="button button-primary" id="jmai-save-job-btn">Simple Job Boardに下書き保存</button>
                    </div>
                </div>
            </div>

            <div id="jmai-notices"></div>
        </div>
        <?php
    }

    /* ─── 設定画面 ─── */

    public function render_settings_page(): void {
        if ( isset( $_POST['jmai_save_settings'] ) && check_admin_referer( 'jmai_settings' ) ) {
            $api_key = sanitize_text_field( wp_unslash( $_POST['jmai_openai_api_key'] ?? '' ) );
            update_option( 'jmai_openai_api_key', $api_key );
            echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
        }

        $api_key = get_option( 'jmai_openai_api_key', '' );
        ?>
        <div class="wrap jmai-wrap">
            <h1>JobMemory AI - 設定</h1>
            <form method="post">
                <?php wp_nonce_field( 'jmai_settings' ); ?>
                <div class="jmai-card">
                    <table class="form-table">
                        <tr>
                            <th><label for="jmai_openai_api_key">OpenAI APIキー</label></th>
                            <td>
                                <input type="password" id="jmai_openai_api_key" name="jmai_openai_api_key" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" />
                                <p class="description">OpenAIのAPIキーを入力してください。<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">APIキーの取得はこちら</a></p>
                            </td>
                        </tr>
                    </table>
                </div>
                <p>
                    <button type="submit" name="jmai_save_settings" class="button button-primary">設定を保存</button>
                </p>
            </form>
        </div>
        <?php
    }

    /* ─── Memory確認画面 ─── */

    public function render_memory_page(): void {
        $memory = ( new JMAI_Memory() )->get();
        ?>
        <div class="wrap jmai-wrap">
            <h1>JobMemory AI - Memory確認</h1>
            <p class="jmai-description">GAISの共通知見と、蓄積されたフィードバック履歴を確認できます。</p>

            <div class="jmai-card">
                <h2>現在のMemory</h2>
                <textarea class="large-text jmai-memory-display" rows="20" readonly><?php echo esc_textarea( $memory ); ?></textarea>
            </div>

            <p>
                <button type="button" class="button button-secondary" id="jmai-reset-memory-btn">Memoryをリセット</button>
                <span class="description">※ リセットするとフィードバック履歴が削除され、初期状態に戻ります。</span>
            </p>
        </div>
        <?php
    }

    /* ─── AJAX: 求人文生成 ─── */

    public function ajax_generate_job(): void {
        check_ajax_referer( 'jmai_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => '権限がありません。' ] );
        }

        $job_title = sanitize_text_field( wp_unslash( $_POST['job_title'] ?? '' ) );
        if ( empty( $job_title ) ) {
            wp_send_json_error( [ 'message' => '職種名を入力してください。' ] );
        }

        $params = [
            'job_title'              => $job_title,
            'recruitment_background' => sanitize_textarea_field( wp_unslash( $_POST['recruitment_background'] ?? '' ) ),
            'job_description'        => sanitize_textarea_field( wp_unslash( $_POST['job_description'] ?? '' ) ),
            'company_strengths'      => sanitize_textarea_field( wp_unslash( $_POST['company_strengths'] ?? '' ) ),
            'work_culture'           => sanitize_textarea_field( wp_unslash( $_POST['work_culture'] ?? '' ) ),
            'salary_benefits'        => sanitize_textarea_field( wp_unslash( $_POST['salary_benefits'] ?? '' ) ),
            'ideal_candidate'        => sanitize_textarea_field( wp_unslash( $_POST['ideal_candidate'] ?? '' ) ),
        ];

        $client = new JMAI_AI_Client();
        $result = $client->generate( $params );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        wp_send_json_success( [
            'pattern_a' => $result['pattern_a'],
            'pattern_b' => $result['pattern_b'],
            'pattern_c' => $result['pattern_c'],
            'job_title' => $job_title,
        ] );
    }

    /* ─── AJAX: フィードバック保存 ─── */

    public function ajax_save_feedback(): void {
        check_ajax_referer( 'jmai_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => '権限がありません。' ] );
        }

        $feedback         = sanitize_textarea_field( wp_unslash( $_POST['feedback'] ?? '' ) );
        $job_title        = sanitize_text_field( wp_unslash( $_POST['job_title'] ?? '' ) );
        $selected_pattern = sanitize_text_field( wp_unslash( $_POST['selected_pattern'] ?? '' ) );

        if ( empty( $feedback ) ) {
            wp_send_json_error( [ 'message' => 'フィードバックを入力してください。' ] );
        }

        $pattern_labels = [
            'a' => 'スタンダード',
            'b' => '挑戦的',
            'c' => 'カジュアル',
        ];
        $label = $pattern_labels[ $selected_pattern ] ?? $selected_pattern;
        $date  = wp_date( 'Y-m-d H:i' );

        $entry = "\n[{$date}] 職種: {$job_title} / パターン: {$label}\nフィードバック: {$feedback}";

        $memory = new JMAI_Memory();
        $memory->append( $entry );

        wp_send_json_success( [ 'message' => 'フィードバックを保存しました。' ] );
    }

    /* ─── AJAX: Simple Job Boardに保存 ─── */

    public function ajax_save_job(): void {
        check_ajax_referer( 'jmai_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => '権限がありません。' ] );
        }

        $job_title        = sanitize_text_field( wp_unslash( $_POST['job_title'] ?? '' ) );
        $content           = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
        $selected_pattern = sanitize_text_field( wp_unslash( $_POST['selected_pattern'] ?? '' ) );

        if ( empty( $job_title ) || empty( $content ) ) {
            wp_send_json_error( [ 'message' => '保存するデータがありません。' ] );
        }

        if ( ! post_type_exists( 'jobpost' ) ) {
            wp_send_json_error( [ 'message' => 'Simple Job Boardプラグインが有効になっていません。' ] );
        }

        $post_id = wp_insert_post( [
            'post_type'    => 'jobpost',
            'post_title'   => $job_title,
            'post_content' => $content,
            'post_status'  => 'draft',
        ] );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => '保存に失敗しました: ' . $post_id->get_error_message() ] );
        }

        wp_send_json_success( [
            'message'  => '下書きとして保存しました。',
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        ] );
    }

    /* ─── AJAX: Memoryリセット ─── */

    public function ajax_reset_memory(): void {
        check_ajax_referer( 'jmai_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => '権限がありません。' ] );
        }

        $memory = new JMAI_Memory();
        $memory->reset();

        wp_send_json_success( [ 'message' => 'Memoryをリセットしました。' ] );
    }
}
