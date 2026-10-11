@extends('portal.ninja2020.layout.error')

@section('title', $title ?? ctrans('texts.error'))
@section('code', __($code) ?? '500')
@section('message', __($message) ?? ctrans('texts.bis_error_system'))
