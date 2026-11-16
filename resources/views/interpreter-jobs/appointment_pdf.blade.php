{{-- # --}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment – Job #{{ $snapshot['job']['id'] }} (v{{ $snap->version }})</title>
    <style>
        * { padding: 0; margin: 0; box-sizing: border-box; }
        .container { width: 100%; margin: 0 auto; border-radius: 6px; font-size: 14px; }
        .header, .footer { text-align: center; padding: 5px; }
        .form { margin: 15px; }
        .border-self { border: 1px solid black; border-radius: 3px; padding: 5px; }
        .contact-form h5 { border-bottom: 2px solid black; display: inline-block; padding-bottom: 2px; font-size: 17px; margin-bottom: 5px; margin-left: 5px; }
        .contact-form label { font-size: 14px; color: black; font-weight: 600; margin-bottom: 4px; }
        .bottom-label p { font-size: 14px; font-weight: 500; }
        .contact-form { margin-top: 10px; }
        .para { font-size: 9px; font-weight: 400 !important; }
        .interpreter { border-top: 1px solid black; }
        .text-bold { font-size: 15px; font-weight: 700; }
        .ranking li { margin-right: 150px; font-size: 14px; font-weight: 500; list-style: none; }
        ul { list-style-type: none; }
        .bottom-border { border-bottom: 2px solid black; display: inline-block; padding-bottom: 2px; font-size: 17px; margin-bottom: 5px; margin-left: 5px; }
        .content { font-size: 13px; padding-left: 120px; margin-top: 15px; font-weight: 500; }
        .footer-para h6 { font-size: 15px; color: black; }
        .bottom-label { border-bottom: 1px solid black; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; font-size: 14px; }
        .no-border { border: none; }
        .no-padding { padding: 0; }
        .text-center { text-align: center; }
        .mt-4 { margin-top: 10px; }
        .mt-3 { margin-top: 10px; }
        .mb-0 { margin-bottom: 0; }
        .remove-m { margin: 0px !important; }
        .sigimg { margin-bottom: -10px; width: 100px; border: 1px solid; text-align: center; }
        .extra-margin { padding-left: 100px; }
        .d-flex { display: flex; }
        .span-box { padding-left: 5px; }
        .logo-img { display: flex; }
        .logo-img img { max-height: 100px; }
        .logo-img .logo-center { margin-left: auto; }
        .logo-img .agent-img { max-width: 50px; margin-right: auto; }
        .audit { font-size: 11px; color: #444; }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <div class="logo-img">
                @php
                    $logoPath = public_path('img/logo-full-atoz.jpg');
                @endphp
                <img src="{{ $logoPath }}" alt="Logo" class="logo-center" style="max-width: 250px;">
            </div>
        </header>
        <div class="form">
            <div class="form-content border-self">
                <h4><b>APPOINTMENT RECORD</b></h4>
            </div>
            <div class="contact-form border-self">
                <form action="">
                    <h5>Contact Details:</h5>
                    <table>
                        <tr>
                            <td><b> Authorization / Ref No:</b> {{ $snapshot['job']['id'] }} / {{ $snapshot['job']['client_reference'] ?? 'N/A' }}</td>
                            <td><b>No. of Hours (booked): </b> {{ $snapshot['job']['duration_hours'] }}</td>
                        </tr>
                        <tr>
                            <td> <b>Client’s name: </b> {{ $snapshot['job']['client_name'] ?? 'N/A' }}</td>
                            <td><b>Language: </b> {{ $snapshot['job']['language'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td><b>Date:</b> {{ $snapshot['job']['appointment_date'] }}</td>
                            <td><b>Time: </b> {{ $snapshot['job']['start_time'] }}</td>
                        </tr>
                        <tr>
                            <td><b>Contact Name: </b> {{ $snapshot['agent']['name'] ?? 'N/A' }}</td>
                            <td><b>Tel no. </b> {{ $snapshot['agent']['phone'] ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </form>
            </div>
            <section class="border-self mt-3">
                <div class="bottom-label p-3 ">
                    <table>
                        <tr>
                            <td colspan="6"><b>Language Required:</b> {{ $snapshot['job']['language'] ?? 'N/A' }}</td>
                            <td class="extra-margin audit">
                                Snapshot v{{ $snap->version }} • SHA256 {{ $snap->hash }}<br/>
                                Source: {{ $snap->source ?? 'print' }} • Generated: {{ optional($snap->created_at)->format('Y-m-d H:i') }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6"><b> Arrival Time: </b>{{ $snapshot['job']['start_time'] }}</td>
                            <td class="extra-margin"> <b>Company:</b> {{ $snapshot['job']['company_name'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td colspan="6"><b> Interpreter’s Name:</b> {{ $snapshot['agent']['name'] ?? 'N/A' }}</td>
                            <td class="extra-margin"><b> Date:</b> {{ $snapshot['job']['appointment_date'] }}</td>
                        </tr>
                        <tr>
                            <td colspan="6"><b> Qualification:</b> {{ $snapshot['job']['qualification_required_label'] ?? 'N/A' }}</td>
                            <td class="extra-margin"><b> Security:</b> {{ $snapshot['job']['security'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td colspan="6"><b> Address:</b> {{ $snapshot['address']['line_1'] }} {{ $snapshot['address']['line_2'] }} {{ $snapshot['address']['county'] }} {{ $snapshot['address']['postcode'] }}</td>
                            <td class="extra-margin"><b> On-arrival contact:</b> {{ $snapshot['address']['on_arrival_contact'] ?? 'N/A' }}</td>
                        </tr>
                        @if(!empty($snapshot['address']['directions_url']))
                        <tr>
                            <td colspan="12"><b> Directions: </b> {{ $snapshot['address']['directions_url'] }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
                <div class="signatory mt-4">
                    <h4 class="text-bold bottom-border">Special Instructions</h4>
                    <table>
                        <tr>
                            <td colspan="12">{{ $snapshot['job']['special_requirements'] ?? 'None' }}</td>
                        </tr>
                    </table>
                </div>
            </section>
            <div class="signatory mt-4 border-self">
                <h4 class="text-bold bottom-border">Signatory Details</h4>
                <table>
                    <tr>
                        <td colspan="6"><b>Name:</b> ________________________________</td>
                        <td class="extra-margin"><b>Date:</b> __________________</td>
                    </tr>
                    <tr>
                        <td colspan="12" class="no-padding">
                            <div style="border:1px solid #000; height:100px; text-align:center; line-height:100px;">Signature</div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6"><b>Designation:</b> {{ $snapshot['job']['client_designation'] ?? 'N/A' }}</td>
                        <td class="extra-margin"><b>Tel. No:</b> {{ $snapshot['agent']['phone'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td colspan="12"><b>Client Reference / PO / Cost Code:</b> {{ $snapshot['job']['client_reference'] ?? 'N/A' }}</td>
                    </tr>
                </table>
            </div>

        </div>
        <footer class="footer mt-3">
            <p class="footer-para">Issue 1 QMF31 28th February 2013</p>
            <div class="footer-para">
                <h6> Please return completed timesheet to: timesheets@atozinterpreting.com</h6>
            </div>
            @php $footerLogo = public_path('img/logo.jpg'); @endphp
            <img src="{{ $footerLogo }}" alt="Footer Logo" style="width: 100%; height:150px;">
        </footer>

    </div>
</body>

</html>