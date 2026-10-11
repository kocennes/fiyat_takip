{{ $title }}

@isset($body)
{{ strip_tags(str_replace("<br>", "\r\n", $body)) }}
@endisset

@isset($content)
{{ strip_tags(str_replace("<br>", "\r\n", $content)) }}
@endisset
