{{ $title }}

@isset($body)
{{ strip_tags(str_replace("<br>", "\r\n", $body)) }}
@endisset

@isset($content)
{{ strip_tags(str_replace("<br>", "\r\n", $content)) }}
@endisset

@isset($table)
    
    @foreach($table as $row)
        {{ implode("\t", array_values($row)) }}
    @endforeach
@endisset
