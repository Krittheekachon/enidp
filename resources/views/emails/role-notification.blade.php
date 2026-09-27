@extends('emails.layouts.app')

@section('content')
<div class="mail-eyebrow">Notification</div>

<h1>{{ $title }}</h1>

<p class="mail-lead">{{ $body }}</p>

@include('emails.partials.summary-card', [
    'tone' => 'neutral',
    'title' => $title,
    'count' => 1,
    'description' => 'มีรายการที่ต้องตรวจสอบในระบบ',
])

@include('emails.components.button', ['url' => $actionUrl, 'label' => $buttonText])
@endsection


