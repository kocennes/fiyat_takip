@extends('portal.ninja2020.layout.error')

@section('title', ctrans('texts.too_many_requests'))
@section('code', '429')
@section('message', ctrans('texts.too_many_requests'))
