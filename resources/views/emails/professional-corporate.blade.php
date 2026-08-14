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
        }
        .badge {
            display: inline-block;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin: 2px;
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
    <!-- HEADER -->
    <div class="header-bar">
        <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
                <td align="left">
                    Official Preview: <strong style="color: {{ $primary_color ?? '#4F46E5' }};">{{ $company_name }}</strong> Enterprise Email
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
        <!-- SUBJECT -->
        <div class="subject-box">
            Subject: {{ $subject }}
        </div>

        <!-- GREETING -->
        <p style="margin-top: 0; font-size: 15px; font-weight: 600; color: #0f172a;">
            @if(!empty($contact_name))
                Hi {{ $contact_name }},
            @else
                Hi {{ $business_name }} Team,
            @endif
        </p>

        <p>I hope this email finds you well.</p>

        <!-- INTRODUCTION -->
        <p>{!! nl2br(e($introduction)) !!}</p>

        <!-- NUMBERED OPPORTUNITIES -->
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

        <!-- VALUE PROPOSITION -->
        @if(!empty($value_proposition))
            <p style="background-color: #faf5ff; border-left: 3px solid {{ $primary_color ?? '#4F46E5' }}; padding: 10px 14px; margin: 18px 0; color: #334155; font-size: 13px;">
                {{ $value_proposition }}
            </p>
        @endif

        <!-- CTA -->
        <p style="font-weight: 500; color: #0f172a;">
            {{ $cta }}
        </p>

        <hr class="divider">

        <!-- SIGNATURE -->
        <div style="font-size: 13px; color: #334155;">
            <p style="margin: 0;">Regards,</p>
            <p style="margin: 4px 0 0 0; font-weight: 700; color: #0f172a;">{{ $sender_name }}</p>
            @if(!empty($sender_designation))
                <p style="margin: 0; color: #64748b; font-size: 12px; font-weight: 600;">{{ $sender_designation }}</p>
            @endif
            @if(!empty($company_name) && $company_name !== $sender_designation)
                <p style="margin: 4px 0 0 0; font-weight: 700; color: {{ $primary_color ?? '#4F46E5' }};">{{ $company_name }}</p>
            @endif
            @if(!empty($company_website))
                <p style="margin: 2px 0 0 0;"><a href="{{ $company_website }}" target="_blank">{{ $company_website }}</a></p>
            @endif
        </div>

        <!-- COMPANY INFORMATION CARD -->
        <div class="info-card">
            @if(!empty($company_address))
                <div style="margin-bottom: 4px;">📍 <strong>Address:</strong> {{ $company_address }}</div>
            @endif

            <div style="margin-bottom: 4px;">
                @if(!empty($company_website))
                    🌐 <strong>Website:</strong> <a href="{{ $company_website }}" target="_blank">{{ $company_website }}</a>
                @endif
                @if(!empty($company_email))
                    &nbsp;|&nbsp; ✉ <strong>Support:</strong> <a href="mailto:{{ $company_email }}">{{ $company_email }}</a>
                @endif
            </div>

            @if(!empty($company_phone) || !empty($company_alternate_phone))
                <div style="margin-bottom: 4px;">
                    @if(!empty($company_phone))
                        📞 <strong>Office Contact:</strong> {{ $company_phone }}
                    @endif
                    @if(!empty($company_alternate_phone))
                        &nbsp;|&nbsp; 📞 <strong>Alt Contact:</strong> {{ $company_alternate_phone }}
                    @endif
                </div>
            @endif

            @if(!empty($gst_number) || !empty($cin_number) || !empty($business_hours))
                <div>
                    @if(!empty($gst_number))
                        <strong>GST:</strong> {{ $gst_number }} &nbsp;
                    @endif
                    @if(!empty($cin_number))
                        <strong>CIN:</strong> {{ $cin_number }} &nbsp;
                    @endif
                    @if(!empty($business_hours))
                        <strong>Hours:</strong> {{ $business_hours }}
                    @endif
                </div>
            @endif
        </div>

        <!-- ENTERPRISE SERVICES BADGES -->
        @if(!empty($services) && is_array($services) && count($services) > 0)
            <div style="margin-top: 20px;">
                <div style="font-size: 11px; font-weight: 700; color: #64748b; uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                    OUR ENTERPRISE DIGITAL SERVICES
                </div>
                <div>
                    @foreach($services as $service)
                        <span class="badge">✓ {{ trim($service) }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- FOOTER -->
    <div class="footer-area">
        <p style="margin: 0 0 8px 0; font-size: 11px; font-weight: 600; color: #64748b;">
            © {{ date('Y') }} {{ $company_name }}. All Rights Reserved.
        </p>

        <p style="margin: 0 0 10px 0; font-size: 10px; color: #94a3b8; line-height: 1.4;">
            <strong>CONFIDENTIALITY NOTICE:</strong><br>
            This email and any attachments are confidential and intended solely for the recipient. If received by mistake, please notify the sender and delete the email immediately.
        </p>

        <p style="margin: 0; font-size: 10px;">
            @if(!empty($company_website))
                <a href="{{ $company_website }}" target="_blank">Company Website</a> &bull;
            @endif
            @if(!empty($privacy_policy_url))
                <a href="{{ $privacy_policy_url }}" target="_blank">Privacy Policy</a> &bull;
            @else
                <a href="{{ $company_website ?? '#' }}" target="_blank">Privacy Policy</a> &bull;
            @endif
            @if(!empty($terms_url))
                <a href="{{ $terms_url }}" target="_blank">Terms & Conditions</a>
            @else
                <a href="{{ $company_website ?? '#' }}" target="_blank">Terms & Conditions</a>
            @endif
        </p>
    </div>
</div>

</body>
</html>
