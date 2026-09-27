@extends('emails.layouts.app')

@section('content')
@php
    $statusDetails = match ($status) {
        'approved' => [
            'title' => 'หัวข้อ FC ได้รับการอนุมัติแล้ว',
            'body' => 'หัวข้อ Functional Competency ที่คุณเลือกได้รับการอนุมัติแล้ว สามารถเริ่มทำแบบประเมินได้',
            'button' => 'เปิดแบบประเมิน',
            'tone' => 'success',
        ],
        'revision_required' => [
            'title' => 'หัวข้อ FC ถูกส่งกลับให้เลือกใหม่',
            'body' => 'หัวข้อ Functional Competency ที่คุณเลือกถูกส่งกลับ กรุณาเข้าสู่ระบบเพื่อตรวจสอบและเลือกหัวข้อใหม่',
            'button' => 'เลือกหัวข้อ FC ใหม่',
            'tone' => 'danger',
        ],
        default => [
            'title' => 'อัปเดตสถานะหัวข้อ FC',
            'body' => 'มีการอัปเดตสถานะหัวข้อ Functional Competency ของคุณ กรุณาเข้าสู่ระบบเพื่อตรวจสอบรายละเอียด',
            'button' => 'ดูสถานะหัวข้อ FC',
            'tone' => 'neutral',
        ],
    };
@endphp

<div class="mail-eyebrow">FC Topic Status</div>

<h1>{{ $statusDetails['title'] }}</h1>

<p class="mail-lead">เรียน {{ $employee->name }} {{ $statusDetails['body'] }}</p>

@include('emails.partials.summary-card', [
    'tone' => $statusDetails['tone'],
    'title' => $statusDetails['title'],
    'count' => count($topicNames),
    'description' => count($topicNames) > 0 ? implode(', ', $topicNames) : 'หัวข้อ FC',
])

@if($status === 'revision_required' && !empty($comment))
<div class="email-alert"><strong>Comment จากผู้อนุมัติ</strong><br>{{ $comment }}</div>
@endif

@include('emails.components.button', ['url' => $actionUrl, 'label' => $statusDetails['button']])
@endsection
