<div style="background:#f3f6fb;padding:32px 12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Helvetica Neue',Arial,sans-serif;color:#1f2937;">
    <table width="100%" border="0" align="center" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
        <tbody>
        <tr>
            <td style="background:#315493;color:#ffffff;padding:24px 36px;font-size:22px;font-weight:700;">{{$name}}</td>
        </tr>
        <tr>
            <td style="padding:34px 36px 14px 36px;">
                <div style="font-size:26px;font-weight:700;line-height:1.4;color:#111827;">登录确认</div>
                <div style="font-size:16px;line-height:1.8;color:#4b5563;margin-top:16px;">尊敬的用户您好，您正在登录 {{$name}}。</div>
            </td>
        </tr>
        <tr>
            <td style="padding:0 36px 28px 36px;font-size:16px;line-height:1.8;color:#4b5563;">
                请在 5 分钟内点击下方按钮完成登录。如果不是您发起的授权登录请求，请忽略此邮件。
            </td>
        </tr>
        <tr>
            <td style="padding:0 36px 18px 36px;">
                <a href="{{$link}}" style="display:inline-block;background:#315493;color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;border-radius:6px;padding:12px 22px;">确认登录</a>
            </td>
        </tr>
        <tr>
            <td style="padding:0 36px 34px 36px;font-size:13px;line-height:1.7;color:#6b7280;word-break:break-all;">
                如果按钮无法打开，请复制以下链接到浏览器：<br>
                <a href="{{$link}}" style="color:#315493;text-decoration:none;">{{$link}}</a>
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
