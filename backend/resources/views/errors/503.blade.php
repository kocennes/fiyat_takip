@extends('portal.ninja2020.layout.error')

@section('title', ctrans('texts.bis_error_service_unavailable'))
@section('code', '503')
@section('message', $exception->getMessage() ? __($exception->getMessage()) : ctrans('texts.bis_error_service_unavailable'))
