<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aviso de privacidad | All True Detection</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32.png') }}?v={{ filemtime(public_path('assets/images/favicon-32.png')) }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/images/favicon-192.png') }}?v={{ filemtime(public_path('assets/images/favicon-192.png')) }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/favicon-180.png') }}?v={{ filemtime(public_path('assets/images/favicon-180.png')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site.css') }}?v={{ filemtime(public_path('assets/css/site.css')) }}">
</head>
<body class="legal-body">
    <header class="legal-header">
        <a href="{{ route('home') }}" aria-label="Volver al inicio">
            <img src="{{ asset('assets/images/brand-lockup-light.png') }}" alt="All True Detection">
        </a>
        <nav>
            <a href="{{ route('home') }}">Inicio</a>
            <a href="{{ route('terms') }}">Términos y condiciones</a>
        </nav>
    </header>

    <main class="legal-page">
        <section class="legal-hero">
            <p class="eyebrow">Documento legal</p>
            <h1>Aviso de privacidad</h1>
            <p>Este aviso explica cómo All True Detection puede recopilar, usar y proteger información personal relacionada con consultas, solicitudes de evaluación, análisis asistido por IA y procesos administrativos.</p>
        </section>

        <section class="legal-content">
            <h2>Responsable del tratamiento</h2>
            <p>All True Detection es responsable del manejo de los datos proporcionados por clientes, prospectos, representantes empresariales y personas que soliciten información a través del sitio web o canales de contacto.</p>

            <h2>Datos que pueden recopilarse</h2>
            <p>Podemos solicitar nombre, empresa, cargo, teléfono, correo electrónico, ubicación, tipo de servicio requerido, cantidad de personas a evaluar, descripción general del caso y datos necesarios para responder una solicitud comercial, técnica, analítica o administrativa.</p>

            <h2>Finalidades</h2>
            <p>La información se usa para responder consultas, preparar cotizaciones, coordinar servicios, realizar análisis técnico o asistido por IA cuando corresponda, dar seguimiento a solicitudes, mejorar la atención, cumplir obligaciones administrativas y mantener comunicación relacionada con los servicios solicitados.</p>

            <h2>Confidencialidad y resguardo</h2>
            <p>La información sensible se maneja con acceso restringido, discreción operativa y medidas razonables de seguridad. Los resultados, documentos, datos relacionados con evaluaciones y hallazgos derivados de análisis asistido se tratan bajo criterios de confidencialidad y uso limitado.</p>

            <h2>Transferencias</h2>
            <p>Los datos no se venden ni se comparten con terceros para fines comerciales ajenos. Cuando un servicio requiera apoyo técnico, proveedores o cumplimiento legal, la información se compartirá solo en la medida necesaria y bajo criterios de protección.</p>

            <h2>Derechos del titular</h2>
            <p>El titular puede solicitar acceso, rectificación, cancelación u oposición al tratamiento de sus datos, así como aclarar dudas sobre el manejo de información, escribiendo al correo indicado en el sitio web.</p>

            <h2>Cambios al aviso</h2>
            <p>Este aviso puede actualizarse para reflejar cambios operativos, legales o de servicio. La versión vigente estará disponible en esta página.</p>
        </section>
    </main>
</body>
</html>
