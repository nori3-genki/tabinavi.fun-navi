/**
 * 手動プロンプト生成 JavaScript
 * 
 * @version 2.0.0-FIX
 * 
 * 変更履歴:
 * - 2.0.0-FIX: HTML要素IDとの不一致を修正
 *   - #prompt-display → #prompt-text (表示/非表示制御)
 *   - #prompt-char-count 削除（HTML側に存在しない）
 */
jQuery(document).ready(function ($) {

    let currentAI = 'chatgpt'; // デフォルトをChatGPTに
    let generatedPrompts = {};

    const aiNames = {
        claude: 'Claude',
        gemini: 'Gemini',
        chatgpt: 'ChatGPT'
    };

    /* =========================
       AI タブ切り替え
    ========================= */
    $('.ai-tab').on('click', function () {
        $('.ai-tab').removeClass('active');
        $(this).addClass('active');

        currentAI = $(this).data('ai');

        if (generatedPrompts[currentAI]) {
            displayPrompt(generatedPrompts[currentAI]);
        } else if ($('#manual-hotel-name').val().trim()) {
            // 既にホテル名が入力されていて、このAI用のプロンプトがまだない場合は生成
            // generatePrompt(); // 自動生成は無効化（ユーザー操作を待つ）
        }
    });

    /* =========================
       プロンプト生成ボタン
    ========================= */
    $('#generate-prompt-btn').on('click', function () {
        generatePrompt();
    });

    function generatePrompt() {

        const hotelName = $('#manual-hotel-name').val().trim();
        const location  = $('#manual-location').val().trim();
        const preset    = $('#manual-preset').val();
        const words     = $('#manual-words').val();

        if (!hotelName) {
            alert('ホテル名を入力してください');
            $('#manual-hotel-name').focus();
            return;
        }

        // スタイルレイヤー（修正: セレクタを調整）
        let layers = [];
        $('.checkbox-group input:checked, .checkbox-label input:checked').each(function () {
            layers.push($(this).val());
        });

        const $btn = $('#generate-prompt-btn');
        const originalText = $btn.html();

        $btn.prop('disabled', true)
            .html('<span class="dashicons dashicons-update spin"></span> 生成中...');

        const aiModels = ['claude', 'gemini', 'chatgpt'];
        let completed = 0;

        generatedPrompts = {};

        // HRS_Generator が定義されているか確認
        if (typeof HRS_Generator === 'undefined') {
            console.error('HRS_Generator is not defined');
            $btn.prop('disabled', false).html(originalText);
            alert('エラー: JavaScript設定が読み込まれていません。ページをリロードしてください。');
            return;
        }

        aiModels.forEach(function (aiModel) {

            $.ajax({
                url: HRS_Generator.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'hrs_generate_manual_prompt',
                    nonce: HRS_Generator.nonce,
                    hotel_name: hotelName,
                    location: location,
                    preset: preset,
                    words: words,
                    ai_model: aiModel,
                    layers: layers
                },

                success: function (response) {
                    if (response.success) {
                        generatedPrompts[aiModel] = response.data.prompt;
                    } else {
                        generatedPrompts[aiModel] =
                            'エラー: ' + (response.data.message || 'プロンプト生成に失敗しました');
                    }
                },

                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    generatedPrompts[aiModel] =
                        'エラー: 通信エラーが発生しました (' + status + ')';
                },

                complete: function () {
                    completed++;

                    if (completed === aiModels.length) {
                        $btn.prop('disabled', false).html(originalText);
                        displayPrompt(generatedPrompts[currentAI]);
                    }
                }
            });
        });
    }

    /* =========================
       プロンプト表示
    ========================= */
    function displayPrompt(prompt) {
        // 空状態を非表示、プロンプトテキストを表示
        $('#prompt-empty-state').hide();
        $('#prompt-text').show().text(prompt);

        // コピーボタンを有効化
        $('#copy-prompt-btn').prop('disabled', false);
    }

    /* =========================
       コピー機能
    ========================= */
    $('#copy-prompt-btn').on('click', function () {
        if (generatedPrompts[currentAI]) {
            copyToClipboard(generatedPrompts[currentAI]);
        }
    });

    function copyToClipboard(text) {

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text)
                .then(showCopySuccess)
                .catch(function() { 
                    fallbackCopy(text);
                });
        } else {
            fallbackCopy(text);
        }
    }

    function fallbackCopy(text) {

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';

        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            showCopySuccess();
        } catch (e) {
            alert('コピーに失敗しました');
        }

        document.body.removeChild(textarea);
    }

    function showCopySuccess() {

        const $btn = $('#copy-prompt-btn');
        const original = $btn.html();

        $btn.addClass('copy-success hrs-button-success')
            .html('<span class="dashicons dashicons-yes"></span> コピーしました！');

        setTimeout(function () {
            $btn.removeClass('copy-success hrs-button-success')
                .html(original);
        }, 2000);
    }

    /* =========================
       初期化
    ========================= */
    // 再生成モード時にホテル名が既に入力されている場合の処理
    // （自動生成はしない - ユーザーがボタンをクリックするのを待つ）
    
    // デバッグ用
    console.log('Manual Generator JS loaded. HRS_Generator:', typeof HRS_Generator !== 'undefined' ? 'defined' : 'undefined');

});