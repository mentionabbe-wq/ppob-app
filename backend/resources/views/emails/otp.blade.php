@component('mail::message')
# Kode Verifikasi

Gunakan kode berikut untuk {{ $purposeLabel }}:

@component('mail::panel')
<div style="font-size:32px;font-weight:700;letter-spacing:8px;text-align:center;font-family:monospace">
{{ $code }}
</div>
@endcomponent

Kode ini berlaku **{{ $ttl }} menit** dan hanya dapat dipakai satu kali.

Jika Anda tidak meminta kode ini, abaikan email ini dan jangan bagikan kode kepada siapa pun — termasuk yang mengaku sebagai petugas {{ config('app.name') }}.

Terima kasih,<br>
{{ config('app.name') }}
@endcomponent
