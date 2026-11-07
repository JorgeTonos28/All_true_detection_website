<aside class="remote-interview section-dark" id="entrevista-remota" aria-labelledby="remote-interview-title">
    <div class="container remote-interview__layout">
        <div class="remote-interview__copy reveal">
            <p class="eyebrow">Evaluaciones a distancia</p>
            <h2 id="remote-interview-title">Entrevista remota<br class="remote-interview__break"> con IA</h2>
            <p class="remote-interview__lead">La persona participa desde su dispositivo. Una entrevista guiada por IA se combina con la medición del pulso por cámara, sin brazaletes ni sensores de contacto.</p>
            <a class="btn btn-primary" href="{{ route('services.request', ['service' => $remoteInterview['code']]) }}">Solicitar entrevista remota <span aria-hidden="true">↗</span></a>
            <p class="remote-interview__note">Tecnología descrita en la patente estadounidense US 12,023,160 B1.</p>
        </div>

        <div class="remote-interview__method reveal">
            <figure class="remote-interview__signal">
                <div class="remote-interview__signal-heading">
                    <strong>Pulso por cámara</strong>
                    <span>Señal ilustrativa</span>
                </div>
                <svg viewBox="0 0 480 98" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                    <path class="remote-interview__trace" vector-effect="non-scaling-stroke" d="M0 69 C12 69 25 66 38 67 S57 74 68 65 C77 57 80 35 88 23 S101 44 109 55 C119 70 128 56 139 60 S157 73 177 68 C191 64 204 67 217 68 C231 69 242 61 250 43 S264 17 271 29 S282 58 291 59 C304 60 306 52 318 61 S339 71 357 67 C370 65 383 68 395 63 S413 28 421 22 S434 46 442 55 S456 58 465 60 S476 66 480 66"/>
                </svg>
                <figcaption>Fotopletismografía remota (rPPG)</figcaption>
            </figure>
            <ol class="remote-interview__steps" aria-label="Cómo funciona la entrevista remota">
                <li><strong>Consentimiento informado</strong><span>La persona conoce el procedimiento y lo autoriza antes de participar.</span></li>
                <li><strong>Entrevista y medición</strong><span>La IA adapta sus preguntas mientras la cámara registra variaciones del pulso.</span></li>
                <li><strong>Revisión profesional</strong><span>Un analista interpreta el resultado orientativo y documenta sus límites en el informe.</span></li>
            </ol>
        </div>
    </div>
</aside>
