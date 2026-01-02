<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            padding: 20px;
            text-align: center;
            border-bottom: 2px solid #e0e0e0;
        }
        .header.error { background-color: #fee2e2; border-bottom-color: #f87171; }
        .header.warning { background-color: #fef3c7; border-bottom-color: #facc15; }
        .header.success { background-color: #d1fae5; border-bottom-color: #34d399; }
        .header.info { background-color: #dbeafe; border-bottom-color: #60a5fa; }
        .content {
            padding: 30px 20px;
        }
        .title {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 10px 0;
            color: #111827;
        }
        .type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .type-badge.error { background-color: #fee2e2; color: #991b1b; }
        .type-badge.warning { background-color: #fef3c7; color: #92400e; }
        .type-badge.success { background-color: #d1fae5; color: #065f46; }
        .type-badge.info { background-color: #dbeafe; color: #1e40af; }
        .message {
            font-size: 16px;
            line-height: 1.6;
            color: #4b5563;
            margin: 20px 0;
        }
        .footer {
            padding: 20px;
            text-align: center;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        .button {
            display: inline-block;
            margin: 20px 0;
            padding: 12px 24px;
            background-color: #3b82f6;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        .button:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header {{ $notification->type->value }}">
            <span class="type-badge {{ $notification->type->value }}">{{ $notification->type->label() }}</span>
        </div>

        <div class="content">
            <h1 class="title">{{ $notification->title }}</h1>

            <div class="message">
                {!! nl2br(e($notification->content)) !!}
            </div>

            @if(config('app.url'))
                <div style="text-align: center;">
                    <a href="{{ config('app.url') }}" class="button">
                        View in {{ config('app.name') }}
                    </a>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>This is an automated notification from {{ config('app.name') }}.</p>
            <p>If you no longer wish to receive these emails, you can update your notification preferences in your account settings.</p>
        </div>
    </div>
</body>
</html>
