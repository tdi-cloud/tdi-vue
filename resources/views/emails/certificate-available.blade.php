<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="color-scheme" content="light" />
    <title>{{ $emailSubject }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        body { margin: 0; padding: 0; width: 100% !important; background-color: #F5F7FB; font-family: Arial, Helvetica, sans-serif; }
        a { text-decoration: none; }
        .tdi-sans { font-family: Arial, Helvetica, sans-serif; }
        .tdi-body p { margin: 0 0 12px; }
        .tdi-body p:last-child { margin-bottom: 0; }
        .tdi-body a { color: #1D4ED8; text-decoration: underline; }
        .tdi-body h1 { margin: 0 0 12px; font-size: 22px; line-height: 1.3; color: #0B1F44; }
        .tdi-body h2 { margin: 0 0 10px; font-size: 18px; line-height: 1.35; color: #0B1F44; }
        .tdi-body h3 { margin: 0 0 8px; font-size: 15.5px; line-height: 1.4; color: #0B1F44; }
        .tdi-body ul, .tdi-body ol { margin: 0 0 12px; padding-left: 24px; }
        .tdi-body li { margin: 0 0 4px; }
        .tdi-body blockquote { margin: 0 0 12px; padding: 8px 14px; border-left: 3px solid #C8A45D; background-color: #FAF6EC; color: #475569; }
        .tdi-body pre { margin: 0 0 12px; padding: 12px 14px; border-radius: 8px; background-color: #F1F5F9; color: #0F172A; font-family: Consolas, 'Courier New', monospace; font-size: 13px; white-space: pre-wrap; }
        .tdi-body code { font-family: Consolas, 'Courier New', monospace; font-size: 13px; background-color: #F1F5F9; padding: 1px 4px; border-radius: 4px; }
        .tdi-body pre code { padding: 0; background-color: transparent; }
        .tdi-signature p { margin: 0 0 4px; }
        .tdi-signature a { color: #1D4ED8; }
        @media only screen and (max-width: 600px) {
            .tdi-container { width: 100% !important; }
            .tdi-stack { display: block !important; width: 100% !important; text-align: left !important; padding-left: 0 !important; padding-right: 0 !important; }
            .tdi-stack-r { text-align: left !important; margin-top: 10px !important; }
            .tdi-px { padding-left: 22px !important; padding-right: 22px !important; }
            .tdi-hero-title { font-size: 22px !important; }
            .tdi-cert-pad { padding: 22px 18px !important; }
            .tdi-cert-title { font-size: 21px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#F5F7FB;">
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#F5F7FB;">
        Your {{ $certificateLabel }} for {{ $programTitle }} is attached.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5F7FB;">
        <tr>
            <td align="center" style="padding:32px 12px;">

                <!--[if mso]>
                <table role="presentation" width="640" align="center" cellpadding="0" cellspacing="0"><tr><td>
                <![endif]-->
                <table role="presentation" class="tdi-container" width="640" cellpadding="0" cellspacing="0"
                    style="width:640px;max-width:640px;background-color:#FFFFFF;border-radius:16px;overflow:hidden;border:1px solid #E7EBF3;">

                    <!-- thin gold accent line -->
                    <tr>
                        <td height="3" bgcolor="#C8A45D" style="background-color:#C8A45D;background-image:linear-gradient(90deg,#C8A45D 0%,#E8D4A2 50%,#C8A45D 100%);font-size:1px;line-height:3px;">&nbsp;</td>
                    </tr>

                    <!-- ══════════════════════════════════════════
                         HERO (changes with the certificate type)
                    ══════════════════════════════════════════ -->
                    <tr>
                        <td class="tdi-px tdi-sans" bgcolor="#0B1F44"
                            style="background-color:#0B1F44;background-image:linear-gradient(135deg,#0B1F44 0%,#123B82 60%,#1D4ED8 100%);padding:38px 32px 34px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="middle" width="62" style="padding-right:16px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" width="54" style="background-color:rgba(232,212,162,0.16);border:1px solid rgba(232,212,162,0.45);border-radius:50%;">
                                            <tr>
                                                <td align="center" valign="middle" height="54"
                                                    style="font-family:Arial,Helvetica,sans-serif;font-size:26px;line-height:54px;">{{ $heroEmoji }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td valign="middle">
                                        <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:2px;color:#E8D4A2;text-transform:uppercase;">TESDA Development Institute</p>
                                        <p class="tdi-hero-title" style="margin:6px 0 0;font-size:25px;font-weight:800;color:#FFFFFF;line-height:1.3;letter-spacing:0.2px;">{{ $certificateLabel }}</p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0 16px;">
                                <tr><td width="42" height="2" bgcolor="#C8A45D" style="background-color:#C8A45D;font-size:1px;line-height:2px;">&nbsp;</td></tr>
                            </table>

                            <p style="margin:0;font-size:13.5px;color:#C9D6EF;line-height:1.5;max-width:460px;">
                                Awarded to <strong style="color:#FFFFFF;">{{ $recipientName }}</strong> &middot; {{ $programTitle }}
                            </p>
                        </td>
                    </tr>

                    <!-- ══════════════════════════════════════════
                         BODY (sanitized rich text, placeholders filled in)
                    ══════════════════════════════════════════ -->
                    <tr>
                        <td class="tdi-px tdi-body tdi-sans" style="padding:34px 32px 6px;font-size:14.5px;line-height:1.55;color:#1E293B;">
                            {!! $body !!}
                        </td>
                    </tr>

                    <!-- ══════════════════════════════════════════
                         YOUR CERTIFICATE CARD
                    ══════════════════════════════════════════ -->
                    <tr>
                        <td class="tdi-px" style="padding:26px 32px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#FFFDF7"
                                style="background-color:#FFFDF7;border:1px solid #C8A45D;border-radius:12px;">
                                <tr>
                                    <td class="tdi-sans tdi-cert-pad" align="center" style="padding:30px 32px 26px;">
                                        <p style="margin:0;font-size:10.5px;font-weight:800;letter-spacing:2.4px;color:#9A7B3C;text-transform:uppercase;">Your Certificate</p>
                                        <p class="tdi-cert-title" style="margin:10px 0 0;font-family:Georgia,'Times New Roman',serif;font-size:24px;font-weight:700;color:#0B1F44;line-height:1.3;">{{ $certificateLabel }}</p>

                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:14px auto 18px;">
                                            <tr><td width="36" height="2" bgcolor="#C8A45D" style="background-color:#C8A45D;font-size:1px;line-height:2px;">&nbsp;</td></tr>
                                        </table>

                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="text-align:left;">
                                            <tr>
                                                <td width="130" style="padding:7px 0;font-size:11.5px;color:#64748B;font-weight:600;vertical-align:top;">Type</td>
                                                <td style="padding:7px 0;font-size:13.5px;color:#0B1F44;font-weight:700;">{{ $certificate->type }}</td>
                                            </tr>
                                            @if ($certificateNumber)
                                                <tr>
                                                    <td style="padding:7px 0;font-size:11.5px;color:#64748B;font-weight:600;vertical-align:top;border-top:1px solid #F1EAD9;">Certificate No.</td>
                                                    <td style="padding:7px 0;font-size:13.5px;color:#0B1F44;font-weight:700;border-top:1px solid #F1EAD9;font-family:Consolas,'Courier New',monospace;">{{ $certificateNumber }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td style="padding:7px 0;font-size:11.5px;color:#64748B;font-weight:600;vertical-align:top;border-top:1px solid #F1EAD9;">Program</td>
                                                <td style="padding:7px 0;font-size:13.5px;color:#0B1F44;font-weight:700;border-top:1px solid #F1EAD9;">{{ $programTitle }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:7px 0;font-size:11.5px;color:#64748B;font-weight:600;vertical-align:top;border-top:1px solid #F1EAD9;">Batch</td>
                                                <td style="padding:7px 0;font-size:13.5px;color:#0B1F44;font-weight:700;border-top:1px solid #F1EAD9;">{{ $batchName }}</td>
                                            </tr>
                                            @if ($schedule)
                                                <tr>
                                                    <td style="padding:7px 0;font-size:11.5px;color:#64748B;font-weight:600;vertical-align:top;border-top:1px solid #F1EAD9;">Training Dates</td>
                                                    <td style="padding:7px 0;font-size:13.5px;color:#0B1F44;font-weight:700;border-top:1px solid #F1EAD9;">{{ $schedule }}</td>
                                                </tr>
                                            @endif
                                            @if ($issuedDate)
                                                <tr>
                                                    <td style="padding:7px 0;font-size:11.5px;color:#64748B;font-weight:600;vertical-align:top;border-top:1px solid #F1EAD9;">Date Issued</td>
                                                    <td style="padding:7px 0;font-size:13.5px;color:#0B1F44;font-weight:700;border-top:1px solid #F1EAD9;">{{ $issuedDate }}</td>
                                                </tr>
                                            @endif
                                            @if ($hours)
                                                <tr>
                                                    <td style="padding:7px 0;font-size:11.5px;color:#64748B;font-weight:600;vertical-align:top;border-top:1px solid #F1EAD9;">Hours</td>
                                                    <td style="padding:7px 0;font-size:13.5px;color:#0B1F44;font-weight:700;border-top:1px solid #F1EAD9;">{{ $hours }} {{ $hours === '1' ? 'hour' : 'hours' }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- ══════════════════════════════════════════
                         ATTACHMENT NOTE + BUTTON
                    ══════════════════════════════════════════ -->
                    <tr>
                        <td class="tdi-px tdi-sans" align="center" style="padding:24px 32px 0;">
                            <p style="margin:0;font-size:13.5px;color:#475569;line-height:1.5;">📎 Your certificate is attached to this email as a PDF.</p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px auto 0;">
                                <tr>
                                    <td align="center" bgcolor="#0B1F44" style="background-color:#0B1F44;border-radius:10px;border-bottom:2px solid #C8A45D;">
                                        <a href="{{ $myCertificatesUrl }}" target="_blank" rel="noopener noreferrer"
                                            style="display:inline-block;padding:13px 26px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#FFFFFF;text-decoration:none;letter-spacing:0.2px;">
                                            View in My Certificates
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- ══════════════════════════════════════════
                         SIGNATURE (sanitized rich text, optional)
                    ══════════════════════════════════════════ -->
                    <tr>
                        <td class="tdi-px tdi-sans" style="padding:30px 32px 34px;">
                            @if (trim($signature) !== '')
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:18px;">
                                    <tr><td width="100%" height="1" bgcolor="#E7EBF3" style="background-color:#E7EBF3;font-size:1px;line-height:1px;">&nbsp;</td></tr>
                                </table>
                                <div class="tdi-signature" style="font-size:12.5px;color:#64748B;line-height:1.5;">{!! $signature !!}</div>
                            @endif
                        </td>
                    </tr>

                    <!-- ══════════════════════════════════════════
                         FOOTER
                    ══════════════════════════════════════════ -->
                    <tr>
                        <td height="2" bgcolor="#C8A45D" style="background-color:#C8A45D;background-image:linear-gradient(90deg,#C8A45D 0%,#E8D4A2 50%,#C8A45D 100%);font-size:1px;line-height:2px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="tdi-px tdi-sans" bgcolor="#0B1F44"
                            style="background-color:#0B1F44;background-image:radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px);background-size:14px 14px;padding:26px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td class="tdi-stack" valign="top">
                                        <p style="margin:0;font-size:11.5px;font-weight:800;color:#FFFFFF;letter-spacing:0.3px;">TESDA Development Institute</p>
                                        <p style="margin:2px 0 0;font-size:10.5px;color:#9FB3D9;">TESDA Learning &amp; Development</p>
                                    </td>
                                    <td class="tdi-stack tdi-stack-r" valign="top" align="right" style="font-size:10px;color:#8296C2;line-height:1.5;">
                                        TDI L&amp;D Monitoring System<br />
                                        Automated Notification &middot; Please do not reply directly to this email<br />
                                        &copy; {{ date('Y') }} TESDA
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
                <!--[if mso]>
                </td></tr></table>
                <![endif]-->

            </td>
        </tr>
    </table>
</body>
</html>
