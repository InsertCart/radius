@php
    $flashes = ['status' => 'ok', 'warning' => 'warn', 'error' => 'bad'];
@endphp

<div class="sf-wrap">
    @foreach ($flashes as $key => $tone)
        @if (session($key))
            <div class="sf-flash sf-flash--{{ $tone }}" role="status">
                <span>{{ session($key) }}</span>
                <button type="button" aria-label="Dismiss" onclick="this.parentNode.remove()">&times;</button>
            </div>
        @endif
    @endforeach

    @if ($errors->any())
        <div class="sf-flash sf-flash--bad" role="alert">
            <ul>
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif
</div>
