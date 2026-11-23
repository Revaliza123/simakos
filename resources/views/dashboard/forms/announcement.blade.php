@extends('components.form-page')

@section('form-content')
    @php($editing = $announcement->exists)
    <div class="panel-heading"><div><h2>{{ $editing ? 'Ubah Pengumuman' : 'Buat Pengumuman' }}</h2><p>Pengumuman yang dipublikasikan tampil di beranda seluruh penghuni.</p></div></div>
    <form method="POST" action="{{ $editing ? route('announcements.update', $announcement) : route('announcements.store') }}">@csrf
        @if ($editing) @method('PUT') @endif
        <div class="form-grid">
            <label style="grid-column:1/-1">Judul pengumuman<input name="title" value="{{ old('title', $announcement->title) }}" required></label>
            <label style="grid-column:1/-1">Isi pengumuman<textarea name="content" rows="7" required>{{ old('content', $announcement->content) }}</textarea></label>
            <label class="check-label" style="grid-column:1/-1"><input type="hidden" name="publish" value="0"><input name="publish" type="checkbox" value="1" @checked(old('publish', $announcement->published_at !== null))> Publikasikan ke portal penghuni</label>
        </div>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('announcements') }}">Batal</a><button class="button button-primary" type="submit">{{ $editing ? 'Simpan Perubahan' : 'Simpan' }}</button></div>
    </form>
@endsection
