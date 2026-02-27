jQuery(document).ready(function ($) {
    var selectedPattern = 'a';
    var generatedData = {};

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

        $('#jmai-generate-btn').prop('disabled', true);
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
                $('#jmai-generate-btn').prop('disabled', false);

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
                $('#jmai-generate-btn').prop('disabled', false);
                showNotice('error', '通信エラーが発生しました。もう一度お試しください。');
            }
        });
    });

    /* ─── Save feedback ─── */
    $('#jmai-save-feedback-btn').on('click', function () {
        var feedback = $('#jmai-feedback').val().trim();
        if (!feedback) {
            alert('フィードバックを入力してください。');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('保存中...');
        clearNotices();

        $.ajax({
            url: jmai.ajax_url,
            type: 'POST',
            data: {
                action: 'jmai_save_feedback',
                nonce: jmai.nonce,
                job_title: generatedData.job_title || '',
                selected_pattern: selectedPattern,
                feedback: feedback
            },
            success: function (res) {
                $btn.prop('disabled', false).text('フィードバックを保存');
                if (res.success) {
                    showNotice('success', res.data.message);
                    $('#jmai-feedback').val('');
                } else {
                    showNotice('error', res.data.message);
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('フィードバックを保存');
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
        $btn.prop('disabled', true).text('保存中...');
        clearNotices();

        $.ajax({
            url: jmai.ajax_url,
            type: 'POST',
            data: {
                action: 'jmai_save_job',
                nonce: jmai.nonce,
                job_title: generatedData.job_title || '',
                content: content,
                selected_pattern: selectedPattern
            },
            success: function (res) {
                $btn.prop('disabled', false).text('Simple Job Boardに下書き保存');
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
                $btn.prop('disabled', false).text('Simple Job Boardに下書き保存');
                showNotice('error', '通信エラーが発生しました。');
            }
        });
    });

    /* ─── Reset memory ─── */
    $('#jmai-reset-memory-btn').on('click', function () {
        if (!confirm('Memoryをリセットしますか？フィードバック履歴がすべて削除され、初期状態に戻ります。')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('リセット中...');

        $.ajax({
            url: jmai.ajax_url,
            type: 'POST',
            data: {
                action: 'jmai_reset_memory',
                nonce: jmai.nonce
            },
            success: function (res) {
                $btn.prop('disabled', false).text('Memoryをリセット');
                if (res.success) {
                    alert(res.data.message);
                    location.reload();
                } else {
                    alert(res.data.message);
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Memoryをリセット');
                alert('通信エラーが発生しました。');
            }
        });
    });

    /* ─── Utilities ─── */
    function showNotice(type, message) {
        var cssClass = type === 'success' ? 'notice-success' : 'notice-error';
        var html = '<div class="notice ' + cssClass + ' is-dismissible"><p>' + message + '</p></div>';
        $('#jmai-notices').html(html);
    }

    function clearNotices() {
        $('#jmai-notices').empty();
    }
});
