<?php

return [
    'booking_details' => '予約番号 :reference · :customer · :vehicle · :pickup → :return · 合計 :total',
    'new_booking' => [
        'subject' => '新しい予約リクエスト :reference',
        'intro' => 'お客様から予約リクエストが届きました。車両は :expires 時間保留されます。それまでに確定またはキャンセルしてください。',
        'action' => '予約を確認',
    ],
    'new_handover' => [
        'subject' => 'お客様がサポートを必要としています',
        'intro' => 'AIレンタルエージェントが会話をスタッフに引き継ぎました。',
        'reason' => '理由: :reason',
        'action' => '会話を開く',
    ],
    'customer_cancelled' => [
        'subject' => '予約 :reference がお客様によりキャンセルされました',
        'intro' => ':customer 様が予約 :reference をキャンセルしました。車両は再び利用可能です。',
    ],
    'booking_status' => [
        'greeting' => ':name 様',
        'subject' => [
            'pending' => 'リクエスト :reference を受け付けました',
            'confirmed' => 'ご予約 :reference が確定しました',
            'cancelled' => 'ご予約 :reference はキャンセルされました',
            'expired' => 'リクエスト :reference は期限切れになりました',
        ],
        'body' => [
            'pending' => ':rental をお選びいただきありがとうございます。予約リクエストを受け付けました。:hours 時間以内にスタッフが確認してご連絡します。',
            'confirmed' => ':rental がご予約を確定しました。お会いできるのを楽しみにしています。',
            'cancelled' => ':rental でのご予約はキャンセルされました。',
            'expired' => '期限内に確認できなかったため、リクエストは期限切れとなり車両は解放されました。お手数ですが、あらためてお申し込みください。',
        ],
        'contact' => 'ご不明な点は :phone までご連絡ください。',
    ],
];
