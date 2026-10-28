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
            <p>La verificación de identidad puede realizarse mediante revisión humana o, cuando ATD habilite expresamente el modo automático, mediante Didit. El modo manual no incluye prueba de vida ni cotejo biométrico automático. En el modo Didit, la persona participa en una captura guiada que puede procesar imágenes de su documento, rostro, vídeo o señales de prueba de vida, comparación facial y datos técnicos de dispositivo/red para comprobar identidad y detectar suplantación. La modalidad se identifica en la solicitud y antes de iniciar la captura.</p>
            <p>Antes de iniciar Didit, el solicitante debe informar a la persona y obtener su autorización para este tratamiento conforme a la finalidad indicada; ATD registra la confirmación, versión del aviso y fecha, y el integrador conserva la evidencia que corresponda. Los archivos aportados para revisión manual no se envían automáticamente a Didit. Un resultado automatizado puede requerir revisión y no constituye por sí solo una decisión de contratación, acceso o elegibilidad. Puede consultar con ATD alternativas y solicitar revisión de una conclusión.</p>

            <h2>Confidencialidad y resguardo</h2>
            <p>La información sensible se maneja con acceso restringido, discreción operativa y medidas razonables de seguridad. Los resultados, documentos, datos relacionados con evaluaciones y hallazgos derivados de análisis asistido se tratan bajo criterios de confidencialidad y uso limitado.</p>
            <p>Los expedientes de personas se mantienen dentro de la cuenta del cliente, con acceso para sus usuarios autorizados y el personal de ATD habilitado para el caso. Las fichas y conclusiones se cifran en la base de datos; las imágenes y documentos se guardan en almacenamiento privado. Los correos y webhooks de seguimiento no incluyen cédulas, selfies ni conclusiones. La conservación y las solicitudes de supresión se gestionan con ATD; no existe borrado automático desde el portal.</p>

            <h2>Transferencias</h2>
            <p>Los datos no se venden ni se comparten con terceros para fines comerciales ajenos. Cuando un servicio requiera apoyo técnico, proveedores o cumplimiento legal, la información se compartirá solo en la medida necesaria y bajo criterios de protección.</p>
            <p>Cuando se utiliza el modo automático, Didit actúa como proveedor técnico de verificación y procesa los datos de captura y sus comprobaciones bajo las condiciones aplicables al servicio. ATD le comunica los datos esperados mínimos y una referencia de sesión; conserva una conclusión resumida y no descarga automáticamente las imágenes, vídeos o informes completos del proveedor. Consulte la <a href="https://didit.me/terms/privacy-policy/" rel="noopener noreferrer" target="_blank">política de privacidad de Didit</a>. La ubicación del tratamiento, subencargados, conservación, supresión y posibles transferencias internacionales deben quedar definidos con el proveedor y ser revisados por ATD antes de activar producción. No se afirma una residencia, plazo o garantía contractual que no haya sido confirmado.</p>
            <p>Versión del aviso para el flujo Didit: didit-2026-10-v1. ATD debe atender con el proveedor las solicitudes aplicables a sus datos de captura; el borrado de una cuenta ATD no implica por sí solo la eliminación de una sesión Didit. Escriba al contacto indicado en este sitio para ejercer sus derechos o plantear dudas antes de participar.</p>

            <h2>Derechos del titular</h2>
            <p>El titular puede solicitar acceso, rectificación, cancelación u oposición al tratamiento de sus datos, así como aclarar dudas sobre el manejo de información, escribiendo al correo indicado en el sitio web.</p>

            <h2>Cambios al aviso</h2>
            <p>Este aviso puede actualizarse para reflejar cambios operativos, legales o de servicio. La versión vigente estará disponible en esta página.</p>
        </section>
    </main>
</body>
</html>
