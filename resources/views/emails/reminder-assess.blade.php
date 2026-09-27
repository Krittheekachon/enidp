@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Assessment Reminder</div>

<h1>แจ้งเตือนให้ประเมินตนเอง</h1>

<p class="mail-lead">เรียน {{ $employee->name }} HR แจ้งเตือนให้ดำเนินการประเมินตนเองตามรายการสมรรถนะที่ได้รับมอบหมาย</p>

@include('emails.partials.summary-card', [
    'tone' => 'warning',
    'title' => 'รอการประเมินตนเอง',
    'count' => 1,
    'description' => 'โปรดเข้าสู่ระบบเพื่อทำแบบประเมินให้เรียบร้อย',
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เริ่มประเมินตนเอง'])
@endsection


