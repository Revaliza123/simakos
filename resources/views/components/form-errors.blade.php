@if ($errors->any())
    <div class="flash-message" style="color:#9f1239;background:#fff1f2;border-color:#fecdd3">
        <strong>Periksa kembali data berikut:</strong>
        <ul style="margin:8px 0 0 18px">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
