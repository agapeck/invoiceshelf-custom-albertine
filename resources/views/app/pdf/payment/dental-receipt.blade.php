<!DOCTYPE html>
<html>

<head>
    <title>@lang('pdf_payment_label') - {{ $payment->payment_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style type="text/css">
        /* -- Base -- */
        body {
            font-family: "DejaVu Sans";
        }

        html {
            margin: 0px;
            padding: 0px;
            margin-top: 50px;
            margin-bottom: 50px;
        }

        table {
            border-collapse: collapse;
        }

        hr {
            color: rgba(0, 0, 0, 0.2);
            border: 0.5px solid #EAF1FB;
            margin: 30px 0px;
        }

        /* -- Header -- */
        .header-container {
            width: 100%;
            padding: 0 30px;
            margin-bottom: 30px;
        }

        .header-logo {
            text-transform: capitalize;
            color: #2563eb;
            padding-top: 0px;
        }

        .company-address {
            font-size: 11px;
            line-height: 15px;
            color: #595959;
            word-wrap: break-word;
        }

        .company-details h1 {
            margin: 0;
            font-weight: bold;
            font-size: 15px;
            line-height: 22px;
            letter-spacing: 0.05em;
            text-align: left;
            max-width: 220px;
        }

        /* -- Receipt Title -- */
        .receipt-title {
            text-align: center;
            margin: 20px 0;
        }

        .receipt-title h2 {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            border-bottom: 2px solid #2563eb;
            display: inline-block;
            padding-bottom: 5px;
            margin: 0;
        }

        /* -- Content -- */
        .content-wrapper {
            display: block;
            padding: 0 30px;
        }

        .details-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .details-table td {
            padding: 8px 0;
            vertical-align: top;
        }

        .label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .value {
            font-size: 13px;
            color: #1f2937;
            font-weight: 500;
        }

        /* -- Patient Info Box -- */
        .patient-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .patient-name {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .patient-details {
            font-size: 11px;
            color: #6b7280;
        }

        /* -- Amount Box -- */
        .amount-box {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .amount-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .amount-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .amount-words {
            font-size: 12px;
            font-style: italic;
            opacity: 0.9;
            border-top: 1px solid rgba(255,255,255,0.3);
            padding-top: 10px;
            margin-top: 10px;
        }

        /* -- Payment Details Grid -- */
        .payment-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .payment-grid-item {
            display: table-cell;
            width: 33.33%;
            padding: 10px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .payment-grid-item .label {
            display: block;
            margin-bottom: 5px;
        }

        .payment-grid-item .value {
            font-size: 14px;
        }

        /* -- Invoice Reference -- */
        .invoice-ref {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .invoice-ref strong {
            color: #92400e;
        }

        /* -- Notes -- */
        .notes {
            font-size: 11px;
            color: #6b7280;
            margin-top: 30px;
            padding: 0 30px;
        }

        .notes-label {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }

        /* -- Footer -- */
        .footer {
            margin-top: 50px;
            padding: 20px 30px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .footer-text {
            font-size: 10px;
            color: #9ca3af;
        }

        .signature-area {
            margin-top: 40px;
            text-align: right;
            padding-right: 30px;
        }

        .signature-line {
            width: 200px;
            border-top: 1px solid #1f2937;
            display: inline-block;
            padding-top: 5px;
            font-size: 11px;
            color: #6b7280;
        }

        p {
            padding: 0 0 0 0;
            margin: 0 0 0 0;
        }
    </style>

    @if (App::isLocale('th'))
        @include('app.pdf.locale.th')
    @endif
</head>

<body>
    <!-- Header with Logo and Company Info -->
    <div class="header-container">
        <table width="100%">
            <tr>
                @if ($logo)
                    <td width="50%">
                        <img style="height:50px" class="header-logo" src="{{ \App\Space\ImageUtils::toBase64Src($logo) }}" alt="Company Logo">
                    </td>
                @else
                    @if ($payment->customer)
                        <td width="50%" style="padding-top:0px;">
                            <h1 class="header-logo">{{ $payment->customer->company->name }}</h1>
                        </td>
                    @endif
                @endif
                <td width="50%" style="text-align: right;" class="company-address">
                    {!! $company_address !!}
                </td>
            </tr>
        </table>
    </div>

    <!-- Receipt Title -->
    <div class="receipt-title">
        <h2>PAYMENT RECEIPT</h2>
    </div>

    <div class="content-wrapper">
        <!-- Patient Information Box -->
        <div class="patient-box">
            <div class="patient-name">{{ $payment->customer->name }}</div>
            <div class="patient-details">
                @if ($payment->customer->file_number)
                    File No: {{ $payment->customer->file_number }} &nbsp;|&nbsp;
                @endif
                @if ($payment->customer->phone)
                    Tel: {{ $payment->customer->phone }}
                @endif
            </div>
        </div>

        <!-- Payment Details Grid -->
        <table width="100%" style="margin-bottom: 20px;">
            <tr>
                <td style="width: 33%; padding: 10px; text-align: center; border: 1px solid #e5e7eb;">
                    <span class="label">Receipt Number</span><br>
                    <span class="value">{{ $payment->payment_number }}</span>
                </td>
                <td style="width: 33%; padding: 10px; text-align: center; border: 1px solid #e5e7eb;">
                    <span class="label">Date</span><br>
                    <span class="value">{{ $payment->formattedPaymentDate }}</span>
                </td>
                <td style="width: 34%; padding: 10px; text-align: center; border: 1px solid #e5e7eb;">
                    <span class="label">Payment Method</span><br>
                    <span class="value">{{ $payment->paymentMethod ? $payment->paymentMethod->name : 'Cash' }}</span>
                </td>
            </tr>
        </table>

        <!-- Invoice Reference (if linked to invoice) -->
        @if ($payment->invoice && $payment->invoice->invoice_number)
            <div class="invoice-ref">
                <strong>Invoice Reference:</strong> {{ $payment->invoice->invoice_number }}
            </div>
        @endif

        <!-- Amount Box with Words -->
        <div class="amount-box">
            <div class="amount-label">Amount Received</div>
            <div class="amount-value">{!! format_money_pdf($payment->amount, $payment->customer->currency) !!}</div>
            <div class="amount-words">
                {{ numberToWordsWithCurrency($payment->amount / 100, $payment->customer->currency->name ?? 'shillings') }}
            </div>
        </div>

        <!-- Signature Area -->
        <div class="signature-area">
            <div class="signature-line">
                Authorized Signature
            </div>
        </div>
    </div>

    <!-- Notes Section -->
    <div class="notes">
        @if ($notes)
            <div class="notes-label">Notes</div>
            {!! $notes !!}
        @endif
    </div>

    <!-- Footer -->
    <div class="footer">
        <p class="footer-text">
            Thank you for choosing {{ $payment->customer->company->name }}!
        </p>
    </div>
</body>

</html>
