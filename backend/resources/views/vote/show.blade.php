@extends('layouts.plain')
@section('title','Vote')
@section('nav')
    <span class="muted">Phiếu phụ huynh</span>
@endsection
@section('content')
<h1>{{ $classForm->form->title }}</h1>
<div class="card">
    <p><strong>Trường:</strong> {{ $classForm->classProfile->school->name }}</p>
    <p><strong>Lớp:</strong> {{ $classForm->classProfile->class_name }}</p>
    <p><strong>Tiến độ:</strong> {{ $coverage }} / {{ $classForm->classProfile->quota }} ({{ $classForm->status }})</p>
    <p><strong>Tạm thời:</strong> Đồng ý {{ $stats['agree'] }} · Không {{ $stats['disagree'] }}</p>
    <div style="white-space:pre-wrap">{{ $classForm->form->content }}</div>
</div>

@if(!$formActive)
    <div class="err">
        @if($classForm->status === 'closed')
            Vote lớp này đang <strong>đóng</strong>. Link vẫn dùng được khi giáo viên mở lại — hiện chưa nhận phiếu mới.
        @else
            Form đã hết hạn hoặc không còn active. Bạn không thể gửi / đổi ý.
        @endif
    </div>
@else
<div class="card">
    <p class="muted">
        Nếu bạn đã vote trước đó, gửi lại form với cùng Zalo User ID để <strong>đổi ý</strong> (khi form còn mở).
        @unless($acceptingNew)
            Lớp đã đủ sĩ số — chỉ còn cho phép đổi ý phiếu đã gửi, không nhận phiếu mới.
        @endunless
    </p>
    <form method="POST" action="{{ route('vote.store', $token) }}">
        @csrf
        <label>Zalo User ID</label>
        <input name="zalo_user_id" required value="{{ old('zalo_user_id') }}" placeholder="vd: parent-zalo-001">
        <label>SĐT (tuỳ chọn)</label>
        <input name="phone" value="{{ old('phone') }}">
        <label>Lựa chọn</label>
        <select name="choice" required>
            @foreach(($options['choices'] ?? []) as $opt)
                <option value="{{ $opt['value'] }}" @selected(old('choice') === $opt['value'])>{{ $opt['label'] }}</option>
            @endforeach
        </select>
        <p style="margin-top:1rem"><button class="btn" type="submit">Gửi / Cập nhật phiếu</button></p>
    </form>
</div>
@endif
@endsection
