<?php

return [
    'invalid_credentials' => 'メールアドレスまたはパスワードが正しくありません。',
    'rental_switched' => ':rental の管理に切り替えました。',
    'booking_updated' => '予約 :reference を「:status」に更新しました。',
    'booking_unavailable' => '予約 :reference は確定できません。その日程では車両が空いていません。',
    'booking_statuses' => ['pending' => '保留中', 'confirmed' => '確定', 'cancelled' => 'キャンセル', 'expired' => '期限切れ'],
    'knowledge_created' => 'ナレッジ項目「:title」を作成しました。',
    'knowledge_updated' => 'ナレッジ項目「:title」を更新しました。',
    'knowledge_deleted' => 'ナレッジ項目を削除しました。',
    'vehicle_created' => '車両「:name」を作成しました。',
    'vehicle_updated' => '車両「:name」を更新しました。',
    'vehicle_deleted' => '車両を削除しました。',
    'handover_replied' => 'お客様に返信を送信しました。',
    'llm_down' => 'AIレンタルエージェントが :time にお客様へ回答できず、その後も復旧していません。言語モデルのサーバーを確認してください。その間、お客様にはスタッフへの連絡をご案内しています。',
    'inventory_saved' => ':count 日分の在庫を保存しました。',
    'inventory_range_too_long' => '日付の範囲は :days 日以内にしてください。',
    'handover_resolved' => 'ハンドオーバーを完了しました。AIレンタルエージェントが会話に戻ります。',
];
