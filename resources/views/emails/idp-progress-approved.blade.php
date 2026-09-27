@extends('emails.layouts.app')

@section('content')
@php
    $achievementLabel = match ($achievementStatus) {
        'exceeded' => 'สูงกว่าเป้าหมาย',
        'met' => 'บรรลุตามเป้าหมาย',
        'not_met' => 'ยังไม่บรรลุตามเป้าหมาย',
        default => 'ตรวจสอบผลแล้ว',
    };
    $tone = $achievementStatus === 'not_met' ? 'danger' : 'success';
@endphp

<div class="mail-eyebrow">IDP Progress Approved</div>

<h1>ผลความก้าวหน้า IDP ได้รับการอนุมัติแล้ว</h1>

<p class="mail-lead">เรียน {{ $employee->name }} หัวหน้าได้ตรวจสอบและอนุมัติผลการดำเนินงานตามแผนพัฒนารายบุคคลของคุณแล้ว</p>

@include('emails.partials.summary-card', [
    'tone' => $tone,
    'title' => $achievementLabel,
    'count' => 1,
    'description' => 'สมรรถนะ: '.$competencyName,
])

@if(!empty($comment))
<div class="email-alert"><strong>ความคิดเห็นจากผู้อนุมัติ</strong><br>{{ $comment }}</div>
@endif

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'ดูผลความก้าวหน้า IDP'])
@endsection
