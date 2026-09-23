@extends('layouts.admin')
@section('title','Forms')
@section('content')
<div class="topbar">
    <div>
        <h1>Danh sách Form / Vote</h1>
        <p>Tạo, chỉnh trạng thái và xuất CSV kết quả theo Form. Không xóa Form — dùng trạng thái <strong>closed</strong> để đóng/lưu trữ.</p>
    </div>
    <a class="btn" href="{{ route('admin.forms.create') }}">+ Tạo Form</a>
</div>
<div class="card">
<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Tiêu đề</th>
        <th>Trạng thái</th>
        <th>Thời hạn</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    @foreach($forms as $form)
        <tr>
            <td>{{ $form->id }}</td>
            <td style="font-weight:600">{{ $form->title }}</td>
            <td>
                <span class="badge {{ $form->status === 'closed' ? 'closed' : ($form->status === 'draft' ? 'draft' : '') }}">
                    {{ $form->status }}
                </span>
            </td>
            <td class="muted">
                {{ optional($form->starts_at)->format('d/m/Y') }} –
                @if($form->no_end_date)
                    không kết thúc
                @else
                    {{ optional($form->ends_at)->format('d/m/Y') }}
                @endif
            </td>
            <td>
                <a href="{{ route('admin.forms.edit', $form) }}">Sửa</a>
                ·
                <a href="{{ route('admin.forms.export', $form) }}">Export CSV</a>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
{{ $forms->links() }}
</div>
@endsection
