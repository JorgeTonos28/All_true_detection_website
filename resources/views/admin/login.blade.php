<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | All True Detection</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32.png') }}?v={{ filemtime(public_path('assets/images/favicon-32.png')) }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/images/favicon-192.png') }}?v={{ filemtime(public_path('assets/images/favicon-192.png')) }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/favicon-180.png') }}?v={{ filemtime(public_path('assets/images/favicon-180.png')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site.css') }}?v={{ filemtime(public_path('assets/css/site.css')) }}">
</head>
<body class="admin-body">
    <main class="admin-login">
        <section class="admin-login__panel">
            <a class="admin-login__brand" href="{{ route('home') }}">
                <img src="{{ asset('assets/images/brand-lockup-light.png') }}" alt="All True Detection">
            </a>
            <p class="eyebrow">Panel administrativo</p>
            <h1>Gestión de información básica</h1>
            <p>Actualice logo, datos de contacto, titulares principales y enlaces de redes desde un panel privado.</p>
        </section>

        <section class="admin-card">
            <h2>Iniciar sesión</h2>
            <p>Ingrese con el usuario administrador creado para esta primera versión.</p>

            @if ($errors->any())
                <div class="form-alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" class="admin-form">
                @csrf
                <label>
                    <span>Correo</span>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                </label>

                <label>
                    <span>Contraseña</span>
                    <input type="password" name="password" required autocomplete="current-password">
                </label>

                <label class="check-field">
                    <input type="checkbox" name="remember" value="1">
                    <span>Recordar sesión</span>
                </label>

                <button class="btn btn-primary" type="submit">Entrar al panel</button>
            </form>
        </section>
    </main>
</body>
</html>
