<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $settings['meta_description'] ?? '' }}">
    <title>{{ $settings['company_name'] ?? config('app.name') }} | Evaluación de confianza</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/images/favicon-32.png') }}?v={{ filemtime(public_path('assets/images/favicon-32.png')) }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/images/favicon-192.png') }}?v={{ filemtime(public_path('assets/images/favicon-192.png')) }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/images/favicon-180.png') }}?v={{ filemtime(public_path('assets/images/favicon-180.png')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/site.css') }}?v={{ filemtime(public_path('assets/css/site.css')) }}">
    <script src="{{ asset('assets/js/site.js') }}?v={{ filemtime(public_path('assets/js/site.js')) }}" defer></script>
</head>
<body>
    <header class="site-header" data-site-header>
        <a href="#inicio" class="brand" aria-label="{{ $settings['company_name'] ?? 'All True Detection' }}">
            <img src="{{ $brand['logo_url'] }}" alt="{{ $settings['company_name'] ?? 'All True Detection' }}">
        </a>

        <button class="nav-toggle" type="button" aria-label="Abrir menú" aria-expanded="false" data-nav-toggle>
            <span></span>
            <span></span>
        </button>

        <nav class="site-nav" data-site-nav aria-label="Navegación principal">
            <a href="#servicios">Servicios</a>
            <a href="#proceso">Proceso</a>
            <a href="#sectores">Sectores</a>
            <a href="#confidencialidad">Confidencialidad</a>
            <a href="#faq">FAQ</a>
            <a href="#contacto">Contacto</a>
        </nav>

        <a class="header-cta" href="#contacto">{{ $settings['primary_cta_label'] ?? 'Solicitar evaluación' }}</a>
    </header>

    <main id="inicio">
        <section class="hero section-dark">
            <div class="tech-grid" aria-hidden="true"></div>
            <div class="container hero__inner">
                <div class="hero__copy reveal">
                    <p class="eyebrow">Evaluación de confianza, investigación técnica e IA aplicada</p>
                    <h1>{{ $settings['hero_title'] ?? 'Confianza verificable para decisiones' }} <span>{{ $settings['hero_accent'] ?? 'más seguras' }}</span></h1>
                    <p class="hero__lead">{{ $settings['hero_subtitle'] ?? '' }}</p>

                    <div class="hero__actions">
                        <a class="btn btn-primary" href="#contacto">{{ $settings['primary_cta_label'] ?? 'Solicitar evaluación' }}</a>
                        <a class="btn btn-ghost" href="#servicios">Conocer servicios</a>
                    </div>

                    <div class="assurance-strip" aria-label="Garantías de trabajo">
                        <span>Confidencialidad</span>
                        <span>Protocolos validados</span>
                        <span>Análisis asistido por IA</span>
                    </div>
                </div>

                <div class="trust-console reveal" aria-label="Panel visual de evaluación">
                    <div class="console-topline">
                        <span>Trust Intelligence Console + IA</span>
                        <strong>ATD-01</strong>
                    </div>

                    <div class="console-main">
                        <div class="fingerprint-panel">
                            <img src="{{ $brand['mark_url'] }}" alt="" aria-hidden="true">
                            <span class="radar-ring"></span>
                            <span class="radar-ring radar-ring--delay"></span>
                        </div>

                        <div class="signal-panel">
                            <p>Señal de integridad analizada</p>
                            <svg viewBox="0 0 320 120" role="img" aria-label="Gráfico de señal">
                                <path class="grid-line" d="M0 30H320M0 60H320M0 90H320M64 0V120M128 0V120M192 0V120M256 0V120"/>
                                <path class="signal-line" d="M4 78 C25 72 28 45 49 53 S75 88 98 70 128 24 154 43 183 92 207 69 229 46 252 57 274 77 316 40"/>
                                <path class="signal-glow" d="M4 78 C25 72 28 45 49 53 S75 88 98 70 128 24 154 43 183 92 207 69 229 46 252 57 274 77 316 40"/>
                            </svg>
                        </div>
                    </div>

                    <div class="console-metrics">
                        <article>
                            <span>Integridad</span>
                            <strong>92%</strong>
                        </article>
                        <article>
                            <span>Riesgo</span>
                            <strong>Bajo</strong>
                        </article>
                        <article>
                            <span>Confiabilidad</span>
                            <strong>94%</strong>
                        </article>
                    </div>

                    <div class="console-orbit" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="proof-bar" aria-label="Pilares de confianza">
            <div class="container proof-grid">
                <article class="proof-item reveal">
                    <span class="proof-item__number">01</span>
                    <span class="proof-item__trace" aria-hidden="true"></span>
                    <h2>Precisión</h2>
                    <p>Metodología clara, tecnología aplicada e IA como apoyo analítico.</p>
                </article>
                <article class="proof-item reveal">
                    <span class="proof-item__number">02</span>
                    <span class="proof-item__trace" aria-hidden="true"></span>
                    <h2>Confidencialidad</h2>
                    <p>Información sensible con acceso limitado y manejo reservado.</p>
                </article>
                <article class="proof-item reveal">
                    <span class="proof-item__number">03</span>
                    <span class="proof-item__trace" aria-hidden="true"></span>
                    <h2>Objetividad</h2>
                    <p>Evaluaciones prudentes, documentadas y basadas en evidencia.</p>
                </article>
                <article class="proof-item reveal">
                    <span class="proof-item__number">04</span>
                    <span class="proof-item__trace" aria-hidden="true"></span>
                    <h2>Legalidad</h2>
                    <p>Procesos alineados a autorizaciones, límites y buenas prácticas.</p>
                </article>
            </div>
        </section>

        <section class="services section-light" id="servicios">
            <div class="container">
                <div class="section-heading section-heading--dark reveal">
                    <p class="eyebrow eyebrow--green">Nuestros servicios</p>
                    <h2>Soluciones integrales para evaluar, verificar, analizar y prevenir</h2>
                    <p>Integramos métodos técnicos, criterio profesional y análisis asistido por IA para procesos de selección, controles internos, investigaciones y programas preventivos.</p>
                </div>

                <div class="service-layout">
                    <article class="service-spotlight reveal">
                        <p class="eyebrow eyebrow--green">Evaluación integral</p>
                        <h3>Un diagnóstico claro antes de elegir la prueba o el análisis.</h3>
                        <p>Cada caso inicia con contexto, objetivo y nivel de riesgo. Así definimos si conviene una prueba, una verificación, una investigación o un paquete de análisis con IA.</p>
                        <div class="mini-matrix">
                            <span>Perfil</span>
                            <span>Riesgo</span>
                            <span>IA</span>
                            <span>Informe</span>
                        </div>
                    </article>

                    <div class="service-cards">
                        @foreach ($services as $service)
                            <article class="service-card reveal">
                                <span class="service-card__icon" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3>{{ $service['title'] }}</h3>
                                <p>{{ $service['text'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="risk-lab section-dark" id="riesgos">
            <div class="container risk-grid">
                <div class="section-copy reveal">
                    <p class="eyebrow">El riesgo es real</p>
                    <h2>Reduzca riesgos antes de que se conviertan en pérdidas</h2>
                    <p>Una contratación incorrecta, una verificación incompleta o una sospecha mal gestionada puede afectar seguridad, productividad y reputación. El análisis asistido por IA ayuda a ordenar señales, detectar patrones y priorizar hallazgos.</p>
                    <ul class="check-list">
                        <li>Pérdidas económicas y operativas</li>
                        <li>Ambientes laborales vulnerables</li>
                        <li>Riesgos legales y de cumplimiento</li>
                        <li>Señales dispersas que pasan desapercibidas sin análisis especializado</li>
                    </ul>
                </div>

                <div class="risk-board reveal">
                    <article class="risk-card risk-card--large">
                        <span class="risk-sound" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                        <span class="risk-card__metric metric-number" data-count-from="30" data-count-to="60" data-prefix="30-" data-suffix="%">30-60%</span>
                        <p>de rotación relacionada con malas contrataciones</p>
                    </article>
                    <article class="risk-card">
                        <span class="risk-sound" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                        <span class="risk-card__metric metric-number" data-count-from="0" data-count-to="5" data-suffix="x">5x</span>
                        <p>menos riesgo cuando existe verificación previa</p>
                    </article>
                    <article class="risk-card risk-card--accent">
                        <span class="risk-sound" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                        <span class="risk-card__metric metric-number" data-count-from="0" data-count-to="70" data-suffix="%">70%</span>
                        <p>de incidentes internos pueden tener señales preventivas</p>
                    </article>
                    <article class="risk-card">
                        <span class="risk-sound" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                        <span class="risk-card__metric metric-number" data-count-from="24" data-count-to="48" data-prefix="24/" data-suffix="h">24/48h</span>
                    <p>rutas de trabajo definidas según urgencia, alcance y nivel de análisis</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="human-section section-light" id="evaluacion-integral">
            <div class="container human-grid">
                <div class="section-copy reveal">
                    <p class="eyebrow eyebrow--green">Más que pruebas</p>
                    <h2>Entendemos personas, contextos, datos y riesgos humanos</h2>
                    <p>Combinamos ciencia, experiencia, tecnología y análisis asistido por IA para entregar información confiable sin convertir la evaluación en una experiencia intimidante.</p>
                    <ul class="check-list">
                        <li>Examinadores certificados y con experiencia</li>
                        <li>Tecnología de apoyo, IA aplicada y análisis objetivo</li>
                        <li>Reportes claros, accionables y confidenciales</li>
                    </ul>
                </div>

                <figure class="media-panel reveal">
                    <img src="{{ asset('assets/images/assessment-lab.jpg') }}" alt="Evaluación técnica en oficina con monitor de análisis">
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
            <div class="container">
                <div class="section-heading section-heading--dark reveal">
                    <p class="eyebrow eyebrow--green">Soluciones por sector</p>
                    <h2>Cobertura para organizaciones que manejan personas, datos o activos sensibles</h2>
                </div>

                <div class="sector-grid">
                    @foreach ($sectors as $sector)
                        <article class="sector-item reveal">
                            <span class="sector-item__icon" aria-hidden="true"></span>
                            <small>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</small>
                            <h3>{{ $sector }}</h3>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ethics section-dark" id="confidencialidad">
            <div class="container ethics-grid">
                <div class="section-copy reveal">
                    <p class="eyebrow">Confidencialidad, legalidad y ética</p>
                    <h2>Su información está protegida antes, durante y después del proceso</h2>
                </div>
                <article class="ethics-card reveal">
                    <span class="ethics-card__icon" aria-hidden="true"></span>
                    <h3>Confidencialidad</h3>
                    <p>Protocolos para proteger datos del cliente y de la persona evaluada.</p>
                </article>
                <article class="ethics-card reveal">
                    <span class="ethics-card__icon" aria-hidden="true"></span>
                    <h3>Legalidad</h3>
                    <p>Autorizaciones, límites claros y criterios responsables de uso.</p>
                </article>
                <article class="ethics-card reveal">
                    <span class="ethics-card__icon" aria-hidden="true"></span>
                    <h3>Ética profesional</h3>
                    <p>Neutralidad, respeto, integridad y uso responsable de herramientas analíticas.</p>
                </article>
            </div>
        </section>

        <section class="training section-light" id="capacitacion">
            <div class="container training-grid">
                <div class="section-copy reveal">
                    <p class="eyebrow eyebrow--green">Entrenamiento, tecnología e IA</p>
                    <h2>Capacite a su equipo. Equipe su operación.</h2>
                    <p>Programas de formación, equipos especializados y apoyo analítico con IA para elevar la capacidad de evaluación, prevención e investigación.</p>
                    <div class="hero__actions">
                        <a class="btn btn-primary" href="#contacto">Ver programas</a>
                        <a class="btn btn-outline" href="#contacto">Ver equipos</a>
                    </div>
                </div>

                <article class="training-card reveal">
                    <img src="{{ asset('assets/images/training-room.jpg') }}" alt="Capacitación especializada en sala corporativa">
                    <h3>Capacitación especializada</h3>
                    <p>Cursos y talleres en poligrafía, entrevistas, investigación, seguridad corporativa y lectura de hallazgos analíticos.</p>
                </article>

                <article class="training-card reveal">
                    <img src="{{ asset('assets/images/equipment-kit.jpg') }}" alt="Equipo profesional para evaluaciones técnicas">
                    <h3>Equipamiento profesional</h3>
                    <p>Sensores, materiales, tecnología de apoyo y análisis asistido para evaluaciones confiables.</p>
                </article>
            </div>
        </section>

        <section class="faq section-dark" id="faq">
            <div class="container faq-grid">
                <div>
                    <div class="section-heading reveal">
                        <p class="eyebrow">Preguntas frecuentes</p>
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

        <section class="final-cta section-dark">
            <div class="container reveal">
                <p class="eyebrow">Tome decisiones con información verificable e IA aplicada</p>
                <h2>Proteja su empresa, su equipo y su futuro</h2>
                <p>Solicite hoy una evaluación sin compromiso.</p>
                <div class="hero__actions hero__actions--center">
                    <a class="btn btn-primary" href="#contacto">{{ $settings['primary_cta_label'] ?? 'Solicitar evaluación' }}</a>
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
                <a href="{{ route('admin.login') }}">Panel administrativo</a>
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
            <span><a href="{{ route('privacy') }}">Aviso de privacidad</a> | <a href="{{ route('terms') }}">Términos y condiciones</a></span>
        </div>
    </footer>
</body>
</html>
