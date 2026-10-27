<article class="stat-card">
    <div class="stat-icon {{ $tone ?? 'blue' }}"><span class="material-symbols-outlined">{{ $icon }}</span></div>
    <div class="stat-label">{{ $label }}</div><strong class="stat-value">{{ $value }}</strong>
    <span class="stat-note {{ ($trendTone ?? '') === 'warning' ? 'warning' : '' }}">{{ $note }}</span>
</article>
