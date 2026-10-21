<script data-system-theme="{{ $mode }}" @if (filled($nonce = \Illuminate\Support\Facades\Vite::cspNonce())) nonce="{{ $nonce }}" @endif>
    (() => {
        const mode = @js($mode)
        localStorage.setItem('filament:theme:mode', mode)
        window.theme = mode
        document.documentElement.classList.toggle('dark', mode === 'dark' || (
            mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches
        ))
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: mode }))
    })()
</script>
