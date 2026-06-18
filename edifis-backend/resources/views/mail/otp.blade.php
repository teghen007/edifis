@component('mail::message')
# Your EDIFIS Login Code

Your 6-digit verification code is:

## **{{ $code }}**

This code expires in 10 minutes. Do not share it with anyone.

@component('mail::button', ['url' => url('/parent/verify-otp')])
Verify Now
@endcomponent

If you did not request this code, please ignore this email.

Thanks,<br>
The EDIFIS Team
@endcomponent
