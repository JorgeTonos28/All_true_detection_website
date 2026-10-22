<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel administrativo | All True Detection</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32.png') }}?v={{ filemtime(public_path('assets/images/favicon-32.png')) }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/images/favicon-192.png') }}?v={{ filemtime(public_path('assets/images/favicon-192.png')) }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/favicon-180.png') }}?v={{ filemtime(public_path('assets/images/favicon-180.png')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site.css') }}?v={{ filemtime(public_path('assets/css/site.css')) }}">
</head>
<body class="admin-dashboard-body">
    <header class="admin-topbar">
        <a href="{{ route('home') }}" class="admin-topbar__brand">
            <img src="{{ asset('assets/images/brand-lockup-dark.png') }}" alt="All True Detection">
        </a>

        <nav>
            <a href="{{ route('home') }}" target="_blank" rel="noreferrer">Ver web</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit">Cerrar sesión</button>
            </form>
        </nav>
    </header>

    <main class="admin-dashboard">
        <section class="dashboard-hero">
            <div>
                <p class="eyebrow eyebrow--green">Panel administrativo</p>
                <h1>Información editable de la web</h1>
                <p>Esta primera versión administra identidad, contacto, redes sociales, llamado a la acción principal, SEO básico y titulares de la página de inicio.</p>
            </div>
            <div class="logo-preview">
                <span>Logo actual</span>
                <img src="{{ $logoUrl }}" alt="Logo actual">
            </div>
        </section>

        @if (session('status'))
            <div class="form-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="form-alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="settings-form">
            @csrf
            @method('PUT')

            <section class="settings-group">
                <div>
                    <p class="eyebrow eyebrow--green">Marca</p>
                    <h2>Logo del sitio</h2>
                    <p>Suba un PNG, JPG, WebP o SVG. Para fondos oscuros conviene una versión clara.</p>
                </div>
                <label class="file-field">
                    <span>Nuevo logo</span>
                    <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg">
                </label>
            </section>

            <section class="settings-group">
                <div>
                    <p class="eyebrow eyebrow--green">Cuenta</p>
                    <h2>Acceso administrativo</h2>
                    <p>Actualice el correo de ingreso y cambie la contraseña del usuario administrador actual.</p>
                </div>

                <div class="settings-fields">
                    <label>
                        <span>Correo administrador</span>
                        <input type="email" name="admin_email" value="{{ old('admin_email', $adminUser->email) }}" required autocomplete="email">
                    </label>

                    <label>
                        <span>Nueva contraseña</span>
                        <input type="password" name="admin_password" autocomplete="new-password" placeholder="Dejar vacío para conservar la actual">
                    </label>

                    <label>
                        <span>Confirmar nueva contraseña</span>
                        <input type="password" name="admin_password_confirmation" autocomplete="new-password">
                    </label>
                </div>
            </section>

            @foreach ($settings as $group => $items)
                <section class="settings-group">
                    <div>
                        <p class="eyebrow eyebrow--green">{{ $group }}</p>
                        <h2>{{ $group === 'Home' ? 'Contenido principal' : 'Datos de '.$group }}</h2>
                    </div>

                    <div class="settings-fields">
                        @foreach ($items as $setting)
                            @continue($setting->type === 'file')

                            <label>
                                <span>{{ $setting->label }}</span>
                                @if ($setting->type === 'textarea')
                                    <textarea name="{{ $setting->key }}" rows="4">{{ old($setting->key, $setting->value) }}</textarea>
                                @else
                                    <input type="{{ str_ends_with($setting->key, '_url') ? 'url' : 'text' }}" name="{{ $setting->key }}" value="{{ old($setting->key, $setting->value) }}" @if (str_ends_with($setting->key, '_url')) placeholder="https://..." @endif>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div class="settings-actions">
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
                <a class="btn btn-outline" href="{{ route('home') }}" target="_blank" rel="noreferrer">Previsualizar</a>
            </div>
        </form>
    </main>
</body>
</html>
