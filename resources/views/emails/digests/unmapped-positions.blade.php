@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Daily Digest</div>

<h1>ตำแหน่งยังไม่ผูกสมรรถนะ</h1>

<p class="mail-lead">มีตำแหน่งหรือกลุ่มงานที่ยังไม่ผูกสมรรถนะ {{ count($positions) }} รายการ โปรดตรวจสอบและผูกสมรรถนะให้ครบถ้วน</p>

@include('emails.partials.summary-card', [
    'tone' => 'neutral',
    'title' => 'Position ที่ยังไม่ได้ Mapping',
    'count' => count($positions),
    'description' => 'ตำแหน่งหรือกลุ่มงานที่ยังไม่ผูกสมรรถนะ',
    'items' => $positions,
    'formatter' => fn ($position) => $position,
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดแดชบอร์ด'])
@endsection


