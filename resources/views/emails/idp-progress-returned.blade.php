@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">IDP Progress Returned</div>

<h1>ผลความก้าวหน้า IDP ถูกส่งกลับให้แก้ไข</h1>

<p class="mail-lead">เรียน {{ $employee->name }} หัวหน้าได้ตรวจสอบผลความก้าวหน้าและส่งรายการกลับให้แก้ไขหรือเพิ่มเติมข้อมูล</p>

@include('emails.partials.summary-card', [
    'tone' => 'danger',
    'title' => 'รายการที่ต้องแก้ไข',
    'count' => 1,
    'description' => 'สมรรถนะ: '.$competencyName,
])

<div class="email-alert"><strong>เหตุผลที่ส่งกลับ</strong><br>{{ $comment }}</div>

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'แก้ไขผลความก้าวหน้า IDP'])
@endsection
