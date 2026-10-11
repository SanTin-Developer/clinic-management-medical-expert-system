<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #6b7280; font-size: 10px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        td.value { text-align: right; }
        tbody tr:nth-child(even) { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Generated {{ $generatedAt }} by {{ $generatedBy }}</div>

    <table>
        <thead>
            <tr>
                <th>Section</th>
                <th>Label</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row[0] ?? '' }}</td>
                    <td>{{ $row[1] ?? '' }}</td>
                    <td class="value">{{ $row[2] ?? '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">No data available for this report.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
