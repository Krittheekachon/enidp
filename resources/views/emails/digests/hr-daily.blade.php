@extends('emails.layouts.app')

@section('content')
@php
    $total = collect($sections)->sum(fn ($items) => count($items));
@endphp

<div class="mail-eyebrow">Daily Digest</div>

<h1>สรุปประจำวัน: รายการที่ต้องดำเนินการ</h1>

<p class="mail-lead">มีรายการที่เกี่ยวข้องกับ HR {{ $total }} รายการ โปรดเปิดแดชบอร์ดเพื่อตรวจสอบและดำเนินการต่อ</p>

<div class="summary-grid">
@if(!empty($sections['pendingAssessmentUsers']))
@include('emails.partials.summary-card', [
    'tone' => 'warning',
    'title' => 'บุคลากรยังไม่ประเมินตนเอง',
    'count' => count($sections['pendingAssessmentUsers']),
    'description' => 'ยังไม่พบการส่งแบบประเมินตนเองในระบบ',
    'items' => $sections['pendingAssessmentUsers'],
    'formatter' => fn ($user) => ($user['name'] ?? 'ไม่ระบุชื่อ').(!empty($user['email']) ? ' ('.$user['email'].')' : ''),
])
@endif

@if(!empty($sections['unmappedPositionUsers']))
@include('emails.partials.summary-card', [
    'tone' => 'danger',
    'title' => 'Position ยังไม่มี Competency Mapping',
    'count' => count($sections['unmappedPositionUsers']),
    'description' => 'มีบุคลากรที่ตำแหน่งยังไม่ได้กำหนดสมรรถนะประจำตำแหน่ง',
    'items' => $sections['unmappedPositionUsers'],
    'formatter' => fn ($user) => ($user['name'] ?? 'ไม่ระบุชื่อ').(!empty($user['email']) ? ' ('.$user['email'].')' : '').(!empty($user['position']) ? ' - '.$user['position'] : ''),
])
@endif

@if(!empty($sections['unmappedPositions']))
@include('emails.partials.summary-card', [
    'tone' => 'neutral',
    'title' => 'Position ที่ยังไม่ได้ Mapping',
    'count' => count($sections['unmappedPositions']),
    'description' => 'ตำแหน่งหรือกลุ่มงานที่ยังไม่ผูกสมรรถนะ',
    'items' => $sections['unmappedPositions'],
    'formatter' => fn ($position) => $position,
])
@endif
</div>

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดแดชบอร์ด'])
@endsection


