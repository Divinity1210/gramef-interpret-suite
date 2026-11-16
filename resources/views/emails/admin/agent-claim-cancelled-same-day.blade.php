@extends('layouts.email')

@section('content')
    <thead>
        <th style="font-size: 28px; font-weight: bold; padding-bottom: 20px;">
            Claim submitted for same-day cancelled job {{ $job->reference }}
        </th>
    </thead>
    <tbody>
        <tr>
            <td>
                <p style="font-size: 16px; font-weight: 500; line-height: 22px; margin-top: 0px; margin-bottom: 27px;">
                    An interpreter has submitted a claim entry for the job
                    <span style="font-weight: bold">{{ $job->reference }}</span> which was cancelled on the appointment day.
                </p>
                <p style="font-size: 16px; font-weight: 500; line-height: 22px; margin-top: 0px; margin-bottom: 27px;">
                    Please review the claim and proceed with invoicing or adjustments as needed.
                </p>
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="text-align: center">
                    <tr>
                        <td>
                        <div>
                            <a href="{{ url('/') }}" style="background-color:#EFA847;font-weight: 500;border:1px solid #EFA847;border-radius:27.3649px;color:#ffffff;display:inline-block;font-family:sans-serif;font-size:16px;line-height:43px;text-align:center;text-decoration:none;width:150px;-webkit-text-size-adjust:none;">Portal Login</a>
                            <p style="margin-top:12px;">
                                <a href="{{ route('interpreter-jobs.edit', $job) }}" target="_blank"
                                   style="display:inline-block; padding:8px 12px; background:#1f2937; color:#fff; text-decoration:none; border-radius:4px;">
                                    Review Job
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
