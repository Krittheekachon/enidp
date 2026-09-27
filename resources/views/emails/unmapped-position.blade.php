@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Competency Mapping</div>

<h1>มีตำแหน่งที่ยังไม่ผูกสมรรถนะ</h1>

<p class="mail-lead">ตำแหน่งหรือกลุ่มงาน "{{ $positionName }}" ยังไม่มีการกำหนดสมรรถนะ โปรดตรวจสอบและผูกสมรรถนะให้ครบถ้วน</p>

@include('emails.partials.summary-card', [
    'tone' => 'danger',
    'title' => 'ตำแหน่งยังไม่ผูกสมรรถนะ',
    'count' => 1,
    'description' => $positionName,
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => 'เปิดหน้ากำหนดสมรรถนะ'])
@endsection


