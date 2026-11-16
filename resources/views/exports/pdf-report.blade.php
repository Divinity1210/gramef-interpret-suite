<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .summary h3 {
            margin: 0 0 10px 0;
            color: #007bff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status.approved { background: #d4edda; color: #155724; }
        .status.pending { background: #fff3cd; color: #856404; }
        .status.rejected { background: #f8d7da; color: #721c24; }
        .status.paid { background: #d1ecf1; color: #0c5460; }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>Generated on {{ now()->format('F d, Y \a\t H:i:s') }}</p>
        <p>Total Records: {{ count($data) }}</p>
    </div>

    @if(count($data) > 0)
        <div class="summary">
            <h3>Export Summary</h3>
            <p><strong>Total Records:</strong> {{ count($data) }}</p>
            @if(isset($data[0]['amount']))
                <p><strong>Total Amount:</strong> £{{ number_format(collect($data)->sum('amount'), 2) }}</p>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    @if(isset($data[0]['type']))
                        <th>Type</th>
                    @endif
                    @if(isset($data[0]['number']) || isset($data[0]['invoice_number']) || isset($data[0]['payslip_number']) || isset($data[0]['remittance_number']))
                        <th>Number</th>
                    @endif
                    @if(isset($data[0]['agent_client']))
                        <th>Agent/Client</th>
                    @endif
                    @if(isset($data[0]['amount']))
                        <th>Amount</th>
                    @endif
                    @if(isset($data[0]['status']))
                        <th>Status</th>
                    @endif
                    @if(isset($data[0]['date']) || isset($data[0]['created_at']))
                        <th>Date</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($data as $item)
                    <tr>
                        @if(isset($item['type']))
                            <td>{{ $item['type'] }}</td>
                        @endif
                        @if(isset($item['number']))
                            <td>{{ $item['number'] }}</td>
                        @elseif(isset($item['invoice_number']))
                            <td>{{ $item['invoice_number'] }}</td>
                        @elseif(isset($item['payslip_number']))
                            <td>{{ $item['payslip_number'] }}</td>
                        @elseif(isset($item['remittance_number']))
                            <td>{{ $item['remittance_number'] }}</td>
                        @endif
                        @if(isset($item['agent_client']))
                            <td>{{ $item['agent_client'] }}</td>
                        @endif
                        @if(isset($item['amount']))
                            <td class="amount">£{{ number_format($item['amount'], 2) }}</td>
                        @endif
                        @if(isset($item['status']))
                            <td><span class="status {{ strtolower($item['status']) }}">{{ ucfirst($item['status']) }}</span></td>
                        @endif
                        @if(isset($item['date']))
                            <td>{{ is_string($item['date']) ? $item['date'] : $item['date']->format('Y-m-d') }}</td>
                        @elseif(isset($item['created_at']))
                            <td>{{ is_string($item['created_at']) ? $item['created_at'] : $item['created_at']->format('Y-m-d H:i') }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="summary">
            <h3>No Data Found</h3>
            <p>No records match the specified criteria.</p>
        </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically by the AtoZ Invoice Management System.</p>
        <p>For questions or support, please contact the system administrator.</p>
    </div>
</body>
</html>