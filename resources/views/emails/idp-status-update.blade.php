@extends('emails.layouts.app')

@section('content')
@php
    $reviewStep = preg_match('/^review_step_(\d+)$/', $status, $matches) ? (int) $matches[1] : null;
    $statusDetails = match (true) {
        $status === 'approved' => [
            'title' => 'แผน IDP ได้รับการอนุมัติแล้ว',
            'body' => 'แผน IDP ของคุณได้รับการอนุมัติครบทุกลำดับแล้ว สามารถติดตามและอัปเดตความคืบหน้ากิจกรรมได้ในระบบ',
            'button' => 'เปิดแผน IDP',
            'tone' => 'success',
        ],
        $status === 'revision_required' => [
            'title' => 'แผน IDP ถูกส่งกลับให้แก้ไข',
            'body' => 'แผน IDP ของคุณถูกส่งกลับให้แก้ไข กรุณาเข้าสู่ระบบเพื่อตรวจสอบความคิดเห็นและปรับปรุงแผน',
            'button' => 'แก้ไขแผน IDP',
            'tone' => 'danger',
        ],
        $reviewStep !== null => [
            'title' => 'แผน IDP ผ่านการอนุมัติและรอลำดับถัดไป',
            'body' => 'แผน IDP ของคุณผ่านการอนุมัติแล้ว และกำลังรอผู้อนุมัติลำดับที่ '.$reviewStep.' พิจารณาต่อ',
            'button' => 'ดูสถานะแผน IDP',
            'tone' => 'primary',
        ],
        default => [
            'title' => 'อัปเดตสถานะแผน IDP',
            'body' => 'มีการอัปเดตสถานะแผน IDP ของคุณ กรุณาเข้าสู่ระบบเพื่อตรวจสอบรายละเอียด',
            'button' => 'ดูสถานะแผน IDP',
            'tone' => 'neutral',
        ],
    };
@endphp

<div class="mail-eyebrow">IDP Status</div>

<h1>{{ $statusDetails['title'] }}</h1>

<p class="mail-lead">เรียน {{ $employee->name }} {{ $statusDetails['body'] }}</p>

@include('emails.partials.summary-card', [
    'tone' => $statusDetails['tone'],
    'title' => $statusDetails['title'],
    'count' => 1,
    'description' => 'สมรรถนะ: '.$competencyName,
])

@if($status === 'revision_required' && !empty($rejectComment))
<div class="email-alert"><strong>Comment จากผู้อนุมัติ</strong><br>{{ $rejectComment }}</div>
@endif

@include('emails.components.button', ['url' => $actionUrl, 'label' => $statusDetails['button']])
@endsection
