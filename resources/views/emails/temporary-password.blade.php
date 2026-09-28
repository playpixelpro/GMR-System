<x-mail::message>
# Your temporary NFA GMR password

Hello **{{ $name }}**,

Your NFA GMR account has been created. Use the temporary password below to sign in:

<div style="margin: 24px 0; padding: 16px; background-color: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px; text-align: center;">
    <div style="font-size: 12px; letter-spacing: 1px; text-transform: uppercase; color: #6b7280; margin-bottom: 8px;">Temporary password</div>
    <div style="font-size: 20px; font-weight: 700; letter-spacing: 2px; color: #1d4ed8; word-break: break-all;">{{ $temporaryPassword }}</div>
</div>

You must change this password immediately after your first login.

<x-mail::button :url="$loginUrl">
Sign in to NFA GMR
</x-mail::button>

If you did not expect this account, contact the Administrator.
</x-mail::message>
