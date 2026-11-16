<table style="width: 100%; background-color: #F9F9F9; margin-bottom: 22px;">
    <thead>
        <th colspan="2" style="color: #fff; font-size: 16px; line-height: 22px; font-weight: bold; padding: 15px 17px 13px; background-color: #369ECC;" align="left">
            Job Details:
        </th>
    </thead>
    <tbody>
        <tr>
            <td>
                <table style="padding: 12px 17px 14px; width: 100%">
                    <tr>
                        <td style="font-weight: bold">AtoZ ID:</td>
                        <td style="font-weight: 500">{{ $job->reference ?? $job->id }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold">Status:</td>
                        <td style="font-weight: 500;text-transform:capitalize">{{ $job->statusName ?? 'Pending' }}</td>
                    </tr>

                    @if ($wasSentToAdmin)
                        <tr>
                            <td style="font-weight: bold">Client:</td>
                            <td style="font-weight: 500;text-transform:capitalize">{{ optional(optional($job->client)->user)->getFullName() ?? 'N/A' }}</td>
                        </tr>
                        @if(isset($job->client->organisation))
                            <tr>
                                <td style="font-weight: bold"></td>
                                <td style="font-weight: 500;text-transform:capitalize">
                                    @if(isset($job->client->organisation->company))
                                        {{ optional($job->client->organisation->company)->name }}
                                    @else
                                        {{ optional($job->client->organisation)->organisation_company }}
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endif

                    @isset($quote) 
                    <tr>
                        <td style="font-weight: bold">Quote:</td>
                        <td style="font-weight: 500">{{ '£' . ($quote->cost ?? '0.00') }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold">Description:</td>
                        <td style="font-weight: 500; text-transform:capitalize">{{ $quote->cost_description ?? '' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold">Quote status:</td>
                        <td style="font-weight: 500; text-transform:capitalize">{{ $quote->status ?? '' }}</td>
                    </tr>
                    @endisset
                    
                    <tr>
                        <td style="font-weight: bold">Date:</td>
                        <td style="font-weight: 500">
                            @if(is_string($job->target_date))
                                {{ \Carbon\Carbon::parse($job->target_date)->format('d/m/Y') }}
                            @else
                                {{ optional($job->target_date)->format('d/m/Y') ?? 'N/A' }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold">Word Count:</td>
                        <td style="font-weight: 500">{{ $job->word_count ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold">Languages:</td>
                        <td style="font-weight: 500">{{ optional($job->fromLanguage)->name ?? 'N/A' }} - {{ optional($job->toLanguage)->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight: bold">Service type:</td>
                        <td style="font-weight: 500">{{ optional($job->skill)->skill ?? 'N/A' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>
</table>
