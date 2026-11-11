@extends('emails.layout', ['heading' => 'Testovací e-mail', 'title' => 'Testovací e-mail'])

@section('content')
    <div class="event-info">
        <strong>Toto je testovací e-mail.</strong> Nie je to vstupenka a QR kód nižšie nie je platný na vstup.
    </div>

    <p>Dobrý deň {{ $recipient->name }},</p>

    <p>tento e-mail bol odoslaný z administrácie podujatia, aby ste si mohli overiť doručovanie a vzhľad e-mailov.</p>

    <div class="info-section">
        <h3>Informácie o podujatí</h3>
        <div class="detail-row">
            <span class="label">Názov:</span> {{ $event->overline }} {{ $event->title }}
        </div>
        <div class="detail-row">
            <span class="label">Dátum a čas:</span> {{ \Carbon\Carbon::parse($event->start_time)->format('d.m.Y H:i') }}
        </div>
        @if($event->location)
        <div class="detail-row">
            <span class="label">Miesto:</span> {{ $event->location->address }}
        </div>
        @endif
    </div>

    <div class="info-section">
        <h3>Ukážkový QR kód</h3>
        <div style="text-align: center; margin: 20px 0;">
            <img src="{{ $message->embedData($qrPng, 'qr-test.png', 'image/png') }}" alt="Testovací QR kód" width="200" height="200" style="display: inline-block; border: 1px solid #ddd; padding: 8px; background: #fff;">
            <div style="font-size: 12px; color: #666; margin-top: 8px;">TESTOVACÍ QR KÓD – NEPLATNÝ</div>
        </div>
    </div>

    <div class="info-section">
        <h3>Odoslané</h3>
        <div class="detail-row">
            <span class="label">Komu:</span> {{ $recipient->email }}
        </div>
        <div class="detail-row">
            <span class="label">Kedy:</span> {{ now()->format('d.m.Y H:i:s') }}
        </div>
    </div>
@endsection
