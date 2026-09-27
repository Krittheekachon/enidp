@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">IDP Progress Review</div>

<h1>มีผลความก้าวหน้า IDP รอการตรวจสอบ</h1>

<p class="mail-lead">{{ $employee->name }} ส่งผลการดำเนินงานตามแผนพัฒนารายบุคคลให้หัวหน้าตรวจสอบแล้ว</p>

@include('emails.partials.summary-card', [
    'tone' => 'primary',
    'title' => 'รายการที่รอตรวจสอบ',
    'count' => 1,
    'description' => 'สมรรถนะ: '.$competencyName,
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'ตรวจสอบผลความก้าวหน้า IDP'])
@endsection
