@component('mail::message')

# GPG Key Encryption Error

An error occurred while trying to encrypt an email recently forwarded to you by addy.io.

This may be caused by an expired or revoked key, a missing encryption subkey, or an incompatible key format.

The fingerprint of the key is: **{{ $recipient->fingerprint }}**

Encryption for this recipient has been turned off. Please check and re-upload your public key if you wish to continue using encryption.

@component('mail::button', ['url' => config('app.url').'/recipients'])
Update Key
@endcomponent
@endcomponent
