<h2>FBO Feed Load Report</h2>
<p>Generated: {{ now()->format('Y-m-d H:i:s') }}</p>

<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr>
            <th>Date</th>
            <th>File</th>
            <th>Status</th>
            <th>Entries Loaded</th>
            <th>Errors</th>
            <th>Message</th>
        </tr>
    </thead>
    <tbody>
        @foreach($results as $result)
            @if($result instanceof \App\Services\FBOFeed\LoadResult)
                <tr>
                    <td>{{ $result->date }}</td>
                    <td>{{ $result->filename }}</td>
                    <td>{{ $result->success ? 'Success' : 'Failed' }}</td>
                    <td>{{ $result->entriesLoaded }}</td>
                    <td>{{ $result->errorsCount }}</td>
                    <td>{{ $result->message }}</td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>
