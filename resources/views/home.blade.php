<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $settings['meta_description'] ?? '' }}">
    <title>{{ $settings['company_name'] ?? config('app.name') }} | Evaluación de confianza</title>
    @include('shared.favicon')
    <script src="{{ asset('assets/js/home-theme.js') }}?v={{ filemtime(public_path('assets/js/home-theme.js')) }}"></script>
    <link rel="preload" as="image" href="{{ asset('assets/images/art/hero-lattice-v1.webp') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/home.css') }}?v={{ filemtime(public_path('assets/css/home.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/contact-dialog.css') }}?v={{ filemtime(public_path('assets/css/contact-dialog.css')) }}">
    <script src="{{ asset('assets/js/contact.js') }}?v={{ filemtime(public_path('assets/js/contact.js')) }}" defer></script>
    <script src="{{ asset('assets/js/site.js') }}?v={{ filemtime(public_path('assets/js/site.js')) }}" defer></script>
</head>
<body class="home-v2">
    <a class="skip-link" href="#inicio">Saltar al contenido</a>
    <div class="header-sentinel" data-header-sentinel aria-hidden="true"></div>
    <header class="site-header" data-site-header>
        <a href="#inicio" class="brand" aria-label="{{ $settings['company_name'] ?? 'All True Detection' }}">
            <img src="{{ $brand['logo_url'] }}" alt="{{ $settings['company_name'] ?? 'All True Detection' }}">
        </a>

        <button class="nav-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle>
            <span></span>
            <span></span>
        </button>

        <nav class="site-nav" id="primary-navigation" data-site-nav aria-label="Navegación principal">
            <a href="#servicios">Servicios</a>
            <a href="#proceso">Proceso</a>
            <a href="#sectores">Sectores</a>
            <a href="#confidencialidad">Confidencialidad</a>
            <a href="#faq">FAQ</a>
            <a href="#contacto">Contacto</a>
            <a href="{{ route('portal.dashboard') }}">Portal de clientes</a>
        </nav>

        <a class="header-cta" href="#contacto" data-contact-open>{{ $settings['primary_cta_label'] ?? 'Solicitar evaluación' }}</a>
    </header>

    <main id="inicio">
        <section class="hero section-dark" data-art-section>
            <div class="hero-art" aria-hidden="true"><img src="{{ asset('assets/images/art/hero-lattice-v1.webp') }}" width="1536" height="1024" alt="" fetchpriority="high"></div>
            <div class="container hero__inner">
                <div class="hero__copy reveal">
                    <p class="eyebrow">Evaluación e investigación técnica</p>
                    <h1>{{ $settings['hero_title'] ?? 'Confianza verificable para decisiones' }} <span>{{ $settings['hero_accent'] ?? 'más seguras' }}</span></h1>
                    <p class="hero__lead">{{ $settings['hero_subtitle'] ?? '' }}</p>
                    <div class="hero__actions">
                        <a class="btn btn-primary" href="#contacto" data-contact-open>{{ $settings['primary_cta_label'] ?? 'Solicitar evaluación' }}</a>
                        <a class="text-link text-link--light" href="#servicios">Conocer servicios <span aria-hidden="true">↗</span></a>
                    </div>
                </div>
            </div>
        </section>

        <section class="proof-bar" aria-label="Pilares de confianza">
            <div class="container proof-grid">
                <article class="proof-item reveal">
                    <span class="proof-item__number">01</span>

                    <h2>Precisión</h2>
                    <p>Metodología clara, tecnología aplicada e IA como apoyo analítico.</p>
                </article>
                <article class="proof-item reveal">
                    <span class="proof-item__number">02</span>

                    <h2>Confidencialidad</h2>
                    <p>Información sensible con acceso limitado y manejo reservado.</p>
                </article>
                <article class="proof-item reveal">
                    <span class="proof-item__number">03</span>

                    <h2>Objetividad</h2>
                    <p>Evaluaciones prudentes, documentadas y basadas en evidencia.</p>
                </article>
                <article class="proof-item reveal">
                    <span class="proof-item__number">04</span>

                    <h2>Legalidad</h2>
                    <p>Procesos alineados a autorizaciones, límites y buenas prácticas.</p>
                </article>
            </div>
        </section>

        <section class="services section-light" id="servicios">
            <div class="container services-editorial">
                <div class="services-intro reveal">
                    <h2>La información correcta.<br>El servicio preciso.</h2>
                    <p>Explore nuestras áreas de trabajo. Seleccione un servicio para solicitarlo y darle seguimiento desde su cuenta.</p>
                    <a class="text-link" href="#contacto" data-service-orientation>¿No sabe por dónde empezar? Conversemos ↗</a>
                    <div class="services-art" aria-hidden="true"><img src="{{ asset('assets/images/art/services-evidence-v1.webp') }}" width="733" height="1100" alt="" loading="lazy" decoding="async"></div>
                </div>
                <div class="service-catalog reveal" aria-label="Catálogo de servicios">
                    <p class="catalog-heading">Nuestros servicios</p>
                    @php($families = [
                        ['name'=>'Evaluación de personas','codes'=>['polygraph','deception-detection','drug-screening','psychometric']],
                        ['name'=>'Verificación de información','codes'=>['profile-verification','home-visits','university-degree','exequatur']],
                        ['name'=>'Investigación y análisis','codes'=>['investigations','applied-ai']],
                        ['name'=>'Formación y equipamiento','codes'=>['training-equipment']],
                    ])
                    @php($hasExpandedFamily = false)
                    @foreach($families as $family)
                        @php($familyServices = collect($services)->whereIn('code',$family['codes']))
                        @if($familyServices->isNotEmpty())
                            @include('shared.service-family',['familyName'=>$family['name'],'familyServices'=>$familyServices,'expanded'=>!$hasExpandedFamily])
                            @php($hasExpandedFamily = true)
                        @endif
                    @endforeach
                    @php($otherServices = collect($services)->whereNotIn('code',array_merge(...array_column($families,'codes'))))
                    @if($otherServices->isNotEmpty())
                        @include('shared.service-family',['familyName'=>'Otros servicios','familyServices'=>$otherServices,'expanded'=>!$hasExpandedFamily])
                    @endif
                    @if(empty($services))<p>Por ahora no hay servicios habilitados para solicitudes. Utilice el formulario de contacto para consultar disponibilidad.</p>@endif
                    <p class="catalog-note">Su solicitud se revisa antes de cotizar. Los servicios se definen según el alcance y las autorizaciones de cada caso.</p>
                </div>
            </div>
        </section>

        <section class="risk-lab section-dark" id="riesgos" data-art-section>
            <img class="risk-art" src="{{ asset('assets/images/art/risk-topography-v1.webp') }}" width="1536" height="1024" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <div class="container risk-editorial">
                <div class="section-copy reveal"><p class="eyebrow">El riesgo es real</p><h2>Reduzca riesgos antes de que se conviertan en pérdidas</h2><p>Una contratación incorrecta, una verificación incompleta o una sospecha mal gestionada puede afectar seguridad, productividad y reputación.</p></div>
                <div class="risk-principles">
                    <article class="reveal"><h3>Detectar señales</h3><p>Identificar inconsistencias y organizar la información que merece una revisión.</p></article>
                    <article class="reveal"><h3>Entender el contexto</h3><p>Contrastar los hallazgos con fuentes, autorizaciones y antecedentes del caso.</p></article>
                    <article class="reveal"><h3>Decidir con criterio</h3><p>Convertir la evaluación técnica en información clara, prudente y documentada.</p></article>
                </div>
            </div>
        </section>

        <section class="human-section section-light" id="evaluacion-integral">
            <img class="human-art" src="{{ asset('assets/images/art/hero-lattice-v1.webp') }}" width="1536" height="1024" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <div class="container human-grid">
                <div class="section-copy reveal">
                    <p class="section-kicker">Más que pruebas</p>
                    <h2>Entendemos personas, contextos, datos y riesgos humanos</h2>
                    <p>Combinamos ciencia, experiencia, tecnología y análisis asistido por IA para entregar información confiable sin convertir la evaluación en una experiencia intimidante.</p>
                    <ul class="check-list">
                        <li>Examinadores certificados y con experiencia</li>
                        <li>Tecnología de apoyo, IA aplicada y análisis objetivo</li>
                        <li>Reportes claros, accionables y confidenciales</li>
                    </ul>
                </div>

                <figure class="media-panel reveal">
                    <img loading="lazy" decoding="async" width="1536" height="1024" src="{{ asset('assets/images/assessment-lab.jpg') }}" alt="Evaluación técnica en oficina con monitor de análisis">
                    <figcaption>Decisiones informadas con criterio técnico, datos e IA aplicada.</figcaption>
                </figure>
            </div>
        </section>

        <section class="process section-dark" id="proceso">
            <div class="container">
                <div class="section-heading reveal">
                    <p class="eyebrow">Nuestro proceso</p>
                    <h2>Metodología clara. Análisis inteligente. Resultados confiables.</h2>
                </div>

                <div class="process-lane" data-process-steps>
                    @foreach ($process as $step)
                        <article class="process-step reveal">
                            <span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3>{{ $step['title'] }}</h3>
                            <p>{{ $step['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="sectors section-light" id="sectores">
            <img class="sector-art" src="{{ asset('assets/images/art/sector-network-v1.webp') }}" width="1536" height="1024" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <div class="container">
                <div class="section-heading section-heading--dark reveal">
                    <p class="section-kicker">Soluciones por sector</p>
                    <h2>Cobertura para organizaciones que manejan personas, datos o activos sensibles</h2>
                </div>

                <div class="sector-grid">
                    @foreach ($sectors as $sector)
                        <article class="sector-item reveal">

                            <small>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</small>
                            <h3>{{ $sector }}</h3>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ethics section-dark" id="confidencialidad">
            <img class="ethics-art" src="{{ asset('assets/images/art/sector-network-v1.webp') }}" width="1536" height="1024" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <div class="container ethics-grid">
                <div class="section-copy reveal">
                    <p class="section-kicker">Confidencialidad, legalidad y ética</p>
                    <h2>Su información está protegida antes, durante y después del proceso</h2>
                </div>
                <article class="ethics-card reveal">

                    <h3>Confidencialidad</h3>
                    <p>Protocolos para proteger datos del cliente y de la persona evaluada.</p>
                </article>
                <article class="ethics-card reveal">

                    <h3>Legalidad</h3>
                    <p>Autorizaciones, límites claros y criterios responsables de uso.</p>
                </article>
                <article class="ethics-card reveal">

                    <h3>Ética profesional</h3>
                    <p>Neutralidad, respeto, integridad y uso responsable de herramientas analíticas.</p>
                </article>
            </div>
        </section>

        <section class="training section-light" id="capacitacion">
            <div class="container training-grid">
                <div class="section-copy reveal">
                    <p class="section-kicker">Entrenamiento, tecnología e IA</p>
                    <h2>Capacite a su equipo. Equipe su operación.</h2>
                    <p>Programas de formación, equipos especializados y apoyo analítico con IA para elevar la capacidad de evaluación, prevención e investigación.</p>
                    <div class="hero__actions">
                        <a class="btn btn-primary" href="#contacto">Ver programas</a>
                        <a class="btn btn-outline" href="#contacto">Ver equipos</a>
                    </div>
                </div>

                <article class="training-card reveal">
                    <img loading="lazy" decoding="async" width="1536" height="1024" src="{{ asset('assets/images/training-room.jpg') }}" alt="Capacitación especializada en sala corporativa">
                    <h3>Capacitación especializada</h3>
                    <p>Cursos y talleres en poligrafía, entrevistas, investigación, seguridad corporativa y lectura de hallazgos analíticos.</p>
                </article>

                <article class="training-card reveal">
                    <img loading="lazy" decoding="async" width="1536" height="1024" src="{{ asset('assets/images/equipment-kit.jpg') }}" alt="Equipo profesional para evaluaciones técnicas">
                    <h3>Equipamiento profesional</h3>
                    <p>Sensores, materiales, tecnología de apoyo y análisis asistido para evaluaciones confiables.</p>
                </article>
            </div>
        </section>

        <section class="faq section-dark" id="faq">
            <div class="container faq-grid">
                <div>
                    <div class="section-heading reveal">
                        <p class="section-kicker">Preguntas frecuentes</p>
                        <h2>Resolvemos sus dudas</h2>
                    </div>

                    <div class="faq-list">
                        @foreach ($faqs as $faq)
                            <details class="faq-item reveal">
                                <summary>{{ $faq['q'] }}</summary>
                                <p>{{ $faq['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>

                <aside class="contact-card reveal" id="contacto">
                    <p class="eyebrow">Contacto confidencial</p>
                    <h2>Conversemos sobre su caso</h2>
                    <p>Reciba orientación inicial para identificar el servicio más adecuado y determinar si el análisis con IA puede aportar valor a su caso.</p>
                    @if(session('contact_status'))<p class="contact-feedback" role="status">{{ session('contact_status') }}</p>@endif
                    @if($errors->any())<p class="contact-feedback" role="alert">{{ $errors->first() }}</p>@endif
                    @include('shared.contact-form')
                    <a class="btn btn-primary" href="{{ $brand['whatsapp_url'] }}" target="_blank" rel="noreferrer">Hablar por WhatsApp</a>
                    <ul>
                        <li>{{ $settings['phone'] ?? '' }}</li>
                        <li>{{ $settings['email'] ?? '' }}</li>
                        <li>{{ $settings['schedule'] ?? '' }}</li>
                        <li>{{ $settings['address'] ?? '' }}</li>
                    </ul>

                    @if (! empty($socialLinks))
                        <div class="contact-socials" aria-label="Redes sociales">
                            @foreach ($socialLinks as $social)
                                <a class="social-button social-button--{{ $social['slug'] }}" href="{{ $social['url'] }}" target="_blank" rel="noreferrer" aria-label="{{ $social['label'] }}">
                                    @switch($social['slug'])
                                        @case('instagram')
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="5"></rect><circle cx="12" cy="12" r="3.5"></circle><circle cx="17" cy="7" r="1"></circle></svg>
                                            @break
                                        @case('tiktok')
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4v10.2a4.2 4.2 0 1 1-3.3-4.1"></path><path d="M14 4c.8 2.9 2.4 4.5 5 4.8"></path></svg>
                                            @break
                                        @case('facebook')
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8h2V4h-2.6C10.5 4 9 5.8 9 8.4V11H7v4h2v5h4v-5h2.5l.5-4h-3V8.7c0-.5.3-.7 1-.7Z"></path></svg>
                                            @break
                                        @case('youtube')
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.8 7.5c.3-1.2 1.2-2 2.4-2.2 2.9-.3 6.7-.3 9.6 0 1.2.2 2.1 1 2.4 2.2.4 2.1.4 6.9 0 9-.3 1.2-1.2 2-2.4 2.2-2.9.3-6.7.3-9.6 0-1.2-.2-2.1-1-2.4-2.2-.4-2.1-.4-6.9 0-9Z"></path><path d="m10 9 5 3-5 3V9Z"></path></svg>
                                            @break
                                        @default
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 5 14 14M19 5 5 19"></path></svg>
                                    @endswitch
                                </a>
                            @endforeach
                        </div>
                    @endif
                </aside>
            </div>
        </section>

        <section class="final-cta section-dark" data-art-section>
            <img class="closing-art" src="{{ asset('assets/images/art/risk-topography-v1.webp') }}" width="1536" height="1024" alt="" aria-hidden="true" loading="lazy" decoding="async">
            <div class="container reveal">
                <p class="section-kicker">Tome decisiones con información verificable e IA aplicada</p>
                <h2>Proteja su empresa, su equipo y su futuro</h2>
                <p>Solicite hoy una evaluación sin compromiso.</p>
                <div class="hero__actions hero__actions--center">
                    <a class="btn btn-primary" href="#contacto" data-contact-open>{{ $settings['primary_cta_label'] ?? 'Solicitar evaluación' }}</a>
                    <a class="btn btn-ghost" href="{{ $brand['whatsapp_url'] }}" target="_blank" rel="noreferrer">Hablar con un asesor</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <img src="{{ $brand['logo_url'] }}" alt="{{ $settings['company_name'] ?? 'All True Detection' }}">
                <p>{{ $settings['slogan'] ?? '' }}</p>
            </div>
            <div>
                <h2>Servicios</h2>
                <a href="#servicios">Evaluaciones con polígrafo</a>
                <a href="#servicios">Verificación de perfiles</a>
                <a href="#servicios">Análisis con IA aplicada</a>
                <a href="#servicios">Investigaciones</a>
                <a href="#servicios">Capacitación</a>
            </div>
            <div>
                <h2>Empresa</h2>
                <a href="#proceso">Proceso</a>
                <a href="#confidencialidad">Confidencialidad</a>
                <a href="{{ route('portal.dashboard') }}">Portal de clientes</a>
                <a href="{{ route('api.docs') }}">Documentación API</a>
            </div>
            <div>
                <h2>Contacto</h2>
                <p>{{ $settings['phone'] ?? '' }}</p>
                <p>{{ $settings['email'] ?? '' }}</p>
                <p>{{ $settings['address'] ?? '' }}</p>
            </div>
        </div>
        <div class="footer-bottom container">
            <span>&copy; {{ date('Y') }} {{ $settings['company_name'] ?? 'All True Detection' }}. Todos los derechos reservados.</span>
            <span><a href="{{ route('privacy') }}">Aviso de privacidad</a> | <a href="{{ route('terms') }}">Términos y condiciones</a></span><label class="theme-preference">Tema <select data-theme-preference aria-label="Tema del sitio"><option value="system">Sistema</option><option value="light">Claro</option><option value="dark">Oscuro</option></select></label>
        </div>
    </footer>
    @include('shared.contact-dialog')
</body>
</html>
