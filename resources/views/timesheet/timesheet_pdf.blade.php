{{-- # --}}
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet</title>
    <style>
        * {
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }

        .container {
            width: 100%;
            margin: 0 auto;
            border-radius: 6px;
            font-size: 14px;
        }

        .header,
        .footer {
            text-align: center;
            padding: 5px;
        }

        .form {
            margin: 15px;
        }

        .border-self {
            border: 1px solid black;
            border-radius: 3px;
            padding: 5px;
        }

        .contact-form h5 {
            border-bottom: 2px solid black;
            display: inline-block;
            padding-bottom: 2px;
            font-size: 17px;
            margin-bottom: 5px;
            margin-left: 5px;
        }

        .contact-form label {
            font-size: 14px;
            color: black;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .bottom-label p {
            font-size: 14px;
            font-weight: 500;
        }

        .contact-form {
            margin-top: 10px;
        }

        .para {
            font-size: 9px;
            font-weight: 400 !important;
        }

        .interpreter {
            border-top: 1px solid black;
        }

        .text-bold {
            font-size: 15px;
            font-weight: 700;
        }

        .ranking li {
            margin-right: 150px;
            font-size: 14px;
            font-weight: 500;
            list-style: none;
        }

        ul {
            list-style-type: none;
        }

        .bottom-border {
            border-bottom: 2px solid black;
            display: inline-block;
            padding-bottom: 2px;
            font-size: 17px;
            margin-bottom: 5px;
            margin-left: 5px;
        }

        .content {
            font-size: 13px;
            padding-left: 120px;
            margin-top: 15px;
            font-weight: 500;
        }

        .footer-para h6 {
            font-size: 15px;
            color: black;
        }

        .bottom-label {
            border-bottom: 1px solid black;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }

        .no-border {
            border: none;
        }

        .no-padding {
            padding: 0;
        }

        .text-center {
            text-align: center;
        }

        .mt-4 {
            margin-top: 10px;
        }

        .mt-3 {
            margin-top: 10px;
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .remove-m {
            margin: 0px !important;
        }

        .sigimg {
            margin-bottom: -10px;
            width: 100px;
            border: 1px solid;
            text-align: center;
            background-color: #ffffff;
        }

        .extra-margin {
            padding-left: 100px;
        }

        .d-flex {
            display: flex;
        }

        .span-box {
            padding-left: 5px;
        }

        .logo-img {
            display: flex;


        }

        .logo-img img {
            max-height: 100px;
            /* Adjust as needed */
        }

        .logo-img .logo-center {
            margin-left: auto;
        }

        .logo-img .agent-img {
            max-width: 50px;
            margin-right: auto;


        }
    </style>
</head>

<body>
    <div class="container">
        <header class="header">
            <div class="logo-img">
                @php
                    // Resolve local absolute file paths for images to avoid slow HTTP requests during PDF generation
                    $logoPath = public_path('img/logo-full-atoz.jpg');
                    $agentImgPath = null;
                    if (optional($timesheet->agent)->profile_picture) {
                        $candidate = storage_path('app/public/' . ltrim($timesheet->agent->profile_picture, '/'));
                        if (file_exists($candidate)) {
                            $agentImgPath = $candidate;
                        }
                    }
                @endphp
                <img src="{{ $logoPath }}" alt="Logo" class="logo-center" style="max-width: 250px;">
                @if($agentImgPath)
                    <img src="{{ $agentImgPath }}" alt="Agent" style="max-height: 100px; max-width: 100px;"
                        class="float-right">
                @endif
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
                            <td><b> Authorization / Job ID:</b> {{ $timesheet->job_id }}</td>
                            <td><b>Client Ref:</b> {{ optional($timesheet->interpreterJob)->client_reference ?? 'N/A' }}
                            </td>
                            <td><b>No. of Hours (booked): </b>
                                {{ sprintf('%d.%02d', (int) (optional($timesheet->interpreterJob)->duration_hours ?? 0), (int) (optional($timesheet->interpreterJob)->duration_minutes ?? 0)) }}
                            </td>
                        </tr>
                        <tr>
                            <td> <b>Client’s name: </b>
                                {{ trim(((optional(optional($timesheet->interpreterJob)->client)->userSheet->first_name ?? '') . ' ' . (optional(optional($timesheet->interpreterJob)->client)->userSheet->last_name ?? ''))) ?: 'N/A' }}
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td><b>Date:</b> {{ optional($timesheet->interpreterJob)->appointment_date }}</td>
                            <td><b>Time: </b> {{ optional($timesheet->interpreterJob)->start_time }}</td>
                        </tr>
                        <tr>
                            <td><b>Contact Name: </b>
                                {{ $timesheet->agentOne->user->first_name . $timesheet->agentOne->user->last_name }}
                            </td>
                            <td><b>Tel no. </b> {{ $timesheet->agentOne->contact_number }}</td>
                        </tr>
                    </table>
                </form>
            </div>
            <div class="contact-form border-self mt-3">
                <form action="">
                    <h5>Appointment Address:</h5>
                    <table>
                        <tr>
                            <td><b>Address line 1:</b>
                                {{ optional($timesheet->interpreterJob)->address_line_1 ?? 'N/A' }}</td>
                            <td><b>Address line 2:</b>
                                {{ optional($timesheet->interpreterJob)->address_line_2 ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td><b>County:</b> {{ optional($timesheet->interpreterJob)->county ?? 'N/A' }}</td>
                            <td><b>Postcode:</b> {{ optional($timesheet->interpreterJob)->postcode ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td colspan="2"><b>On arrival contact:</b>
                                {{ optional($timesheet->interpreterJob)->contact_information ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </form>
            </div>
            <section class="border-self mt-3">
                <div class="bottom-label p-3 ">
                    <table>
                        <tr>
                            <td colspan="6"><b>Language Required:</b>
                                {{ optional(optional($timesheet->interpreterJob)->toLanguage)->name }}
                            </td>
                            <td class="extra-margin">I declare that the information provided above is correct. I
                                understand that if I give information that is incorrect or incomplete, action may be
                                taken against me.</td>
                        </tr>
                        <tr>
                            <td colspan="6"><b> Arrival Time: </b>{{ optional($timesheet->interpreterJob)->start_time }}
                            </td>
                            <td class="extra-margin"> <b>Signed:</b>
                                @if(!empty($timesheet->agent_signature))
                                    <img class="sigimg" height="40px" width="80px"
                                        src="{{ $timesheet->agent_signature }}" />
                                @else
                                    <span class="sigimg" style="display:inline-block;height:40px;width:80px;"></span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6"><b> Interpreter’s
                                    Name:</b>{{ $timesheet->agentOne->user->getFullName() }}</td>
                            <td class="extra-margin"><b> Date:</b>
                                {{ optional($timesheet->interpreterJob)->appointment_date }}</td>
                        </tr>
                        <tr>
                            <td colspan="6"><b> Start time:</b> {{ $timesheet->agent_start_time }}</td>
                            <td class="extra-margin"><b>Agent Interpretation
                                    time:</b>{{ $timesheet->agent_duration_hours . ' H ' . $timesheet->agent_duration_minutes }}
                                M</td>
                        </tr>
                        <tr>
                            <td colspan="6"><b> End time:</b> {{ $timesheet->agent_end_time }}</td>
                            <td class="extra-margin"><b>Total interpreting time :
                                </b>{{ (optional($timesheet->interpreterJob)->duration_hours ?? 0) . 'H ' . (optional($timesheet->interpreterJob)->duration_minutes ?? 0) }}
                                M</td>
                        </tr>
                    </table>
                </div>
                <div class="signatory mt-4">
                    <h4 class="text-bold bottom-border">Signatory (Organisation):</h4>
                    <table>
                        <tr>
                            <td><b> Name: </b> {{ $timesheet->client_name }} </td>
                            <td><b>Signature: </b>
                                @if(!empty($timesheet->client_signature))
                                    <img class="sigimg" height="40px" width="80px"
                                        src="{{ $timesheet->client_signature }}" />
                                @else
                                    <span class="sigimg" style="display:inline-block;height:40px;width:80px;"></span>
                                @endif
                            </td>
                            <td><b> Designation: </b>{{ $timesheet->client_designation }} </td>
                        </tr>
                        <tr>
                            <td><b> Tel. No:</b> {{ $timesheet->client_phone }}</td>
                            <td><b> Appointment Time:</b> {{ optional($timesheet->interpreterJob)->start_time }}</td>
                            <td><b> Date:</b> {{ optional($timesheet->interpreterJob)->appointment_date }}</td>
                        </tr>
                        <tr>
                            <td><b> Time In (scheduled):</b> {{ optional($timesheet->interpreterJob)->start_time }}</td>
                            <td><b> Time Out (scheduled):</b> {{ optional($timesheet->interpreterJob)->end_time }}</td>
                            <td><b>Client Reference:</b>
                                {{ optional($timesheet->interpreterJob)->client_reference ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </div>
            </section>
        </div>
        <footer class="footer mt-3">
            <p class="footer-para">
                Issue 1 QMF31 28th February 2013
            </p>
            <div class="footer-para">
                <h6> Please return completed timesheet to: timesheets@atozinterpreting.com</h6>
            </div>
            @php
                $footerLogo = public_path('img/logo.jpg');
            @endphp
            <img src="{{ $footerLogo }}" alt="Footer Logo" style="width: 100%; height:150px;">
        </footer>

    </div>
</body>

</html>