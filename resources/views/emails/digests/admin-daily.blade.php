@extends('emails.layouts.app')

@section('content')
@php
    $total = collect($sections)->sum(fn ($items) => count($items));
@endphp

<div class="mail-eyebrow">Daily Digest</div>

<h1>สรุปประจำวัน: รายการที่ต้องดำเนินการ</h1>

<p class="mail-lead">มีรายการที่เกี่ยวข้องกับผู้ดูแลระบบ {{ $total }} รายการ โปรดเปิดแดชบอร์ดเพื่อตรวจสอบรายละเอียดและดำเนินการต่อ</p>

<div class="summary-grid">
@if(!empty($sections['incompleteUsers']))
@include('emails.partials.summary-card', [
    'tone' => 'warning',
    'title' => 'ข้อมูลบุคลากรไม่ครบ',
    'count' => count($sections['incompleteUsers']),
    'description' => 'ข้อมูลโครงสร้างตำแหน่งหรือหน่วยงานยังไม่ครบ',
    'items' => $sections['incompleteUsers'],
    'formatter' => fn ($user) => ($user['name'] ?? 'ไม่ระบุชื่อ').(!empty($user['email']) ? ' ('.$user['email'].')' : ''),
])
@endif

@if(!empty($sections['missingExpectations']))
@include('emails.partials.summary-card', [
    'tone' => 'danger',
    'title' => 'Expected Level ยังไม่ได้กำหนด',
    'count' => count($sections['missingExpectations']),
    'description' => 'ระดับตำแหน่งยังไม่มีค่าความคาดหวังสำหรับการประเมิน',
    'items' => $sections['missingExpectations'],
    'formatter' => fn ($level) => ($level['workline'] ?? 'ไม่ระบุสายงาน').' / '.($level['job_family'] ?? 'ระดับกลางของสายงาน').' / '.($level['level'] ?? 'ไม่ระบุระดับตำแหน่ง'),
])
@endif
</div>

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดแดชบอร์ด'])
@endsection


