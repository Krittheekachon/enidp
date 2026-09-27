@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">IDP Review</div>

<h1>มีแผน IDP รอการอนุมัติ</h1>

<p class="mail-lead">{{ $employee->name }} ส่งแผนพัฒนารายบุคคลเข้าสู่ขั้นตอนการอนุมัติแล้ว</p>

@include('emails.partials.summary-card', [
    'tone' => 'primary',
    'title' => 'รายการที่รอพิจารณา',
    'count' => 1,
    'description' => 'สมรรถนะ: '.$competencyName,
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดรายการอนุมัติ IDP'])
@endsection
