<!DOCTYPE html>
<html lang="en">
<body style="font-family: 'Segoe UI', Arial, sans-serif; background:#f4f6f9; padding:24px; margin:0;">
    <div style="max-width:520px; margin:0 auto; background:#ffffff; border-radius:12px; padding:28px; color:#1c3f66;">
        <h2 style="margin:0 0 4px;">PSU Intern-Link</h2>
        <p style="margin:0 0 20px; color:#6b7c93; font-size:13px;">{{ $campusName }}</p>

        <p>Good day, {{ $user->name }},</p>

        @if ($isReset)
            <p>The password of your PSU Intern-Link <strong>{{ $roleName }}</strong> account was reset. Please use the new sign-in details below.</p>
        @else
            <p>A PSU Intern-Link <strong>{{ $roleName }}</strong> account has been created for you for <strong>{{ $campusName }}</strong>.</p>
        @endif

        <table style="width:100%; background:#f0f4fa; border-radius:10px; padding:14px 18px; margin:20px 0; font-size:15px;">
            <tr>
                <td style="color:#6b7c93; padding:4px 0; width:150px;">ID number</td>
                <td style="font-weight:700; font-family:Consolas, monospace;">{{ $user->id_number }}</td>
            </tr>
            <tr>
                <td style="color:#6b7c93; padding:4px 0;">Temporary password</td>
                <td style="font-weight:700; font-family:Consolas, monospace;">{{ $password }}</td>
            </tr>
        </table>

        <p style="text-align:center; margin:24px 0;">
            <a href="{{ $loginUrl }}" style="background:#1c3f66; color:#ffffff; text-decoration:none; padding:12px 26px; border-radius:8px; font-weight:600; display:inline-block;">
                Sign in to PSU Intern-Link
            </a>
        </p>

        <p style="font-size:13px; color:#44546a;">
            Sign in with your ID number and this temporary password. You will be asked to choose your own
            password right away. Please don't share these details with anyone.
        </p>
    </div>
</body>
</html>
