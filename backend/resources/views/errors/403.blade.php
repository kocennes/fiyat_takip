@extends('portal.ninja2020.layout.error')

@section('title', ctrans('texts.forbidden'))
@section('code', '403')
@section('message', ($exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.') ? __($exception->getMessage()) : ctrans('texts.forbidden'))
