<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>{{ $title ?? 'Competency & IDP System' }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            background: #f8f9fa;
            color: #111827;
            font-family: Arial, Tahoma, sans-serif;
            -webkit-text-size-adjust: 100%;
        }

        table {
            border-collapse: collapse;
        }

        a {
            color: #75292d;
        }

        .email-shell {
            width: 100%;
            background: #f8f9fa;
            padding: 24px 12px;
        }

        .email-container {
            width: 100%;
            max-width: 680px;
            margin: 0 auto;
        }

        .email-card {
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        }

        .email-header {
            padding: 24px 28px 20px;
            background: #75292d;
            color: #ffffff;
        }

        .email-brand {
            margin: 0;
            color: #f8eeee;
            font-size: 20px;
            line-height: 1.35;
            font-weight: 700;
        }

        .email-subbrand {
            margin: 6px 0 0;
            font-size: 13px;
            line-height: 1.5;
            color: #f8eeee;
        }

        .email-body {
            padding: 30px 28px;
        }

        .mail-eyebrow {
            margin: 0 0 10px;
            color: #75292d;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.5;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 12px;
            color: #111827;
            font-size: 24px;
            line-height: 1.35;
            font-weight: 700;
        }

        .mail-lead,
        p {
            margin: 0 0 16px;
            color: #374151;
            font-size: 15px;
            line-height: 1.75;
        }

        .summary-grid {
            margin: 22px 0;
        }

        .summary-card {
            margin: 0 0 14px;
            padding: 18px;
            border: 1px solid #d1d5db;
            border-left: 5px solid #6b7280;
            border-radius: 12px;
            background: #ffffff;
        }

        .summary-card.is-primary { border-left-color: #75292d; background: #f8eeee; }
        .summary-card.is-success { border-left-color: #166534; background: #dcfce7; }
        .summary-card.is-warning { border-left-color: #92400e; background: #fef3c7; }
        .summary-card.is-danger { border-left-color: #b91c1c; background: #fee2e2; }
        .summary-card.is-neutral { border-left-color: #64748b; background: #f8fafc; }

        .summary-title {
            margin: 0 0 8px;
            color: #111827;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.5;
        }

        .summary-count {
            margin: 0 0 6px;
            color: #0f172a;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.1;
        }

        .summary-description {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.65;
        }

        .summary-list {
            margin: 14px 0 0;
            padding-top: 12px;
            border-top: 1px solid rgba(100, 116, 139, 0.22);
        }

        .summary-list p,
        .muted-note {
            margin: 0 0 8px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.55;
        }

        .email-alert {
            margin: 18px 0;
            padding: 14px 16px;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            background: #fff7ed;
            color: #7c2d12;
            font-size: 14px;
            line-height: 1.7;
        }

        .email-button-wrap {
            margin: 26px 0 4px;
        }

        .email-button {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 9px;
            background: #75292d;
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.4;
            text-decoration: none;
        }

        .email-footer {
            padding: 20px 28px 24px;
            border-top: 1px solid #d1d5db;
            background: #f8f9fa;
        }

        .email-footer p {
            margin: 0;
            color: #4b5563;
            font-size: 12px;
            line-height: 1.7;
        }

        @media only screen and (max-width: 600px) {
            .email-shell {
                padding: 12px 8px;
            }

            .email-header,
            .email-body,
            .email-footer {
                padding-left: 18px;
                padding-right: 18px;
            }

            h1 {
                font-size: 21px;
            }
        }
    </style>
</head>
<body>
    <table role="presentation" class="email-shell" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" class="email-container" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <div class="email-card">
                                @include('emails.components.header')

                                <div class="email-body">
                                    @yield('content')
                                </div>

                                @include('emails.components.footer')
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
