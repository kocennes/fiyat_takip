<!DOCTYPE html>
<html lang="tr" data-report-errors="{{ $report_errors }}" data-rc="{{ $rc }}" data-user-agent="{{ $user_agent }}" data-login="{{ $login }}">
<head>
    <!-- Version: {{ config('ninja.app_version') }} -->
  <meta charset="UTF-8">
  <title>{{ config('ninja.app_name') }}</title>
  <meta name="google-signin-client_id" content="{{ config('services.google.client_id') }}">

  @include('react.head')

</head>

<body class="h-full">
  <noscript>Bu uygulamayı kullanabilmek için tarayıcınızda JavaScript'i etkinleştirmeniz gerekir.</noscript>
  <div id="root"></div>

</body>
</html>
