@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">FC Topic Review</div>

<h1>มีหัวข้อ FC รอการอนุมัติ</h1>

<p class="mail-lead">{{ $employee->name }} ส่งหัวข้อ Functional Competency ให้พิจารณาแล้ว</p>

@include('emails.partials.summary-card', [
    'tone' => 'primary',
    'title' => 'หัวข้อที่รออนุมัติ',
    'count' => count($topicNames),
    'description' => count($topicNames) > 0 ? implode(', ', $topicNames) : 'หัวข้อ FC',
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดรายการอนุมัติหัวข้อ FC'])
@endsection
