@component('mail::message')
# 🏫 {{ $schoolName }} is now live on EDIFIS

Your school has been onboarded by the Presbyterian Education Authority.

**School code:** `{{ $schoolCode }}`  
**Login URL:** [{{ $loginUrl }}]({{ $loginUrl }})  
**Username:** Your email address  
**One-time claim code:** `{{ $claimCode }}`

On your first login, you will be asked to set a new password. Keep this claim code private.

@component('mail::button', ['url' => $loginUrl])
Sign In
@endcomponent

Welcome to EDIFIS — God · Peace · Knowledge.

— PEA ICT Office
@endcomponent
