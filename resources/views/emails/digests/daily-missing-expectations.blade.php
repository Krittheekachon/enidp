@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Daily Digest</div>

<h1>สรุปประจำวัน: ระดับตำแหน่งยังไม่ได้ตั้งค่าความคาดหวัง</h1>

<p class="mail-lead">มีระดับตำแหน่งที่ยังไม่ได้ตั้งค่าความคาดหวัง {{ count($levels) }} รายการ โปรดตรวจสอบก่อนเปิดรอบการประเมิน</p>

@include('emails.partials.summary-card', [
    'tone' => 'danger',
    'title' => 'Expected Level ยังไม่ได้กำหนด',
    'count' => count($levels),
    'description' => 'ระดับตำแหน่งยังไม่มีค่าความคาดหวังสำหรับการประเมิน',
    'items' => $levels,
    'formatter' => fn ($level) => ($level['workline'] ?? 'ไม่ระบุสายงาน').' / '.($level['job_family'] ?? 'ระดับกลางของสายงาน').' / '.($level['level'] ?? 'ไม่ระบุระดับตำแหน่ง'),
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดแดชบอร์ด'])
@endsection


