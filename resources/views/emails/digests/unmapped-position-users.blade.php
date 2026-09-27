@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">{{ $frequency === 'hourly' ? 'Hourly Digest' : 'Daily Digest' }}</div>

@if($frequency === 'hourly')
<h1>สรุปรายชั่วโมง: ผู้ใช้ใหม่ที่ตำแหน่งยังไม่ผูกสมรรถนะ</h1>

<p class="mail-lead">ในชั่วโมงนี้มีผู้ใช้ใหม่ที่ตำแหน่งยังไม่ได้กำหนดสมรรถนะประจำตำแหน่ง {{ count($users) }} คน</p>
@else
<h1>สรุปประจำวัน: ผู้ใช้ที่ตำแหน่งยังไม่ผูกสมรรถนะ</h1>

<p class="mail-lead">พบผู้ใช้ที่ตำแหน่งยังไม่ได้กำหนดสมรรถนะประจำตำแหน่ง {{ count($users) }} คน โปรดตรวจสอบในแดชบอร์ด</p>
@endif

@include('emails.partials.summary-card', [
    'tone' => 'danger',
    'title' => 'Position ยังไม่มี Competency Mapping',
    'count' => count($users),
    'description' => 'มีบุคลากรที่ตำแหน่งยังไม่ได้กำหนดสมรรถนะประจำตำแหน่ง',
    'items' => $users,
    'formatter' => fn ($user) => ($user['name'] ?? 'ไม่ระบุชื่อ').(!empty($user['email']) ? ' ('.$user['email'].')' : '').(!empty($user['position']) ? ' - '.$user['position'] : ''),
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดแดชบอร์ด'])
@endsection


