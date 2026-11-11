@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('app.admin_users.title'), 'url' => route('admin.admin-users.index')],
    ['label' => $admin->name, 'url' => route('admin.admin-users.show', $admin)],
]])

@section('title', __('app.admin_users.login_history').' — '.$admin->name)

@section('content')
    @include('admin.admin-users._logins-table', ['logins' => $logins, 'paginator' => $logins])
@endsection
