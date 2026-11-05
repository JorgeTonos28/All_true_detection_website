# Corrección de comprobaciones CI — 5 de noviembre de 2026

El [run 41956526409](https://github.com/JorgeTonos28/All_true_detection_website/actions/runs/41956526409) del PR #1 falló en `composer audit --no-interaction` en ambas variantes. Instalación, validación Composer, Pint y pruebas habían pasado. La auditoría detectó nueve avisos publicados el 4 de noviembre, repartidos entre cuatro paquetes de Symfony 7.4.21.

## Corrección

| Paquete | Anterior | Corregido | Avisos detectados |
| --- | --- | --- | --- |
| `symfony/http-foundation` | 7.4.21 | 7.4.23 | 4 |
| `symfony/http-kernel` | 7.4.21 | 7.4.23 | 1 |
| `symfony/routing` | 7.4.21 | 7.4.23 | 1 |
| `symfony/serializer` | 7.4.21 | 7.4.23 | 3 |

Composer resolvió únicamente estos cuatro cambios de parche con restricciones temporales a 7.4.23 y `--with-dependencies --minimal-changes`. El lock conserva su content-hash, los requisitos de paquetes y la plataforma PHP 8.3; actualiza versiones, commits y referencias de descarga. El workflow mantiene sus controles de seguridad y su matriz.

Fuentes primarias: [CVE-2026-118514](https://symfony.com/cve-2026-118514), [CVE-2026-118515](https://symfony.com/cve-2026-118515), [CVE-2026-118516](https://symfony.com/cve-2026-118516), y los avisos oficiales [HttpFoundation: rutas](https://github.com/symfony/symfony/security/advisories/GHSA-3h27-hjv7-fqjx), [proxies](https://github.com/symfony/symfony/security/advisories/GHSA-xg2q-4rrq-657q), [URLs](https://github.com/symfony/symfony/security/advisories/GHSA-5mx8-mwrp-5693), [HttpKernel](https://github.com/symfony/symfony/security/advisories/GHSA-wg5q-m2ww-f3c9), [Serializer: URI](https://github.com/symfony/symfony/security/advisories/GHSA-pxj9-jh84-vhm5) y [propiedades](https://github.com/symfony/symfony/security/advisories/GHSA-hqp2-7rr8-hf26).

## Validación

El [run 42059024692](https://github.com/JorgeTonos28/All_true_detection_website/actions/runs/42059024692), commit `a302ee4`, terminó correctamente:

- PHP 8.3 + SQLite: 196 pruebas, 2.190 aserciones; auditoría limpia.
- PHP 8.4 + MySQL 8: 196 pruebas, 2.190 aserciones; auditoría limpia.
- Instalación desde el lock, `composer validate --strict` y Pint pasaron en ambos jobs.

La auditoría local del lock devolvió `advisories=[]` y `abandoned=[]`. En Windows hubo cortes de descarga; los archivos usados para completar la instalación local se verificaron contra los commits oficiales de los tags y el lock, en caché temporal aislada. La validación TLS se mantuvo habilitada. CI descargó e instaló las distribuciones habituales. Este informe refleja los avisos disponibles en esta fecha; la auditoría sigue consultándolos en cada ejecución.

El despliegue debe instalar el lock, regenerar rutas compiladas y reiniciar procesos persistentes según el runbook. Las bases de datos y los expedientes existentes se conservan.
