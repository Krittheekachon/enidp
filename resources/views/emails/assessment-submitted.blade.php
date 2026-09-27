@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Assessment Review</div>

<h1>มีผลการประเมินรอการตรวจสอบ</h1>

<p class="mail-lead">{{ $employee->name }} ส่งผลการประเมินสมรรถนะเข้าสู่ขั้นตอนการตรวจสอบแล้ว</p>

@include('emails.partials.summary-card', [
    'tone' => 'primary',
    'title' => 'รายการที่รอพิจารณา',
    'count' => 1,
    'description' => 'สมรรถนะ: '.$competencyName,
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดรายการตรวจสอบ'])
@endsection


