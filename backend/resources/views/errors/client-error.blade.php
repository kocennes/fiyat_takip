@extends('portal.ninja2020.layout.client_error')

@section('title', __($title) ?: ctrans('texts.bis_error_server'))
@section('code', __($code) ?: '500')
@section('message', __($message) ?: ctrans('texts.bis_error_system'))
