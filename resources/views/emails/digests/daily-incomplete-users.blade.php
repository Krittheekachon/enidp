@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Daily Digest</div>

<h1>สรุปประจำวัน: ผู้ใช้งานข้อมูลไม่ครบ</h1>

<p class="mail-lead">มีผู้ใช้งานที่ข้อมูลโครงสร้างยังไม่ครบ {{ count($users) }} รายการ โปรดตรวจสอบและปรับปรุงข้อมูลให้พร้อมใช้งาน</p>

@include('emails.partials.summary-card', [
    'tone' => 'warning',
    'title' => 'ข้อมูลบุคลากรไม่ครบ',
    'count' => count($users),
    'description' => 'ข้อมูลตำแหน่ง หน่วยงาน หรือโครงสร้างยังไม่สมบูรณ์',
    'items' => $users,
    'formatter' => fn ($user) => ($user['name'] ?? 'ไม่ระบุชื่อ').(!empty($user['email']) ? ' ('.$user['email'].')' : ''),
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดแดชบอร์ด'])
@endsection


