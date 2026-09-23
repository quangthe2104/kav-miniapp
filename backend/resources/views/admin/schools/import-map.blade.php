@extends('layouts.admin')
@section('title','Map cột import')
@section('content')
<div class="topbar">
    <div>
        <h1>Map cột import</h1>
        <p>Bước 2/3 — File <strong>{{ $originalName }}</strong> · {{ $totalRows }} dòng dữ liệu. Chọn cột tương ứng rồi validate.</p>
    </div>
    <form method="POST" action="{{ route('admin.schools.import.cancel') }}">
        @csrf
        <button class="btn ghost" type="submit">Hủy</button>
    </form>
</div>

<div class="card" style="margin-bottom:1rem">
    <h2 style="margin:0 0 .75rem;font-size:1.05rem;color:var(--brand)">Preview (5 dòng đầu)</h2>
    <div style="overflow-x:auto">
        <table>
            <thead>
            <tr>
                @foreach($headers as $h)
                    <th>{{ $h }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($preview as $row)
                <tr>
                    @foreach($headers as $i => $h)
                        <td>{{ $row[$i] ?? '' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2 style="margin:0 0 .75rem;font-size:1.05rem;color:var(--brand)">Ánh xạ cột</h2>
    <p class="muted" style="margin:0 0 1rem;font-size:.9rem">
        Bắt buộc: <code>external_id</code>, <code>name</code>, và ít nhất một trong <code>ward_code</code> / <code>ward_name</code>.
    </p>
    <form method="POST" action="{{ route('admin.schools.import.validate') }}">
        @csrf
        <div class="grid grid-2">
            @foreach($fields as $key => $label)
                <div>
                    <label for="map_{{ $key }}">{{ $label }}</label>
                    <select name="map[{{ $key }}]" id="map_{{ $key }}">
                        <option value="">— Không map —</option>
                        @foreach($headers as $i => $h)
                            <option value="{{ $i }}" @selected((string) old("map.$key", $mapping[$key] ?? '') === (string) $i)>
                                {{ $h }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
        <div style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap">
            <button class="btn secondary" type="submit">Validate dòng</button>
            <a class="btn ghost" href="{{ route('admin.schools.import.create') }}">Upload file khác</a>
        </div>
    </form>
</div>
@endsection
