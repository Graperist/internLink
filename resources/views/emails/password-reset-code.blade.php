<!DOCTYPE html>
<html lang="en">
<body style="font-family: 'Segoe UI', Arial, sans-serif; background:#f4f6f9; padding:24px; margin:0;">
    <div style="max-width:480px; margin:0 auto; background:#ffffff; border-radius:12px; padding:28px; color:#1c3f66;">
        <h2 style="margin:0 0 4px;">PSU Intern-Link</h2>
        <p style="margin:0 0 20px; color:#6b7c93; font-size:13px;">Pangasinan State University</p>

        <p>Hi {{ $user->name }},</p>
        <p>Use this verification code to {{ ($purpose ?? 'reset') === 'change' ? 'change' : 'reset' }} your PSU Intern-Link password:</p>

        <p style="font-size:34px; font-weight:700; letter-spacing:8px; text-align:center; background:#f0f4fa; border-radius:10px; padding:14px 0; margin:20px 0;">
            {{ $code }}
        </p>

        <p style="font-size:13px; color:#44546a;">
            This code expires in {{ $minutes }} minutes. If you didn't ask to {{ ($purpose ?? 'reset') === 'change' ? 'change' : 'reset' }} your password, you can ignore this
            email - your password will stay the same. Never share this code with anyone.
        </p>
    </div>
</body>
</html>
