@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">User Setup</div>

<h1>มีผู้ใช้งานใหม่ข้อมูลไม่ครบ</h1>

<p class="mail-lead">{{ $user->name }} ถูกเพิ่มเข้าสู่ระบบแล้ว แต่ยังมีข้อมูลโครงสร้างตำแหน่งหรือหน่วยงานไม่ครบ</p>

@include('emails.partials.summary-card', [
    'tone' => 'warning',
    'title' => 'ข้อมูลผู้ใช้งานต้องตรวจสอบ',
    'count' => 1,
    'description' => 'โปรดตรวจสอบและปรับปรุงข้อมูลผู้ใช้งานให้พร้อมใช้งาน',
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'ตรวจสอบข้อมูลผู้ใช้งาน'])
@endsection


