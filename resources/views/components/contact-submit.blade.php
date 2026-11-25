@props(['formId' => null, 'inputClass' => 'rounded-lg border border-slate-300 px-3 py-2 text-sm'])
@if (\App\Cms\Forms\ContactChallenge::enabled())
    @php($challenge = app(\App\Cms\Forms\ContactChallenge::class)->issue(request()))
    <input type="hidden" name="_contact_challenge" value="{{ $challenge['token'] }}">
    <div>
        <label>
            <span style="display:block">{{ $challenge['question'] }}</span>
            <input type="text" name="contact_answer" inputmode="numeric" pattern="[0-9]+" maxlength="2"
                   required autocomplete="off" class="{{ $inputClass }}">
        </label>
        @if (old('_form') === $formId)
            @error('contact_answer') <p role="alert">{{ $message }}</p> @enderror
        @endif
    </div>
@endif
{{ $slot }}
{{-- After the visible button so pressing Enter activates the regular submit. --}}
<div hidden style="display:none" aria-hidden="true">
    <input type="text" name="website" tabindex="-1" autocomplete="off">
    <button type="submit" name="_contact_copy" value="1" tabindex="-1" formnovalidate>Send message</button>
</div>
