@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Daily Digest</div>

<h1>สรุปประจำวัน: บุคลากรยังไม่ประเมินตนเอง</h1>

<p class="mail-lead">มีบุคลากรที่ยังไม่ส่งแบบประเมินตนเอง {{ count($users) }} คน โปรดติดตามให้ดำเนินการในระบบ</p>

@include('emails.partials.summary-card', [
    'tone' => 'warning',
    'title' => 'บุคลากรยังไม่ประเมินตนเอง',
    'count' => count($users),
    'description' => 'ยังไม่พบการส่งแบบประเมินตนเองในระบบ',
    'items' => $users,
    'formatter' => fn ($user) => ($user['name'] ?? 'ไม่ระบุชื่อ').(!empty($user['email']) ? ' ('.$user['email'].')' : ''),
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดแดชบอร์ด'])
@endsection


