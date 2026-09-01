<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Business Inquiry' }}</title>
</head>
<body style="margin: 0; padding: 20px 10px; background-color: #ffffff; color: #1e293b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14.5px; line-height: 1.65; -webkit-font-smoothing: antialiased;">

<div style="max-width: 580px; margin: 0 auto; padding: 10px 0; background-color: #ffffff;">

    <!-- GREETING -->
    <p style="margin: 0 0 16px 0; font-size: 15px; font-weight: 600; color: #0f172a;">
        @if(!empty($contact_name))
            Hi {{ $contact_name }},
        @else
            Hi {{ $business_name }} Team,
        @endif
    </p>

    <!-- INTRO PARAGRAPH -->
    <p style="margin: 0 0 16px 0; color: #334155;">
        I hope this note finds you well.
    </p>
    <p style="margin: 0 0 18px 0; color: #334155;">
        {!! nl2br(e($introduction)) !!}
    </p>

    <!-- OPPORTUNITIES / SERVICE IDEAS -->
    @if(!empty($opportunities) && is_array($opportunities))
        <div style="margin: 18px 0 22px 0;">
            @foreach($opportunities as $index => $opp)
                <div style="margin-bottom: 12px; padding-left: 4px;">
                    <div style="font-weight: 700; color: #0f172a; margin-bottom: 2px;">
                        • {{ $opp['title'] ?? 'Digital Growth Opportunity' }}
                    </div>
                    <div style="color: #475569; font-size: 14px; padding-left: 12px;">
                        {{ $opp['description'] ?? '' }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- VALUE PROPOSITION -->
    @if(!empty($value_proposition))
        <p style="margin: 0 0 18px 0; color: #334155;">
            At <strong>{{ $company_name ?? 'BLUEBOXX.DA PRIVATE LIMITED' }}</strong>, we specialize in building custom web platforms, automated lead systems, and visual branding tailored to deliver measurable business growth.
        </p>
    @endif

    <!-- CALL TO ACTION -->
    <p style="margin: 0 0 24px 0; font-weight: 500; color: #0f172a;">
        {{ $cta ?? 'Would you be open to a quick 5-minute call next Tuesday to discuss how these updates can help your business?' }}
    </p>

    <!-- DIVIDER -->
    <div style="height: 1px; background-color: #e2e8f0; margin: 24px 0; border: none;"></div>

    <!-- PROFESSIONAL SIGNATURE -->
    <div style="font-size: 13.5px; color: #334155; line-height: 1.55;">
        <p style="margin: 0 0 4px 0; color: #475569;">Warm regards,</p>
        <p style="margin: 0; font-weight: 700; font-size: 14.5px; color: #0f172a;">{{ $sender_name ?? 'Sumedh Agrawal' }}</p>
        <p style="margin: 1px 0 0 0; color: #64748b; font-size: 12.5px;">{{ $sender_designation ?? 'Business Development' }} &bull; <strong style="color: #4338ca;">{{ $company_name ?? 'BLUEBOXX.DA PRIVATE LIMITED' }}</strong></p>
        
        <div style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #64748b;">
            @if(!empty($company_phone))
                <span>📞 {{ $company_phone }}</span>
                @if(!empty($company_alternate_phone)) &nbsp;|&nbsp; <span>📱 {{ $company_alternate_phone }}</span> @endif
                <br>
            @endif
            @if(!empty($company_website))
                <span>🌐 <a href="{{ $company_website }}" target="_blank" style="color: #4f46e5; text-decoration: none;">{{ preg_replace('/^https?:\/\//', '', $company_website) }}</a></span>
                &nbsp;|&nbsp;
            @endif
            <span>✉ <a href="mailto:{{ $company_email ?? 'info.blueboxx@gmail.com' }}" style="color: #4f46e5; text-decoration: none;">{{ $company_email ?? 'info.blueboxx@gmail.com' }}</a></span>
            @if(!empty($company_address))
                <br><span>📍 {{ $company_address }}</span>
            @endif
        </div>
    </div>

    <!-- COMPLIANT FOOTER -->
    <div style="margin-top: 32px; padding-top: 14px; border-top: 1px solid #f1f5f9; font-size: 11px; color: #94a3b8; line-height: 1.5;">
        <p style="margin: 0 0 4px 0;">
            You received this message regarding digital growth opportunities for {{ $business_name }}. If you prefer not to receive future emails, you can <a href="mailto:{{ $company_email ?? 'info.blueboxx@gmail.com' }}?subject=Unsubscribe" style="color: #64748b; text-decoration: underline;">unsubscribe here</a> or simply reply "unsubscribe".
        </p>
        <p style="margin: 0; font-size: 10.5px;">
            {{ $company_name ?? 'BLUEBOXX.DA PRIVATE LIMITED' }}
        </p>
    </div>

</div>

</body>
</html>
