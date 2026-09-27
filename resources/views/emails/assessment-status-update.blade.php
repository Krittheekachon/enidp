@extends('emails.layouts.app')

@section('content')
@php
    $statusDetails = match ($status) {
        'self_submitted' => [
            'title' => 'ส่งแบบประเมินแล้ว',
            'body' => 'ผลการประเมินของคุณอยู่ระหว่างรอการอนุมัติ กรุณาติดตามสถานะในระบบ',
            'button' => 'ดูสถานะการประเมิน',
            'tone' => 'primary',
        ],
        'unit_evaluated' => [
            'title' => 'หัวหน้าหน่วยอนุมัติผลการประเมินแล้ว',
            'body' => 'หัวหน้าหน่วยอนุมัติผลการประเมินของคุณแล้ว ตอนนี้ระบบยังอยู่ระหว่างรอการตรวจสอบจากหัวหน้างานลำดับถัดไป',
            'button' => 'ดูสถานะการประเมิน',
            'tone' => 'primary',
        ],
        'dept_evaluated' => [
            'title' => 'ผลการประเมินผ่านการตรวจสอบแล้ว',
            'body' => 'ผลการประเมินของคุณผ่านการตรวจสอบตามลำดับแล้ว ตอนนี้อยู่ระหว่างรอการยืนยันขั้นถัดไป',
            'button' => 'ดูสถานะการประเมิน',
            'tone' => 'primary',
        ],
        'approved' => [
            'title' => 'ผลการประเมินได้รับการอนุมัติแล้ว',
            'body' => 'ผลการประเมินของคุณได้รับการอนุมัติครบทุกลำดับแล้ว สามารถเริ่มทำแผนพัฒนารายบุคคล (IDP) ได้',
            'button' => 'เริ่มทำแผน IDP',
            'tone' => 'success',
        ],
        'revision_required' => [
            'title' => 'ส่งกลับให้แก้ไข',
            'body' => 'ผลการประเมินของคุณถูกส่งกลับให้แก้ไข กรุณาเข้าสู่ระบบเพื่อตรวจสอบและแก้ไขรายการที่เกี่ยวข้อง',
            'button' => 'แก้ไขแบบประเมิน',
            'tone' => 'danger',
        ],
        default => [
            'title' => 'อัปเดตสถานะผลการประเมิน',
            'body' => 'มีการอัปเดตสถานะผลการประเมินของคุณ กรุณาเข้าสู่ระบบเพื่อตรวจสอบรายละเอียด',
            'button' => 'ดูสถานะการประเมิน',
            'tone' => 'neutral',
        ],
    };
@endphp

<div class="mail-eyebrow">Assessment Status</div>

<h1>{{ $statusDetails['title'] }}</h1>

<p class="mail-lead">เรียน {{ $employee->name }} {{ $statusDetails['body'] }}</p>

@include('emails.partials.summary-card', [
    'tone' => $statusDetails['tone'],
    'title' => $statusDetails['title'],
    'count' => 1,
    'description' => $statusDetails['body'],
])

@if($status === 'revision_required' && !empty($rejectComment))
<div class="email-alert"><strong>Comment จากผู้ประเมิน</strong><br>{{ $rejectComment }}</div>
@endif

@include('emails.components.button', ['url' => $actionUrl, 'label' => $statusDetails['button']])
@endsection


