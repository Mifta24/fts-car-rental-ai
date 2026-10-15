<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-theme flex min-h-screen items-center justify-center">
    <div class="w-full max-w-sm rounded-3xl border border-slate-200 bg-slate-50 p-8">
        <span class="rental-monogram" aria-hidden="true">F</span>
        <h1 class="mt-6 text-2xl font-medium tracking-tight text-slate-900">Car Rental Admin</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to manage your fleet, bookings and AI Rental Agent.</p>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
                <input id="password" type="password" name="password" required
                    class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm focus:border-slate-500 focus:outline-none">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300">
                Remember me
            </label>
            <button type="submit" class="w-full rounded-full bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                Sign in
            </button>
        </form>
    </div>
</body>
</html>
