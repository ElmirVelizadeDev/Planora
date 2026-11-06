<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F5F7FB">
    <link rel="icon" href="/images/branding/planora-favicon.ico" sizes="any" type="image/vnd.microsoft.icon">
    <link rel="icon" href="/images/branding/planora-favicon-32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="/images/branding/planora-favicon-16.png" sizes="16x16" type="image/png">
    <link rel="icon" href="/images/branding/planora-app-icon-192.png" sizes="192x192" type="image/png">
    <link rel="apple-touch-icon" href="/images/branding/planora-app-icon-192.png" sizes="192x192">
    <title>{{ config('app.name', 'Planora') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
