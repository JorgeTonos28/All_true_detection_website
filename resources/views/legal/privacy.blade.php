<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aviso de privacidad | {{ $business['company_name'] ?? 'All True Detection' }}</title>
    @include('shared.favicon',['settings'=>$business])
    <link rel="stylesheet" href="{{ asset('assets/css/site.css') }}?v={{ filemtime(public_path('assets/css/site.css')) }}">
</head>
<body class="legal-body">
    <header class="legal-header">
        <a href="{{ route('home') }}" aria-label="Volver al inicio">
            <img src="{{ filled($business['logo_path'] ?? null) ? \Illuminate\Support\Facades\Storage::disk('public')->url($business['logo_path']) : asset('assets/images/brand-lockup-light.png') }}" alt="{{ $business['company_name'] ?? 'All True Detection' }}">
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
            <p>Para validaciones de título universitario, exequátur e identidad, el expediente puede incluir nombre y apellidos de la persona, número de cédula, dirección, finalidad autorizada, datos académicos o profesionales y, de forma opcional, fecha de nacimiento, teléfono y correo. Se reciben copias o fotografías de la cédula, documentos de apoyo y selfies cuando el servicio las requiere. El solicitante debe contar con autorización de la persona y aportar únicamente datos pertinentes.</p>

            <h2>Finalidades</h2>
            <p>La información se usa para responder consultas, preparar cotizaciones, coordinar servicios, realizar análisis técnico o asistido por IA cuando corresponda, dar seguimiento a solicitudes, mejorar la atención, cumplir obligaciones administrativas y mantener comunicación relacionada con los servicios solicitados.</p>
            <p>La verificación de identidad ofrecida actualmente consiste en revisión humana de la información y las imágenes aportadas. No se realizan pruebas de vida con movimiento ni cotejos biométricos automáticos en este flujo, y las selfies no se envían a proveedores biométricos externos. Cualquier incorporación futura de ese tratamiento deberá informarse antes de su activación y contar con las autorizaciones y condiciones aplicables.</p>

            <h2>Confidencialidad y resguardo</h2>
            <p>La información sensible se maneja con acceso restringido, discreción operativa y medidas razonables de seguridad. Los resultados, documentos, datos relacionados con evaluaciones y hallazgos derivados de análisis asistido se tratan bajo criterios de confidencialidad y uso limitado.</p>
            <p>Los expedientes de personas se mantienen dentro de la cuenta del cliente, con acceso para sus usuarios autorizados y el personal de ATD habilitado para el caso. Las fichas y conclusiones se cifran en la base de datos; las imágenes y documentos se guardan en almacenamiento privado. Los correos y webhooks de seguimiento no incluyen cédulas, selfies ni conclusiones. La conservación y las solicitudes de supresión se gestionan con ATD; no existe borrado automático desde el portal.</p>

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
