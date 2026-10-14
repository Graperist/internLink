<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<title>Weekly Reports - {{ $student->user->name }}</title>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View></w:WordDocument></xml><![endif]-->
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; }
    h1 { font-size: 16pt; margin-bottom: 2pt; }
    h2 { font-size: 13pt; margin: 18pt 0 2pt; }
    .meta { color: #444; margin: 0 0 4pt; }
    table { border-collapse: collapse; width: 100%; margin-top: 6pt; }
    th, td { border: 1px solid #000; padding: 5pt; vertical-align: top; text-align: left; }
    th { background: #e9e9e9; }
    .range { color: #444; margin: 0 0 4pt; }
</style>
</head>
<body>
    <h1>Practicum/Internship Weekly Reports</h1>
    <p class="meta"><strong>Student:</strong> {{ $student->user->name }} ({{ $student->user->id_number }})</p>
    @if ($hte)
        <p class="meta"><strong>Host Training Establishment:</strong> {{ $hte->name }}</p>
    @endif

    @foreach ($weeks as $weekStart => $weekEntries)
        @php
            $start = \Carbon\Carbon::parse($weekStart);
            $end = $start->copy()->addDays(6);
        @endphp

        <h2>Week {{ $loop->iteration }}</h2>
        <p class="range">{{ $start->format('F d, Y') }} – {{ $end->format('F d, Y') }}</p>

        <table>
            <thead>
                <tr>
                    <th style="width:16%;">Date</th>
                    <th style="width:42%;">Tasks Accomplished</th>
                    <th>Knowledge, Skills, Values Learned</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($weekEntries as $entry)
                    <tr>
                        <td>{{ $entry->entry_date->format('M d, Y') }}</td>
                        <td>{!! nl2br(e($entry->value('tasks_accomplished'))) !!}</td>
                        <td>{!! nl2br(e($entry->value('knowledge_skills_learned'))) !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
