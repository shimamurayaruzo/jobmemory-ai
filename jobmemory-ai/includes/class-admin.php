<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JMAI_Admin {

	private const ALLOWED_PATTERNS = array( 'a', 'b', 'c' );

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_jmai_generate_job', array( $this, 'ajax_generate_job' ) );
		add_action( 'wp_ajax_jmai_save_feedback', array( $this, 'ajax_save_feedback' ) );
		add_action( 'wp_ajax_jmai_save_job', array( $this, 'ajax_save_job' ) );
		add_action( 'wp_ajax_jmai_reset_memory', array( $this, 'ajax_reset_memory' ) );
		add_action( 'wp_ajax_jmai_fetch_company_info', array( $this, 'ajax_fetch_company_info' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			'JobMemory AI',
			'JobMemory AI',
			'manage_options',
			'jobmemory-ai',
			array( $this, 'render_generate_page' ),
			'dashicons-format-aside',
			30
		);

		add_submenu_page(
			'jobmemory-ai',
			esc_html__( '求人生成', 'jobmemory-ai' ),
			esc_html__( '求人生成', 'jobmemory-ai' ),
			'manage_options',
			'jobmemory-ai',
			array( $this, 'render_generate_page' )
		);

		add_submenu_page(
			'jobmemory-ai',
			esc_html__( '設定', 'jobmemory-ai' ),
			esc_html__( '設定', 'jobmemory-ai' ),
			'manage_options',
			'jobmemory-ai-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'jobmemory-ai',
			esc_html__( 'Memory確認', 'jobmemory-ai' ),
			esc_html__( 'Memory確認', 'jobmemory-ai' ),
			'manage_options',
			'jobmemory-ai-memory',
			array( $this, 'render_memory_page' )
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'jobmemory-ai' ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'jmai-admin',
			JMAI_PLUGIN_URL . 'assets/admin.css',
			array(),
			JMAI_VERSION
		);

		wp_enqueue_script(
			'jmai-admin',
			JMAI_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			JMAI_VERSION,
			true
		);

		wp_localize_script(
			'jmai-admin',
			'jmai',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'jmai_nonce' ),
			)
		);
	}

	/* ─── 求人生成画面 ─── */

	public function render_generate_page(): void {
		$api_key = get_option( 'jmai_openai_api_key', '' );
		?>
		<div class="wrap jmai-wrap">
			<h1><?php esc_html_e( 'JobMemory AI - 求人生成', 'jobmemory-ai' ); ?></h1>
			<p class="jmai-description"><?php esc_html_e( 'GAIS会員企業向けAI求人生成ツール', 'jobmemory-ai' ); ?></p>

			<?php if ( empty( $api_key ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: settings page link */
							esc_html__( 'OpenAI APIキーが未設定です。%sでAPIキーを入力してください。', 'jobmemory-ai' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=jobmemory-ai-settings' ) ) . '">' . esc_html__( '設定画面', 'jobmemory-ai' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<form id="jmai-generate-form">
				<div class="jmai-card">
					<h2><?php esc_html_e( '基本情報', 'jobmemory-ai' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="job_title"><?php esc_html_e( '職種名', 'jobmemory-ai' ); ?> <span class="jmai-required">*</span></label></th>
							<td><input type="text" id="job_title" name="job_title" class="regular-text" placeholder="<?php esc_attr_e( '例：AIエンジニア、プロンプトエンジニア', 'jobmemory-ai' ); ?>" required /></td>
						</tr>
					</table>
				</div>

			<div class="jmai-card">
				<h2><?php esc_html_e( '会社HPから情報を取得（任意）', 'jobmemory-ai' ); ?></h2>
				<p class="description"><?php esc_html_e( '会社概要・採用ページのURLがおすすめです', 'jobmemory-ai' ); ?></p>
				<div class="jmai-hp-input-row">
					<input type="url" id="jmai-company-url" class="regular-text" placeholder="https://example.co.jp/about" />
					<button type="button" id="jmai-fetch-hp" class="button button-secondary"><?php esc_html_e( '情報を取得', 'jobmemory-ai' ); ?></button>
					<span id="jmai-fetch-spinner" class="spinner"></span>
				</div>
				<div id="jmai-fetch-message" class="jmai-message"></div>
			</div>

			<div class="jmai-card">
				<h2><?php esc_html_e( '自社の魅力・優位性', 'jobmemory-ai' ); ?></h2>
				<p class="description"><?php esc_html_e( '入力するほど良い求人文が生成されます。HPから取得した情報は自動入力されます。', 'jobmemory-ai' ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="recruitment_background"><?php esc_html_e( '募集背景', 'jobmemory-ai' ); ?></label></th>
							<td><textarea id="recruitment_background" name="recruitment_background" class="large-text" rows="2" placeholder="<?php esc_attr_e( '例：事業拡大のため、新規プロジェクト立ち上げのため', 'jobmemory-ai' ); ?>"></textarea></td>
						</tr>
						<tr>
							<th><label for="job_description"><?php esc_html_e( '仕事内容の補足', 'jobmemory-ai' ); ?></label></th>
							<td><textarea id="job_description" name="job_description" class="large-text" rows="2" placeholder="<?php esc_attr_e( '例：LLMを活用した社内ツール開発', 'jobmemory-ai' ); ?>"></textarea></td>
						</tr>
					<tr>
						<th><label for="business_description"><?php esc_html_e( '事業内容', 'jobmemory-ai' ); ?></label></th>
						<td><textarea id="business_description" name="business_description" class="large-text" rows="2" placeholder="<?php esc_attr_e( '例：AI/DXソリューションの開発・提供', 'jobmemory-ai' ); ?>"></textarea></td>
					</tr>
					<tr>
						<th><label for="company_strengths"><?php esc_html_e( '自社の強み・魅力', 'jobmemory-ai' ); ?></label></th>
							<td><textarea id="company_strengths" name="company_strengths" class="large-text" rows="2" placeholder="<?php esc_attr_e( '例：リモートワーク可、フレックス制度、AI研修充実', 'jobmemory-ai' ); ?>"></textarea></td>
						</tr>
						<tr>
							<th><label for="work_culture"><?php esc_html_e( '職場環境・カルチャー', 'jobmemory-ai' ); ?></label></th>
							<td><textarea id="work_culture" name="work_culture" class="large-text" rows="2" placeholder="<?php esc_attr_e( '例：少人数チーム、フラットな組織、挑戦を歓迎', 'jobmemory-ai' ); ?>"></textarea></td>
						</tr>
						<tr>
							<th><label for="salary_benefits"><?php esc_html_e( '給与・待遇', 'jobmemory-ai' ); ?></label></th>
							<td><textarea id="salary_benefits" name="salary_benefits" class="large-text" rows="2" placeholder="<?php esc_attr_e( '例：年収500-800万円、書籍購入補助あり', 'jobmemory-ai' ); ?>"></textarea></td>
						</tr>
						<tr>
							<th><label for="ideal_candidate"><?php esc_html_e( '求める人物像', 'jobmemory-ai' ); ?></label></th>
							<td><textarea id="ideal_candidate" name="ideal_candidate" class="large-text" rows="2" placeholder="<?php esc_attr_e( '例：自走できる方、新しい技術に興味がある方', 'jobmemory-ai' ); ?>"></textarea></td>
						</tr>
					</table>
				</div>

				<p>
					<button type="submit" class="button button-primary button-hero" id="jmai-generate-btn"><?php esc_html_e( 'AIで求人文を生成（3パターン）', 'jobmemory-ai' ); ?></button>
				</p>
			</form>

			<div id="jmai-loading" style="display:none;">
				<div class="jmai-loading-spinner">
					<span class="spinner is-active"></span>
					<span><?php esc_html_e( '3パターン生成中...（約30秒）', 'jobmemory-ai' ); ?></span>
				</div>
			</div>

			<div id="jmai-result-area" style="display:none;">
				<div class="jmai-card">
					<h2><?php esc_html_e( '生成結果', 'jobmemory-ai' ); ?></h2>

					<div class="jmai-tabs">
						<button class="jmai-tab active" data-pattern="a"><?php esc_html_e( 'パターンA', 'jobmemory-ai' ); ?><br><small><?php esc_html_e( 'スタンダード', 'jobmemory-ai' ); ?></small></button>
						<button class="jmai-tab" data-pattern="b"><?php esc_html_e( 'パターンB', 'jobmemory-ai' ); ?><br><small><?php esc_html_e( '挑戦的', 'jobmemory-ai' ); ?></small></button>
						<button class="jmai-tab" data-pattern="c"><?php esc_html_e( 'パターンC', 'jobmemory-ai' ); ?><br><small><?php esc_html_e( 'カジュアル', 'jobmemory-ai' ); ?></small></button>
					</div>

					<div class="jmai-tab-content" id="pattern_a"></div>
					<div class="jmai-tab-content" id="pattern_b" style="display:none;"></div>
					<div class="jmai-tab-content" id="pattern_c" style="display:none;"></div>
				</div>

				<div class="jmai-card" id="jmai-advice-area" style="display:none;">
					<h2>💡 <?php esc_html_e( 'AIからのアドバイス', 'jobmemory-ai' ); ?></h2>
					<div id="jmai-advice-content" class="jmai-advice-content"></div>
				</div>

				<div class="jmai-card">
					<h2><?php esc_html_e( '画像の追加', 'jobmemory-ai' ); ?></h2>
					<p class="description"><?php esc_html_e( '求人に掲載する画像を追加できます。最初の1枚がアイキャッチ画像になります。', 'jobmemory-ai' ); ?></p>
					<div id="jmai-images-preview" class="jmai-images-preview"></div>
					<p>
						<button type="button" class="button" id="jmai-add-image-btn"><?php esc_html_e( '画像を追加', 'jobmemory-ai' ); ?></button>
					</p>
					<input type="hidden" id="jmai-image-ids" value="" />
				</div>

				<div class="jmai-card">
					<h2><?php esc_html_e( '求人情報の指摘事項', 'jobmemory-ai' ); ?></h2>
					<p class="description"><?php esc_html_e( '指摘内容を元に選択中のパターンをAIが再作成します（Memoryにも蓄積されます）', 'jobmemory-ai' ); ?></p>
					<textarea id="jmai-feedback" class="large-text" rows="3" placeholder="<?php esc_attr_e( 'この求人文の改善点があれば入力してください', 'jobmemory-ai' ); ?>"></textarea>

					<div class="jmai-actions">
						<button type="button" class="button" id="jmai-save-feedback-btn"><?php esc_html_e( '指摘を送信して再作成', 'jobmemory-ai' ); ?></button>
						<button type="button" class="button button-primary" id="jmai-save-job-btn"><?php esc_html_e( 'Simple Job Boardに下書き保存', 'jobmemory-ai' ); ?></button>
					</div>

					<div id="jmai-notices"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ─── 設定画面 ─── */

	public function render_settings_page(): void {
		if ( isset( $_POST['jmai_save_settings'] )
			&& current_user_can( 'manage_options' )
			&& check_admin_referer( 'jmai_settings' )
		) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['jmai_openai_api_key'] ?? '' ) );

			if ( JMAI_AI_Client::validate_api_key( $api_key ) ) {
				update_option( 'jmai_openai_api_key', $api_key );
				echo '<div class="notice notice-info"><p style="color:#0073aa;font-weight:bold;">'
					. esc_html__( '登録成功', 'jobmemory-ai' )
					. '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p style="color:#d63638;font-weight:bold;">'
					. esc_html__( '登録失敗', 'jobmemory-ai' )
					. '</p></div>';
			}
		}

		$api_key = get_option( 'jmai_openai_api_key', '' );
		?>
		<div class="wrap jmai-wrap">
			<h1><?php esc_html_e( 'JobMemory AI - 設定', 'jobmemory-ai' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'jmai_settings' ); ?>
				<div class="jmai-card">
					<table class="form-table">
						<tr>
							<th><label for="jmai_openai_api_key"><?php esc_html_e( 'OpenAI APIキー', 'jobmemory-ai' ); ?></label></th>
							<td>
								<input type="password" id="jmai_openai_api_key" name="jmai_openai_api_key" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: OpenAI API keys URL */
										esc_html__( 'OpenAIのAPIキーを入力してください。%s', 'jobmemory-ai' ),
										'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">' . esc_html__( 'APIキーの取得はこちら', 'jobmemory-ai' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
					</table>
				</div>
				<p>
					<button type="submit" name="jmai_save_settings" class="button button-primary"><?php esc_html_e( '設定を保存', 'jobmemory-ai' ); ?></button>
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
			<h1><?php esc_html_e( 'JobMemory AI - Memory確認', 'jobmemory-ai' ); ?></h1>
			<p class="jmai-description"><?php esc_html_e( 'GAISの共通知見と、蓄積されたフィードバック履歴を確認できます。', 'jobmemory-ai' ); ?></p>

			<div class="jmai-card">
				<h2><?php esc_html_e( '現在のMemory', 'jobmemory-ai' ); ?></h2>
				<textarea class="large-text jmai-memory-display" rows="20" readonly><?php echo esc_textarea( $memory ); ?></textarea>
			</div>

			<p>
				<button type="button" class="button button-secondary" id="jmai-reset-memory-btn"><?php esc_html_e( 'Memoryをリセット', 'jobmemory-ai' ); ?></button>
				<span class="description"><?php esc_html_e( '※ リセットするとフィードバック履歴が削除され、初期状態に戻ります。', 'jobmemory-ai' ); ?></span>
			</p>
		</div>
		<?php
	}

	/* ─── AJAX: 求人文生成 ─── */

	public function ajax_generate_job(): void {
		check_ajax_referer( 'jmai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'jobmemory-ai' ) ) );
		}

		$job_title = sanitize_text_field( wp_unslash( $_POST['job_title'] ?? '' ) );
		if ( empty( $job_title ) ) {
			wp_send_json_error( array( 'message' => __( '職種名を入力してください。', 'jobmemory-ai' ) ) );
		}

		$params = array(
			'job_title'              => $job_title,
			'recruitment_background' => sanitize_textarea_field( wp_unslash( $_POST['recruitment_background'] ?? '' ) ),
			'job_description'        => sanitize_textarea_field( wp_unslash( $_POST['job_description'] ?? '' ) ),
			'business_description'   => sanitize_textarea_field( wp_unslash( $_POST['business_description'] ?? '' ) ),
			'company_strengths'      => sanitize_textarea_field( wp_unslash( $_POST['company_strengths'] ?? '' ) ),
			'work_culture'           => sanitize_textarea_field( wp_unslash( $_POST['work_culture'] ?? '' ) ),
			'salary_benefits'        => sanitize_textarea_field( wp_unslash( $_POST['salary_benefits'] ?? '' ) ),
			'ideal_candidate'        => sanitize_textarea_field( wp_unslash( $_POST['ideal_candidate'] ?? '' ) ),
		);

		$client = new JMAI_AI_Client();
		$result = $client->generate( $params );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		wp_send_json_success(
			array(
				'pattern_a' => $result['pattern_a'],
				'pattern_b' => $result['pattern_b'],
				'pattern_c' => $result['pattern_c'],
				'job_title' => $job_title,
			)
		);
	}

	/* ─── AJAX: フィードバック保存 & 再生成 ─── */

	public function ajax_save_feedback(): void {
		check_ajax_referer( 'jmai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'jobmemory-ai' ) ) );
		}

		$feedback         = sanitize_textarea_field( wp_unslash( $_POST['feedback'] ?? '' ) );
		$job_title        = sanitize_text_field( wp_unslash( $_POST['job_title'] ?? '' ) );
		$selected_pattern = sanitize_text_field( wp_unslash( $_POST['selected_pattern'] ?? '' ) );
		$current_content  = sanitize_textarea_field( wp_unslash( $_POST['current_content'] ?? '' ) );

		if ( empty( $feedback ) ) {
			wp_send_json_error( array( 'message' => __( '指摘事項を入力してください。', 'jobmemory-ai' ) ) );
		}

		if ( ! in_array( $selected_pattern, self::ALLOWED_PATTERNS, true ) ) {
			wp_send_json_error( array( 'message' => __( '無効なパターンが選択されました。', 'jobmemory-ai' ) ) );
		}

		$pattern_labels = array(
			'a' => 'スタンダード',
			'b' => '挑戦的',
			'c' => 'カジュアル',
		);
		$label = $pattern_labels[ $selected_pattern ];
		$date  = wp_date( 'Y-m-d H:i' );

		$entry = "\n[{$date}] 職種: {$job_title} / パターン: {$label}\n指摘事項: {$feedback}";

		$memory = new JMAI_Memory();
		$memory->append( $entry );

		$client = new JMAI_AI_Client();
		$result = $client->regenerate_single(
			$current_content,
			$feedback,
			$selected_pattern,
			array( 'job_title' => $job_title )
		);

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		wp_send_json_success(
			array(
				'message'          => __( '求人文を再作成しました。', 'jobmemory-ai' ),
				'regenerated'      => $result['content'],
				'advice'           => $result['advice'],
				'selected_pattern' => $selected_pattern,
			)
		);
	}

	/* ─── AJAX: Simple Job Boardに保存 ─── */

	public function ajax_save_job(): void {
		check_ajax_referer( 'jmai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'jobmemory-ai' ) ) );
		}

		$job_title        = sanitize_text_field( wp_unslash( $_POST['job_title'] ?? '' ) );
		$content          = wp_kses_post( wp_unslash( $_POST['content'] ?? '' ) );
		$selected_pattern = sanitize_text_field( wp_unslash( $_POST['selected_pattern'] ?? '' ) );

		if ( empty( $job_title ) || empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( '保存するデータがありません。', 'jobmemory-ai' ) ) );
		}

		if ( ! in_array( $selected_pattern, self::ALLOWED_PATTERNS, true ) ) {
			wp_send_json_error( array( 'message' => __( '無効なパターンが選択されました。', 'jobmemory-ai' ) ) );
		}

		if ( ! post_type_exists( 'jobpost' ) ) {
			wp_send_json_error( array( 'message' => __( 'Simple Job Boardプラグインが有効になっていません。', 'jobmemory-ai' ) ) );
		}

		$image_ids_raw = sanitize_text_field( wp_unslash( $_POST['image_ids'] ?? '' ) );

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'jobpost',
				'post_title'   => $job_title,
				'post_content' => $content,
				'post_status'  => 'draft',
			)
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( '保存に失敗しました: ', 'jobmemory-ai' ) . $post_id->get_error_message() ) );
		}

		if ( ! empty( $image_ids_raw ) ) {
			$image_ids = array_map( 'absint', explode( ',', $image_ids_raw ) );
			$image_ids = array_filter( $image_ids );

			foreach ( $image_ids as $index => $attachment_id ) {
				if ( 'attachment' !== get_post_type( $attachment_id ) ) {
					continue;
				}

				if ( 0 === $index ) {
					set_post_thumbnail( $post_id, $attachment_id );
				}

				wp_update_post(
					array(
						'ID'          => $attachment_id,
						'post_parent' => $post_id,
					)
				);
			}
		}

		wp_send_json_success(
			array(
				'message'  => __( '下書きとして保存しました。', 'jobmemory-ai' ),
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			)
		);
	}

	/* ─── AJAX: Memoryリセット ─── */

	public function ajax_reset_memory(): void {
		check_ajax_referer( 'jmai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'jobmemory-ai' ) ) );
		}

		$memory = new JMAI_Memory();
		$memory->reset();

		wp_send_json_success( array( 'message' => __( 'Memoryをリセットしました。', 'jobmemory-ai' ) ) );
	}

	/* ─── AJAX: 会社HP情報取得 ─── */

	public function ajax_fetch_company_info(): void {
		check_ajax_referer( 'jmai_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'jobmemory-ai' ) ) );
		}

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );

		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'URLが入力されていません。', 'jobmemory-ai' ) ) );
		}

		$html = $this->fetch_webpage( $url );

		if ( is_wp_error( $html ) ) {
			wp_send_json_error( array( 'message' => $html->get_error_message() ) );
		}

		$text = $this->extract_text_from_html( $html );

		if ( mb_strlen( $text ) < 100 ) {
			wp_send_json_error( array( 'message' => __( 'ページから十分な情報を取得できませんでした。', 'jobmemory-ai' ) ) );
		}

		$client    = new JMAI_AI_Client();
		$extracted = $client->extract_company_info( $text );

		if ( is_wp_error( $extracted ) ) {
			wp_send_json_error( array( 'message' => $extracted->get_error_message() ) );
		}

		$warning = null;
		if ( mb_strlen( $text ) < 500 ) {
			$warning = __( '取得した情報が少なめです。会社概要ページのURLを試すとより詳しい情報が取得できます。', 'jobmemory-ai' );
		}

		wp_send_json_success(
			array(
				'company_strength'     => $extracted['company_strength'] ?? '',
				'business_description' => $extracted['business_description'] ?? '',
				'work_environment'     => $extracted['work_environment'] ?? '',
				'warning'              => $warning,
			)
		);
	}

	private function fetch_webpage( string $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 30,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
				'sslverify'  => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fetch_error', __( 'ページを取得できませんでした: ', 'jobmemory-ai' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error( 'fetch_error', __( 'ページを取得できませんでした（ステータス: ', 'jobmemory-ai' ) . $status_code . '）' );
		}

		return wp_remote_retrieve_body( $response );
	}

	private function extract_text_from_html( string $html ): string {
		$html = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $html );
		$html = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $html );
		$html = preg_replace( '/<nav\b[^>]*>(.*?)<\/nav>/is', '', $html );
		$html = preg_replace( '/<footer\b[^>]*>(.*?)<\/footer>/is', '', $html );

		$text = wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) > 5000 ) {
			$text = mb_substr( $text, 0, 5000 );
		}

		return $text;
	}
}
