<?php
/**
 * 地域別季節イベントデータ
 * 
 * 各地域の季節ごとのイベント・見どころを定義
 * 
 * @package HRS
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(

    // ===== 北海道 =====
    '北海道' => array(
        'spring' => array(
            array('name' => '五稜郭の桜', 'description' => '4月下旬〜5月上旬'),
            array('name' => '芝桜（滝上・東藻琴）', 'description' => '5月中旬〜6月上旬'),
            array('name' => 'チューリップフェア（上湧別）', 'description' => '5月'),
        ),
        'summer' => array(
            array('name' => '富良野ラベンダー', 'description' => '7月上旬〜下旬'),
            array('name' => 'YOSAKOIソーラン祭り', 'description' => '6月'),
            array('name' => '北海道神宮例祭', 'description' => '6月'),
            array('name' => '大通ビアガーデン', 'description' => '7月〜8月'),
        ),
        'autumn' => array(
            array('name' => '大雪山紅葉', 'description' => '9月上旬〜中旬（日本一早い）'),
            array('name' => '定山渓紅葉', 'description' => '10月上旬〜中旬'),
            array('name' => '秋鮭・いくら', 'description' => '9月〜11月'),
        ),
        'winter' => array(
            array('name' => 'さっぽろ雪まつり', 'description' => '2月上旬'),
            array('name' => '小樽雪あかりの路', 'description' => '2月'),
            array('name' => '流氷観光（知床・網走）', 'description' => '1月下旬〜3月'),
            array('name' => 'スキー・スノーボード', 'description' => '12月〜4月'),
        ),
    ),

    // ===== 東北 =====
    '青森' => array(
        'spring' => array(
            array('name' => '弘前さくらまつり', 'description' => '4月下旬〜5月上旬'),
        ),
        'summer' => array(
            array('name' => '青森ねぶた祭', 'description' => '8月2日〜7日'),
            array('name' => '弘前ねぷたまつり', 'description' => '8月1日〜7日'),
        ),
        'autumn' => array(
            array('name' => '奥入瀬渓流紅葉', 'description' => '10月中旬〜下旬'),
            array('name' => 'りんご狩り', 'description' => '9月〜11月'),
        ),
        'winter' => array(
            array('name' => '十和田湖冬物語', 'description' => '2月'),
            array('name' => '酸ヶ湯温泉の雪', 'description' => '豪雪で有名'),
        ),
    ),

    '宮城' => array(
        'spring' => array(
            array('name' => '白石川堤一目千本桜', 'description' => '4月上旬〜中旬'),
        ),
        'summer' => array(
            array('name' => '仙台七夕まつり', 'description' => '8月6日〜8日'),
            array('name' => '松島灯籠流し花火大会', 'description' => '8月'),
        ),
        'autumn' => array(
            array('name' => '鳴子峡紅葉', 'description' => '10月下旬〜11月上旬'),
            array('name' => '松島紅葉', 'description' => '11月'),
        ),
        'winter' => array(
            array('name' => 'SENDAI光のページェント', 'description' => '12月'),
            array('name' => '牡蠣', 'description' => '冬の三陸牡蠣'),
        ),
    ),

    // ===== 関東 =====
    '茨城' => array(
        'spring' => array(
            array('name' => '偕楽園梅まつり', 'description' => '2月下旬〜3月下旬'),
            array('name' => 'ひたち海浜公園ネモフィラ', 'description' => '4月中旬〜5月上旬'),
            array('name' => '笠間つつじまつり', 'description' => '4月中旬〜5月上旬'),
        ),
        'summer' => array(
            array('name' => '大洗海水浴', 'description' => '7月〜8月'),
            array('name' => '土浦全国花火競技大会', 'description' => '10月（夏に準備）'),
            array('name' => 'ひたち海浜公園コキア', 'description' => '夏は緑のコキア'),
        ),
        'autumn' => array(
            array('name' => 'ひたち海浜公園コキア紅葉', 'description' => '10月中旬〜下旬'),
            array('name' => '袋田の滝紅葉', 'description' => '11月上旬〜中旬'),
            array('name' => 'あんこう鍋解禁', 'description' => '11月〜'),
            array('name' => '笠間菊まつり', 'description' => '10月中旬〜11月下旬'),
        ),
        'winter' => array(
            array('name' => 'あんこう鍋', 'description' => '11月〜3月が旬'),
            array('name' => '袋田の滝氷瀑', 'description' => '1月〜2月'),
            array('name' => '大洗あんこう祭り', 'description' => '11月'),
        ),
    ),

    '栃木' => array(
        'spring' => array(
            array('name' => 'あしかがフラワーパーク藤', 'description' => '4月中旬〜5月中旬'),
            array('name' => '日光の桜', 'description' => '4月下旬〜5月上旬'),
        ),
        'summer' => array(
            array('name' => '那須高原避暑', 'description' => '涼しい高原リゾート'),
            array('name' => '鬼怒川温泉', 'description' => '夏も人気の温泉地'),
        ),
        'autumn' => array(
            array('name' => '日光紅葉', 'description' => '10月上旬〜11月上旬'),
            array('name' => 'いろは坂紅葉', 'description' => '10月中旬〜下旬'),
            array('name' => '那須高原紅葉', 'description' => '10月'),
        ),
        'winter' => array(
            array('name' => '湯西川温泉かまくら祭', 'description' => '1月下旬〜3月上旬'),
            array('name' => '日光イルミネーション', 'description' => '11月〜2月'),
        ),
    ),

    '東京' => array(
        'spring' => array(
            array('name' => '上野公園の桜', 'description' => '3月下旬〜4月上旬'),
            array('name' => '目黒川の桜', 'description' => '3月下旬〜4月上旬'),
            array('name' => '千鳥ヶ淵の桜', 'description' => '3月下旬〜4月上旬'),
        ),
        'summer' => array(
            array('name' => '隅田川花火大会', 'description' => '7月最終土曜'),
            array('name' => '神宮外苑花火大会', 'description' => '8月'),
            array('name' => '高尾山ビアマウント', 'description' => '6月〜10月'),
        ),
        'autumn' => array(
            array('name' => '明治神宮外苑いちょう並木', 'description' => '11月中旬〜12月上旬'),
            array('name' => '高尾山紅葉', 'description' => '11月中旬〜下旬'),
        ),
        'winter' => array(
            array('name' => '表参道イルミネーション', 'description' => '12月'),
            array('name' => '東京ミッドタウンイルミネーション', 'description' => '11月〜12月'),
            array('name' => '初詣（明治神宮・浅草寺）', 'description' => '1月'),
        ),
    ),

    '神奈川' => array(
        'spring' => array(
            array('name' => '鎌倉の桜', 'description' => '3月下旬〜4月上旬'),
            array('name' => '横浜の桜', 'description' => '大岡川など'),
        ),
        'summer' => array(
            array('name' => '湘南海水浴', 'description' => '7月〜8月'),
            array('name' => '鎌倉あじさい', 'description' => '6月'),
            array('name' => '横浜開港祭', 'description' => '6月'),
        ),
        'autumn' => array(
            array('name' => '箱根紅葉', 'description' => '11月上旬〜下旬'),
            array('name' => '鎌倉紅葉', 'description' => '11月下旬〜12月上旬'),
        ),
        'winter' => array(
            array('name' => '箱根駅伝', 'description' => '1月2日〜3日'),
            array('name' => '横浜イルミネーション', 'description' => '11月〜2月'),
        ),
    ),

    // ===== 中部 =====
    '静岡' => array(
        'spring' => array(
            array('name' => '河津桜まつり', 'description' => '2月上旬〜3月上旬（早咲き）'),
            array('name' => '富士芝桜まつり', 'description' => '4月中旬〜5月下旬'),
        ),
        'summer' => array(
            array('name' => '熱海海上花火大会', 'description' => '年間通じて開催'),
            array('name' => '伊豆海水浴', 'description' => '7月〜8月'),
        ),
        'autumn' => array(
            array('name' => '修善寺紅葉', 'description' => '11月中旬〜12月上旬'),
            array('name' => '寸又峡紅葉', 'description' => '11月'),
        ),
        'winter' => array(
            array('name' => '伊豆の温泉', 'description' => '冬の温泉旅行に最適'),
            array('name' => '初日の出（伊豆）', 'description' => '1月1日'),
        ),
    ),

    '長野' => array(
        'spring' => array(
            array('name' => '高遠城址公園の桜', 'description' => '4月上旬〜中旬'),
            array('name' => '上田城千本桜まつり', 'description' => '4月'),
        ),
        'summer' => array(
            array('name' => '上高地', 'description' => '避暑地として人気'),
            array('name' => '軽井沢', 'description' => '避暑リゾート'),
            array('name' => '諏訪湖花火大会', 'description' => '8月15日'),
        ),
        'autumn' => array(
            array('name' => '上高地紅葉', 'description' => '10月上旬〜中旬'),
            array('name' => '志賀高原紅葉', 'description' => '10月'),
            array('name' => '松茸', 'description' => '秋の味覚'),
        ),
        'winter' => array(
            array('name' => '白馬・志賀高原スキー', 'description' => '12月〜4月'),
            array('name' => '地獄谷野猿公苑', 'description' => '温泉に入る猿'),
            array('name' => '野沢温泉', 'description' => '冬の温泉とスキー'),
        ),
    ),

    // ===== 関西 =====
    '京都' => array(
        'spring' => array(
            array('name' => '清水寺の桜', 'description' => '3月下旬〜4月上旬'),
            array('name' => '哲学の道の桜', 'description' => '4月上旬'),
            array('name' => '嵐山の桜', 'description' => '3月下旬〜4月中旬'),
        ),
        'summer' => array(
            array('name' => '祇園祭', 'description' => '7月1日〜31日'),
            array('name' => '五山送り火', 'description' => '8月16日'),
            array('name' => '鴨川納涼床', 'description' => '5月〜9月'),
        ),
        'autumn' => array(
            array('name' => '嵐山紅葉', 'description' => '11月中旬〜12月上旬'),
            array('name' => '東福寺紅葉', 'description' => '11月中旬〜下旬'),
            array('name' => '清水寺紅葉ライトアップ', 'description' => '11月'),
        ),
        'winter' => array(
            array('name' => '嵐山花灯路', 'description' => '12月'),
            array('name' => '初詣（伏見稲荷・八坂神社）', 'description' => '1月'),
        ),
    ),

    '大阪' => array(
        'spring' => array(
            array('name' => '大阪城公園の桜', 'description' => '3月下旬〜4月上旬'),
            array('name' => '造幣局桜の通り抜け', 'description' => '4月中旬'),
        ),
        'summer' => array(
            array('name' => '天神祭', 'description' => '7月24日〜25日'),
            array('name' => 'なにわ淀川花火大会', 'description' => '8月'),
        ),
        'autumn' => array(
            array('name' => '箕面の滝紅葉', 'description' => '11月中旬〜12月上旬'),
        ),
        'winter' => array(
            array('name' => '御堂筋イルミネーション', 'description' => '11月〜12月'),
            array('name' => 'てっちり（ふぐ鍋）', 'description' => '冬の味覚'),
        ),
    ),

    // ===== 中国・四国 =====
    '広島' => array(
        'spring' => array(
            array('name' => '宮島の桜', 'description' => '4月上旬'),
            array('name' => '尾道の桜', 'description' => '千光寺公園'),
        ),
        'summer' => array(
            array('name' => '宮島水中花火大会', 'description' => '8月'),
            array('name' => 'とうかさん大祭', 'description' => '6月'),
        ),
        'autumn' => array(
            array('name' => '宮島紅葉', 'description' => '11月中旬〜下旬'),
            array('name' => '牡蠣', 'description' => '10月〜3月'),
        ),
        'winter' => array(
            array('name' => '広島牡蠣', 'description' => '冬が旬'),
        ),
    ),

    '香川' => array(
        'spring' => array(
            array('name' => '栗林公園の桜', 'description' => '4月上旬'),
        ),
        'summer' => array(
            array('name' => '瀬戸内国際芸術祭', 'description' => '3年に1度'),
        ),
        'autumn' => array(
            array('name' => '栗林公園紅葉ライトアップ', 'description' => '11月'),
        ),
        'winter' => array(
            array('name' => '讃岐うどん巡り', 'description' => '年中人気'),
        ),
    ),

    // ===== 九州 =====
    '福岡' => array(
        'spring' => array(
            array('name' => '舞鶴公園の桜', 'description' => '3月下旬〜4月上旬'),
        ),
        'summer' => array(
            array('name' => '博多祇園山笠', 'description' => '7月1日〜15日'),
            array('name' => '博多どんたく', 'description' => '5月3日〜4日'),
        ),
        'autumn' => array(
            array('name' => '太宰府天満宮紅葉', 'description' => '11月中旬〜下旬'),
        ),
        'winter' => array(
            array('name' => '博多ラーメン', 'description' => '冬の温かい一杯'),
            array('name' => 'もつ鍋', 'description' => '冬の定番'),
        ),
    ),

    '長崎' => array(
        'spring' => array(
            array('name' => 'ハウステンボスチューリップ', 'description' => '2月〜4月'),
        ),
        'summer' => array(
            array('name' => '長崎ランタンフェスティバル', 'description' => '1月〜2月（旧正月）'),
            array('name' => '精霊流し', 'description' => '8月15日'),
        ),
        'autumn' => array(
            array('name' => '雲仙紅葉', 'description' => '10月下旬〜11月上旬'),
        ),
        'winter' => array(
            array('name' => 'ハウステンボスイルミネーション', 'description' => '10月〜5月'),
            array('name' => '島原そうめん', 'description' => '年中'),
        ),
    ),

    '鹿児島' => array(
        'spring' => array(
            array('name' => '仙巌園の桜', 'description' => '3月下旬〜4月上旬'),
        ),
        'summer' => array(
            array('name' => 'おはら祭', 'description' => '11月（夏の準備）'),
        ),
        'autumn' => array(
            array('name' => '霧島紅葉', 'description' => '10月下旬〜11月中旬'),
        ),
        'winter' => array(
            array('name' => '指宿砂むし温泉', 'description' => '冬でも温かい'),
            array('name' => '黒豚しゃぶしゃぶ', 'description' => '冬の贅沢'),
        ),
    ),

    // ===== 沖縄 =====
    '沖縄' => array(
        'spring' => array(
            array('name' => '桜まつり（今帰仁城）', 'description' => '1月下旬〜2月（日本一早い桜）'),
            array('name' => '海開き', 'description' => '3月〜4月'),
        ),
        'summer' => array(
            array('name' => 'エイサー', 'description' => '旧盆'),
            array('name' => 'ビーチリゾート', 'description' => '夏のメインシーズン'),
            array('name' => 'マリンスポーツ', 'description' => 'ダイビング・シュノーケリング'),
        ),
        'autumn' => array(
            array('name' => '那覇大綱挽', 'description' => '10月'),
            array('name' => '離島巡り', 'description' => '過ごしやすい季節'),
        ),
        'winter' => array(
            array('name' => 'ホエールウォッチング', 'description' => '1月〜3月'),
            array('name' => 'プロ野球キャンプ', 'description' => '2月'),
            array('name' => '温暖な気候', 'description' => '避寒地として人気'),
        ),
    ),
);