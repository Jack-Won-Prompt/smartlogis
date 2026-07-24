@extends('errors.layout')
@section('code','403')
@section('title','접근 권한이 없습니다')
@section('message',$exception->getMessage() ?: '이 화면에 접근할 권한이 없습니다. 필요한 경우 본사 관리자에게 문의하세요.')
