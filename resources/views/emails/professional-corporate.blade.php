<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Enterprise Proposal' }}</title>
    <style>
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .email-container {
            max-width: 650px;
            margin: 20px auto;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header-bar {
            background-color: #ffffff;
            padding: 12px 24px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            color: #64748b;
        }
        .content-area {
            padding: 28px 24px;
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
        }
        .subject-box {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .opportunity-item {
            margin-bottom: 14px;
            padding-left: 4px;
        }
        .opportunity-title {
            font-weight: 700;
            color: #0f172a;
        }
        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 24px 0;
            border: none;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            font-size: 12px;
            color: #475569;
            margin: 20px 0;
            line-height: 1.6;
        }
        .badge {
            display: inline-block;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin: 2px 4px 4px 0;
            font-weight: 500;
        }
        .footer-area {
            padding: 20px 24px;
            background-color: #ffffff;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            text-align: left;
        }
        a {
            color: {{ $primary_color ?? '#4F46E5' }};
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="email-container">
    <!-- 1. TOP HEADER -->
    <div class="header-bar">
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td align="left">
                    Official Preview: <strong style="color: {{ $primary_color ?? '#4F46E5' }};">{{ $company_name ?? 'BLUEBOXX.DA PRIVATE LIMITED' }}</strong> Enterprise Email
                </td>
                @if(!empty($lead_email))
                <td align="right" style="color: #64748b;">
                    To: <strong>{{ $lead_email }}</strong>
                </td>
                @endif
            </tr>
        </table>
    </div>

    <!-- MAIN BODY CONTENT -->
    <div class="content-area">
        <!-- 2. SUBJECT -->
        <div class="subject-box">
            Subject: {{ $subject }}
        </div>

        <!-- 3. GREETING -->
        <p style="margin-top: 0; font-size: 15px; font-weight: 600; color: #0f172a;">
            @if(!empty($contact_name))
                Hi {{ $contact_name }},
            @else
                Hi {{ $business_name }} Team,
            @endif
        </p>

        <!-- 4. PERSONALIZED OPENING PARAGRAPH -->
        <p>I hope this email finds you well.</p>
        <p>{!! nl2br(e($introduction)) !!}</p>

        <!-- 5. THREE NUMBERED SERVICE RECOMMENDATIONS -->
        @if(!empty($opportunities) && is_array($opportunities))
            <div style="margin: 20px 0;">
                @foreach($opportunities as $index => $opp)
                    <div class="opportunity-item">
                        <span class="opportunity-title">{{ $index + 1 }}. {{ $opp['title'] ?? 'Service Opportunity' }}:</span>
                        <div style="color: #475569; margin-top: 2px;">{{ $opp['description'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- 6. BLUEBOXX.DA HIGHLIGHTED PURPLE INFORMATION BOX -->
        <p style="background-color: #faf5ff; border-left: 3px solid {{ $primary_color ?? '#4F46E5' }}; padding: 12px 16px; margin: 20px 0; color: #334155; font-size: 13px; line-height: 1.6;">
            At <strong>{{ $company_name ?? 'BLUEBOXX.DA PRIVATE LIMITED' }}</strong>, we specialize in Website Development, Custom CRM Software, AI Automation, Digital Marketing designed specifically to deliver measurable growth for businesses like yours.
        </p>

        <!-- 7. CTA PARAGRAPH -->
        <p style="font-weight: 500; color: #0f172a; margin: 18px 0;">
            {{ $cta ?? 'Would you be open to a quick 5-minute call next Tuesday to discuss how these updates can boost your online presence?' }}
        </p>

        <hr class="divider">

        <!-- 8. REGARDS / SIGNATURE -->
        <div style="font-size: 13px; color: #334155; line-height: 1.5;">
            <p style="margin: 0;">Regards,</p>
            <p style="margin: 4px 0 0 0; font-weight: 700; color: #0f172a;">{{ $sender_name ?? 'Sumedh Agrawal' }}</p>
            <p style="margin: 0; color: #64748b; font-size: 12px; font-weight: 600;">{{ $sender_designation ?? 'BLUEBOXX.DA PRIVATE LIMITED' }}</p>
            @if(!empty($company_name) && $company_name !== ($sender_designation ?? ''))
                <p style="margin: 4px 0 0 0; font-weight: 700; color: {{ $primary_color ?? '#4F46E5' }};">{{ $company_name }}</p>
            @endif
            <p style="margin: 2px 0 0 0;"><a href="{{ $company_website ?? 'https://blueboxxda.com' }}" target="_blank">{{ $company_website ?? 'https://blueboxxda.com' }}</a></p>
        </div>

        <!-- 9. CONTACT INFORMATION CARD -->
        <div class="info-card">
            <div style="margin-bottom: 4px;">📍 <strong>Address:</strong> {{ $company_address ?? 'BLUEBOXX.DA Tower, Tech Park Road' }}</div>
            <div style="margin-bottom: 4px;">
                🌐 <strong>Website:</strong> <a href="{{ $company_website ?? 'https://blueboxxda.com' }}" target="_blank">{{ $company_website ?? 'https://blueboxxda.com' }}</a>
                &nbsp;|&nbsp; ✉ <strong>Support:</strong> <a href="mailto:{{ $company_email ?? 'contact@blueboxxda.com' }}">{{ $company_email ?? 'contact@blueboxxda.com' }}</a>
            </div>
            <div style="margin-bottom: 4px;">
                📞 <strong>Office Contact:</strong> {{ $company_phone ?? '+91 63565 6210' }}
                &nbsp;|&nbsp; 📱 <strong>Alt Contact:</strong> {{ $company_alternate_phone ?? '+91 63565 6210' }}
            </div>
            <div>
                <strong>Hours:</strong> {{ $business_hours ?? 'Mon–Fri (10:00 AM – 6:00 PM)' }}
            </div>
        </div>

        <!-- 10. OUR ENTERPRISE DIGITAL SERVICES (FOUR BADGES) -->
        <div style="margin-top: 20px;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                OUR ENTERPRISE DIGITAL SERVICES
            </div>
            <div>
                <span class="badge">✓ Website Development</span>
                <span class="badge">✓ Custom CRM Software</span>
                <span class="badge">✓ AI Automation</span>
                <span class="badge">✓ Digital Marketing</span>
            </div>
        </div>
    </div>

    <!-- 11. FOOTER -->
    <div class="footer-area">
        <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; color: #64748b;">
            © 2026 {{ $company_name ?? 'BLUEBOXX.DA PRIVATE LIMITED' }}. All Rights Reserved.
        </p>

        <p style="margin: 0 0 10px 0; font-size: 10px; color: #94a3b8; line-height: 1.4;">
            <strong>CONFIDENTIALITY NOTICE:</strong><br>
            This email and its contents are confidential and intended solely for the recipient. If you have received this email in error, please notify the sender and delete it.
        </p>

        <p style="margin: 0; font-size: 10px;">
            <a href="{{ $company_website ?? 'https://blueboxxda.com' }}" target="_blank">Company Website</a> &bull;
            <a href="{{ $privacy_policy_url ?? 'https://blueboxxda.com' }}" target="_blank">Privacy Policy</a> &bull;
            <a href="{{ $terms_url ?? 'https://blueboxxda.com' }}" target="_blank">Terms & Conditions</a>
        </p>
    </div>
</div>

</body>
</html>
