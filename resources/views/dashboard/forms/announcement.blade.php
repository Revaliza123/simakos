@extends('components.form-page')

@section('form-content')
    <div class="panel-heading"><div><h2>Buat Pengumuman</h2><p>Publikasikan informasi penting untuk seluruh penghuni.</p></div></div>
    <form method="POST" action="{{ route('announcements.store') }}">@csrf
        <div class="form-grid"><label style="grid-column:1/-1">Judul pengumuman<input name="title" value="{{ old('title') }}" required></label><label style="grid-column:1/-1">Isi pengumuman<textarea name="content" rows="7" required style="width:100%;border:1px solid #cbd5e1;border-radius:7px;padding:11px;resize:vertical">{{ old('content') }}</textarea></label></div><div class="form-actions"><a class="button button-secondary" href="{{ route('announcements') }}">Batal</a><button class="button button-primary" type="submit">Publikasikan</button></div>
    </form>
@endsection
