<div style="background:#f3f6fb;padding:32px 12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Arial,sans-serif;color:#1f2937;">
    <table width="100%" border="0" align="center" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
        <tbody>
        <tr>
            <td style="background:#315493;color:#ffffff;padding:24px 36px;font-size:22px;font-weight:700;">{{$name}}</td>
        </tr>
        <tr>
            <td style="padding:34px 36px 14px 36px;">
                <div style="font-size:26px;font-weight:700;line-height:1.4;color:#111827;">网站通知</div>
                <div style="font-size:16px;line-height:1.8;color:#4b5563;margin-top:16px;">尊敬的用户您好，您有一条新的站点通知。</div>
            </td>
        </tr>
        <tr>
            <td style="padding:0 36px 34px 36px;font-size:16px;line-height:1.9;color:#374151;">
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:18px 20px;">
                    {!! nl2br($content) !!}
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 36px;background:#f9fafb;font-size:14px;line-height:1.7;color:#6b7280;">
                <a href="{{$url}}" style="color:#315493;text-decoration:none;font-weight:600;">返回 {{$name}}</a>
                <span style="color:#9ca3af;"> · 本邮件由系统自动发出，请勿直接回复</span>
            </td>
        </tr>
        </tbody>
    </table>
</div>
