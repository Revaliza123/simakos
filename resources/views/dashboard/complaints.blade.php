@extends('layouts.app')

@section('content')
    @include('components.page-heading')
    @include('components.form-errors')
    <div class="toolbar">
        <div class="tabs">
            <a class="tab {{ $status === null ? 'active' : '' }}" href="{{ route('complaints') }}">Semua <b>{{ $counts->sum() }}</b></a>
            @foreach (\App\Models\Complaint::STATUS_LABELS as $key => $label)
                <a class="tab {{ $status === $key ? 'active' : '' }}" href="{{ route('complaints', ['status' => $key]) }}">{{ $label }} <b>{{ $counts[$key] ?? 0 }}</b></a>
            @endforeach
        </div>
    </div>
    <section class="ticket-grid">
        @forelse ($complaints as $complaint)
            <article class="ticket-card" data-search="{{ strtolower($complaint->title.' '.($complaint->rental?->user?->name ?? '').' '.($complaint->rental?->room?->room_number ?? '')) }}">
                <div class="ticket-top"><span class="status-badge {{ $complaint->statusTone() }}"><i></i>{{ $complaint->statusLabel() }}</span><span class="ticket-id">{{ $complaint->ticketNumber() }}</span></div>
                <h3>{{ $complaint->title }}</h3>
                <p>{{ $complaint->description }}</p>
                @if ($complaint->photo)
                    <a class="ticket-photo" href="{{ asset('storage/'.$complaint->photo) }}" target="_blank" rel="noopener"><img src="{{ asset('storage/'.$complaint->photo) }}" alt="Foto bukti {{ $complaint->title }}" loading="lazy"></a>
                @endif
                <div class="ticket-info"><span class="material-symbols-outlined">person</span>{{ $complaint->rental?->user?->name ?? '-' }} · Kamar {{ $complaint->rental?->room?->room_number ?? '-' }}<time>{{ $complaint->created_at?->diffForHumans() }}</time></div>
                @if ($complaint->response)
                    <div class="ticket-response"><b>Tanggapan admin</b>{{ $complaint->response }}<small>{{ $complaint->responded_at?->translatedFormat('d M Y H:i') }}</small></div>
                @endif
                <form class="ticket-form" method="POST" action="{{ route('complaints.update', $complaint) }}">
                    @csrf @method('PATCH')
                    <label>Status penanganan
                        <select name="status">
                            @foreach (\App\Models\Complaint::STATUS_LABELS as $key => $label)
                                <option value="{{ $key }}" @selected($complaint->status === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Tanggapan / catatan tindak lanjut<textarea name="response" rows="2" maxlength="2000" placeholder="mis. Teknisi menuju lokasi">{{ $complaint->response }}</textarea></label>
                    <button class="button button-primary button-full" type="submit">Perbarui Tiket</button>
                </form>
            </article>
        @empty
            <div class="panel settings-form"><h2>Belum ada keluhan</h2><p class="empty-note">Keluhan yang dikirim penghuni melalui portal akan tampil di sini.</p></div>
        @endforelse
    </section>
@endsection
