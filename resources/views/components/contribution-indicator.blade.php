@props([
    'user' => auth()->user(),
    'compact' => false,
])

@php
    $totalPoints = (int) ($user?->total_contributions ?? 0);
    $badgeName = (string) ($user?->tafe_badge ?? 'First-Year');
@endphp

@if ($user)
    <div {{ $attributes->class(['contribution-indicator', 'contribution-indicator--compact' => $compact]) }}
        role="status" aria-live="polite" aria-label="Contribution points and badge status">
        <div class="contribution-indicator__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path
                    d="M12 2.75L14.98 8.76L21.59 9.73L16.79 14.42L17.93 21.02L12 17.9L6.07 21.02L7.21 14.42L2.41 9.73L9.02 8.76L12 2.75Z"
                    stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
            </svg>
        </div>

        @if ($compact)
            <div class="contribution-indicator__compact-stack">
                <div class="contribution-indicator__compact-row">
                    <span class="contribution-indicator__label">Points</span>
                    <span class="contribution-indicator__value">{{ number_format($totalPoints) }}</span>
                </div>
                <div class="contribution-indicator__badge-wrap">
                    <div class="contribution-indicator__badge">{{ $badgeName }}</div>
                </div>
            </div>
        @else
            <div class="contribution-indicator__content">
                <div class="contribution-indicator__label">Contribution Points</div>
                <div class="contribution-indicator__value">{{ number_format($totalPoints) }}</div>
            </div>

            <div class="contribution-indicator__badge-wrap">
                <div class="contribution-indicator__badge-label">TAFE Badge</div>
                <div class="contribution-indicator__badge">{{ $badgeName }}</div>
            </div>
        @endif
    </div>
@endif
