@extends('layouts.email')

@section('content')
    <thead>
        <th style="font-size: 28px; font-weight: bold; padding-bottom: 20px;">
            {{ $heading ?? ('The Timesheet for the job ' . ($job->reference ?? '') . ' is done successful') }}
        </th>
    </thead>
    <tbody>
        <tr>
            <td>
                <p style="font-size: 16px; font-weight: 500; line-height: 22px; margin-top: 0px; margin-bottom: 27px;">
                    {!! $bodyMessage ?? ('This is an email notification to inform you that both the agent and client signed the timesheet for this job <span style="font-weight: bold">' . e($job->reference ?? '') . '</span>.') !!}
                </p>
                <p style="font-size: 16px; font-weight: 500; line-height: 22px; margin-top: 0px; margin-bottom: 27px;">
                    Please login to print the timesheet.
                </p>
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="text-align: center">
                    <tr>
                        <td>
                        <div>
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ url('/') }}" style="height:36px;v-text-anchor:middle;width:150px;" arcsize="5%" strokecolor="#EFA847" fillcolor="#EFA847">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-weight: 500;font-family:Helvetica, Arial,sans-serif;font-size:16px;">Portal Login</center>
                            </v:roundrect>
                            <![endif]-->
                            <a href="{{ url('/') }}" style="background-color:#EFA847;font-weight: 500;border:1px solid #EFA847;border-radius:27.3649px;color:#ffffff;display:inline-block;font-family:sans-serif;font-size:16px;line-height:43px;text-align:center;text-decoration:none;width:150px;-webkit-text-size-adjust:none;mso-hide:all;">Portal Login</a>
                <p style="margin-top:12px;">
                    <a href="{{ url('/interpreter-jobs/'.$job->id.'/timesheet/pdf') }}" target="_blank"
                       style="display:inline-block; padding:8px 12px; background:#1f2937; color:#fff; text-decoration:none; border-radius:4px;">
                        Print Timesheet (PDF)
                    </a>
                </p>

                        </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>
@endsection
