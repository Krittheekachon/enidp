@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Hourly Digest</div>

<h1>สรุปผู้ใช้งานใหม่</h1>

<p class="mail-lead">มีผู้ใช้งานใหม่รอตรวจสอบ {{ count($users) }} รายการ โปรดตรวจสอบข้อมูลและสิทธิ์การใช้งานในแดชบอร์ด</p>

@include('emails.partials.summary-card', [
    'tone' => 'success',
    'title' => 'ผู้ใช้งานใหม่',
    'count' => count($users),
    'description' => 'รายการผู้ใช้งานที่ถูกเพิ่มเข้าระบบในรอบนี้',
    'items' => $users,
    'formatter' => fn ($user) => ($user['name'] ?? 'ไม่ระบุชื่อ').(!empty($user['email']) ? ' ('.$user['email'].')' : ''),
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดแดชบอร์ด'])
@endsection


