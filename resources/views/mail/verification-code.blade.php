<!doctype html>
<html lang="en" dir="ltr">
<body style="margin:0;background:#f6f8fb;font-family:Arial,Tahoma,sans-serif;color:#111827;">
  <div style="max-width:560px;margin:0 auto;padding:28px 18px;">
    <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:26px;text-align:left;">
      <h1 style="margin:0 0 12px;font-size:22px;color:#111827;">Confirm your email</h1>
      <p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#374151;">Click the button below to confirm your email address for InfluencerHub.</p>
      <p style="margin:24px 0;text-align:center;">
        <a href="{{ $verifyUrl }}" style="display:inline-block;background:#33257f;color:#ffffff;text-decoration:none;font-weight:700;border-radius:8px;padding:13px 24px;">Confirm email</a>
      </p>
      <p style="margin:0 0 10px;font-size:14px;line-height:1.7;color:#4b5563;">You can also enter this verification code manually:</p>
      <p style="margin:0 0 18px;text-align:center;font-size:28px;font-weight:800;letter-spacing:5px;direction:ltr;color:#111827;">{{ $code }}</p>
      <p style="margin:0;font-size:12px;line-height:1.7;color:#6b7280;">This link and code expire soon. If you did not request this email, you can safely ignore it.</p>
    </div>
  </div>
</body>
</html>