@php
    use Filament\Support\Colors\Color;
    use Moonweft\Preference\System\Appearance;
    $schemes = $kind === 'palette' ? Appearance::colorSchemes() : [];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" label-tag="div">
    <div class="mw-appearance-options mw-appearance-options-{{ $kind }}" role="radiogroup"
        aria-labelledby="{{ $getId() }}-label" @if ($isRequired()) aria-required="true" @endif>
        @foreach ($getOptions() as $value => $label)
            @php
                $primary = $kind === 'palette' ? Color::all()[$schemes[$value]['primary_color']] : Color::Blue;
                $gray = $kind === 'palette' ? Color::all()[$schemes[$value]['gray_color']] : Color::Slate;
            @endphp
            <label class="mw-appearance-option" data-appearance-option="{{ $kind }}:{{ $value }}"
                style="--preview-primary: {{ $primary[600] }}; --preview-soft: {{ $primary[100] }}; --preview-background: {{ $gray[50] }}; --preview-border: {{ $gray[200] }}; --preview-ink: {{ $gray[800] }}; --preview-muted: {{ $gray[400] }}">
                <input type="radio" name="{{ $getId() }}" id="{{ $getId() }}-{{ $value }}"
                    value="{{ $value }}" {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
                    @disabled($isDisabled()) @required($isRequired()) />
                @if ($kind === 'typography')
                    <span class="mw-font-preview" data-font-preview="{{ $theme }}"
                        style="--moonweft-font-scale: {{ ((int) $value) / 100 }}">
                        <span class="mw-font-preview-heading">{{ __('moonweft-preference::theme.preview_heading') }}</span>
                        <span class="mw-font-preview-body">{{ __('moonweft-preference::theme.preview_body') }}</span>
                        <span class="mw-font-preview-helper">{{ __('moonweft-preference::theme.preview_helper') }}</span>
                    </span>
                @else
                <span class="mw-appearance-preview {{ $kind === 'theme' ? 'mw-preview-'.$value : '' }}" aria-hidden="true">
                    <span class="mw-preview-sidebar"><i></i><i></i><i></i><i></i></span>
                    <span class="mw-preview-main"><span class="mw-preview-heading"></span><span class="mw-preview-cards"><i></i><i></i></span><span class="mw-preview-table"><i></i><i></i><i></i></span></span>
                </span>
                @endif
                @if ($kind === 'palette')
                    <span class="mw-appearance-swatches" aria-hidden="true"><i style="background: {{ $primary[600] }}"></i><i style="background: {{ $primary[400] }}"></i><i style="background: {{ $primary[100] }}"></i><i style="background: {{ $gray[200] }}"></i><i style="background: {{ $gray[800] }}"></i></span>
                @endif
                <span class="mw-appearance-name">{{ $label }}</span>
                @if ($kind === 'theme')
                    <span class="mw-appearance-description">{{ __('moonweft-preference::theme.themes.'.$value) }}</span>
                @endif
                <span class="mw-appearance-selected">{{ __('moonweft-preference::theme.selected') }}</span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
