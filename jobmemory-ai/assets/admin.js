jQuery(document).ready(function ($) {
    var selectedPattern = 'a';
    var generatedData = {};
    var selectedImages = [];

    /* ─── Tab switching ─── */
    $(document).on('click', '.jmai-tab', function () {
        var pattern = $(this).data('pattern');

        $('.jmai-tab').removeClass('active');
        $(this).addClass('active');

        $('.jmai-tab-content').hide();
        $('#pattern_' + pattern).show();

        selectedPattern = pattern;
    });

    /* ─── Generate job descriptions ─── */
    $('#jmai-generate-form').on('submit', function (e) {
        e.preventDefault();

        var jobTitle = $('#job_title').val().trim();
        if (!jobTitle) {
            alert('職種名を入力してください。');
            return;
        }

        var $btn = $('#jmai-generate-btn');
        btnLoading($btn, '生成中...');
        $('#jmai-loading').show();
        $('#jmai-result-area').hide();
        clearNotices();

        $.ajax({
            url: jmai.ajax_url,
            type: 'POST',
            data: {
                action: 'jmai_generate_job',
                nonce: jmai.nonce,
                job_title: jobTitle,
                recruitment_background: $('#recruitment_background').val(),
                job_description: $('#job_description').val(),
                company_strengths: $('#company_strengths').val(),
                work_culture: $('#work_culture').val(),
                salary_benefits: $('#salary_benefits').val(),
                ideal_candidate: $('#ideal_candidate').val()
            },
            success: function (res) {
                $('#jmai-loading').hide();
                btnReset($btn);

                if (res.success) {
                    generatedData = res.data;
                    $('#pattern_a').text(res.data.pattern_a);
                    $('#pattern_b').text(res.data.pattern_b);
                    $('#pattern_c').text(res.data.pattern_c);

                    $('.jmai-tab').removeClass('active');
                    $('.jmai-tab[data-pattern="a"]').addClass('active');
                    $('.jmai-tab-content').hide();
                    $('#pattern_a').show();
                    selectedPattern = 'a';

                    $('#jmai-result-area').show();
                    $('html, body').animate({
                        scrollTop: $('#jmai-result-area').offset().top - 50
                    }, 400);
                } else {
                    showNotice('error', res.data.message);
                }
            },
            error: function () {
                $('#jmai-loading').hide();
                btnReset($btn);
                showNotice('error', '通信エラーが発生しました。もう一度お試しください。');
            }
        });
    });

    /* ─── Save feedback & regenerate ─── */
    $('#jmai-save-feedback-btn').on('click', function () {
        var feedback = $('#jmai-feedback').val().trim();
        if (!feedback) {
            alert('指摘事項を入力してください。');
            return;
        }

        var currentContent = $('#pattern_' + selectedPattern).text();
        var $btn = $(this);
        var $jobBtn = $('#jmai-save-job-btn');
        btnLoading($btn, '再作成中...');
        $jobBtn.prop('disabled', true);
        clearNotices();

        $.ajax({
            url: jmai.ajax_url,
            type: 'POST',
            data: {
                action: 'jmai_save_feedback',
                nonce: jmai.nonce,
                job_title: generatedData.job_title || '',
                selected_pattern: selectedPattern,
                feedback: feedback,
                current_content: currentContent
            },
            success: function (res) {
                btnReset($btn);
                $jobBtn.prop('disabled', false);
                if (res.success) {
                    var pat = res.data.selected_pattern;
                    $('#pattern_' + pat).text(res.data.regenerated);
                    generatedData['pattern_' + pat] = res.data.regenerated;
                    showNotice('success', res.data.message);
                    $('#jmai-feedback').val('');

                    if (res.data.advice) {
                        $('#jmai-advice-content').text(res.data.advice);
                        $('#jmai-advice-area').show();
                    }
                } else {
                    showNotice('error', res.data.message);
                }
            },
            error: function () {
                btnReset($btn);
                $jobBtn.prop('disabled', false);
                showNotice('error', '通信エラーが発生しました。');
            }
        });
    });

    /* ─── Save to Simple Job Board ─── */
    $('#jmai-save-job-btn').on('click', function () {
        var content = $('#pattern_' + selectedPattern).text();
        if (!content) {
            alert('保存する求人文がありません。');
            return;
        }

        var $btn = $(this);
        var $fbBtn = $('#jmai-save-feedback-btn');
        btnLoading($btn, '保存中...');
        $fbBtn.prop('disabled', true);
        clearNotices();

        $.ajax({
            url: jmai.ajax_url,
            type: 'POST',
            data: {
                action: 'jmai_save_job',
                nonce: jmai.nonce,
                job_title: generatedData.job_title || '',
                content: content,
                selected_pattern: selectedPattern,
                image_ids: $('#jmai-image-ids').val()
            },
            success: function (res) {
                btnReset($btn);
                $fbBtn.prop('disabled', false);
                if (res.success) {
                    var msg = res.data.message;
                    if (res.data.edit_url) {
                        msg += ' <a href="' + res.data.edit_url + '" target="_blank">編集画面を開く</a>';
                    }
                    showNotice('success', msg);
                } else {
                    showNotice('error', res.data.message);
                }
            },
            error: function () {
                btnReset($btn);
                $fbBtn.prop('disabled', false);
                showNotice('error', '通信エラーが発生しました。');
            }
        });
    });

    /* ─── Image upload ─── */
    $('#jmai-add-image-btn').on('click', function () {
        var frame = wp.media({
            title: '求人に掲載する画像を選択',
            button: { text: '画像を追加' },
            multiple: true
        });

        frame.on('select', function () {
            var attachments = frame.state().get('selection').toJSON();
            $.each(attachments, function (i, attachment) {
                var exists = selectedImages.some(function (img) {
                    return img.id === attachment.id;
                });
                if (!exists) {
                    selectedImages.push({
                        id: attachment.id,
                        url: attachment.sizes && attachment.sizes.thumbnail
                            ? attachment.sizes.thumbnail.url
                            : attachment.url
                    });
                }
            });
            renderImagePreviews();
        });

        frame.open();
    });

    $(document).on('click', '.jmai-image-remove', function () {
        var removeId = $(this).data('id');
        selectedImages = selectedImages.filter(function (img) {
            return img.id !== removeId;
        });
        renderImagePreviews();
    });

    function renderImagePreviews() {
        var $container = $('#jmai-images-preview');
        $container.empty();

        $.each(selectedImages, function (i, img) {
            var badge = i === 0 ? '<span class="jmai-image-badge">アイキャッチ</span>' : '';
            var html = '<div class="jmai-image-item">' +
                '<img src="' + img.url + '" alt="" />' +
                '<button type="button" class="jmai-image-remove" data-id="' + img.id + '">&times;</button>' +
                badge +
                '</div>';
            $container.append(html);
        });

        var ids = selectedImages.map(function (img) { return img.id; });
        $('#jmai-image-ids').val(ids.join(','));
    }

    /* ─── Reset memory ─── */
    $('#jmai-reset-memory-btn').on('click', function () {
        if (!confirm('Memoryをリセットしますか？フィードバック履歴がすべて削除され、初期状態に戻ります。')) {
            return;
        }

        var $btn = $(this);
        btnLoading($btn, 'リセット中...');

        $.ajax({
            url: jmai.ajax_url,
            type: 'POST',
            data: {
                action: 'jmai_reset_memory',
                nonce: jmai.nonce
            },
            success: function (res) {
                btnReset($btn);
                if (res.success) {
                    alert(res.data.message);
                    location.reload();
                } else {
                    alert(res.data.message);
                }
            },
            error: function () {
                btnReset($btn);
                alert('通信エラーが発生しました。');
            }
        });
    });

    /* ─── Utilities ─── */

    function btnLoading($btn, loadingText) {
        $btn.data('original-text', $btn.text());
        $btn.prop('disabled', true)
            .addClass('jmai-btn-loading')
            .html('<span class="jmai-btn-spinner"></span>' + loadingText);
    }

    function btnReset($btn) {
        $btn.prop('disabled', false)
            .removeClass('jmai-btn-loading')
            .text($btn.data('original-text'));
    }

    function showNotice(type, message) {
        var cssClass = type === 'success' ? 'notice-success' : 'notice-error';
        var html = '<div class="notice ' + cssClass + ' is-dismissible"><p>' + message + '</p></div>';
        $('#jmai-notices').html(html);
        $('html, body').animate({
            scrollTop: $('#jmai-notices').offset().top - 100
        }, 300);
    }

    function clearNotices() {
        $('#jmai-notices').empty();
    }
});
