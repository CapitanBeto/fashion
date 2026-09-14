# Fashion Intelligence — Guía de implementación completa

Este documento explica exactamente qué hace cada archivo, dónde va, y en qué orden ejecutar
cada paso. Léelo de arriba a abajo la primera vez; después úsalo como referencia.

---

## PARTE 1 — PRERREQUISITOS

Instala estas herramientas antes de tocar cualquier archivo del proyecto.

### 1.1 Software requerido

```
PHP 8.2+          https://www.php.net/downloads
Composer 2+       https://getcomposer.org
Node.js 18+       https://nodejs.org  (para npm run build si usas Vite)
PostgreSQL 16+    https://www.postgresql.org/download/
Git               https://git-scm.com
```

Para el Python engine (Parte 2):
```
Python 3.11+      https://www.python.org/downloads/
Ollama            https://ollama.com   (LLM local gratuito)
```

### 1.2 Crear el proyecto Laravel base

Nuestros archivos NO son un proyecto Laravel completo — son los archivos propios del sistema
que se agregan ENCIMA de un Laravel nuevo. Primero crea el proyecto:

```bash
composer create-project laravel/laravel fashion-intelligence-backend "^11.0"
cd fashion-intelligence-backend
```

Ahora tienes la estructura base de Laravel. Los pasos siguientes agregan nuestros archivos.

### 1.3 Crear la base de datos PostgreSQL

```bash
# Conectarse a PostgreSQL
psql -U postgres

# Dentro de psql:
CREATE DATABASE fashion_intelligence;
CREATE USER fi_user WITH PASSWORD 'tu_password_seguro';
GRANT ALL PRIVILEGES ON DATABASE fashion_intelligence TO fi_user;

# Instalar extensión pgvector (requerida para embeddings de imágenes)
# En Ubuntu/Debian:
\q
sudo apt install postgresql-16-pgvector

# En macOS con Homebrew:
brew install pgvector
```

---

## PARTE 2 — INSTALAR ARCHIVOS LARAVEL

Copia los archivos del paquete al proyecto Laravel que creaste. La estructura del paquete
refleja exactamente la estructura de carpetas de Laravel.

```bash
# Desde la carpeta del paquete extraído, copia todo al proyecto:
cp -r fashion-intelligence/* fashion-intelligence-backend/

# Si estás en Windows usa xcopy o simplemente arrastra las carpetas con el explorador.
```

A continuación, qué hace cada archivo y por qué existe.

---

## SECCIÓN A — COMPOSER Y DEPENDENCIAS

### `composer.json`

**Qué es:** Define todas las dependencias PHP del proyecto.
**Dónde va:** raíz del proyecto (reemplaza el composer.json que creó Laravel).
**Qué agrega sobre el Laravel base:**
- `laravel/sanctum` — autenticación API con tokens
- `spatie/laravel-query-builder` — filtrado avanzado en la API
- `spatie/laravel-data` — DTOs tipados para respuestas estructuradas
- `livewire/livewire` — componentes reactivos para el dashboard
- `pgvector/pgvector` — soporte de vectores para búsqueda semántica de imágenes

**Paso:**
```bash
cd fashion-intelligence-backend
composer install
```

---

## SECCIÓN B — CONFIGURACIÓN

### `config/fashion.php`

**Qué es:** Configuración central del sistema. Todos los parámetros configurables en un lugar.
**Dónde va:** `config/fashion.php`
**Qué contiene:**
- URL y secret del Python engine
- Modelo LLM (Ollama/OpenAI/Anthropic)
- Límites de scraping (modo FREE/MANAGED/FULL, presupuesto máximo, concurrencia)
- Credenciales de proveedores de scraping (Apify, Zyte, etc.)
- Configuración de Reddit API
- Experimento inicial `hoodie_test_01` con keywords y subreddits
- Pesos de scoring por defecto (momentum, convergence, commercial opportunity)
- Umbrales del ciclo de vida (EMERGING → DEAD)

**Importante:** Todos los valores sensibles leen de `.env`. Este archivo NUNCA contiene
credenciales reales — solo llama a `env('VARIABLE', 'default')`.

### `.env.example`

**Qué es:** Plantilla de variables de entorno. Nunca se commitea el `.env` real.
**Dónde va:** raíz del proyecto.

**Paso:**
```bash
cp .env.example .env
php artisan key:generate
```

**Luego edita `.env` y completa:**
```
# Base de datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fashion_intelligence
DB_USERNAME=fi_user
DB_PASSWORD=tu_password_seguro

# Admin inicial
ADMIN_EMAIL=tu@email.com
ADMIN_PASSWORD=password_seguro

# Python engine (lo configuras en Parte 3)
PYTHON_ENGINE_URL=http://localhost:8001
PYTHON_ENGINE_SECRET=genera_un_string_aleatorio_largo

# Reddit API (gratis, crear app en reddit.com/prefs/apps)
REDDIT_CLIENT_ID=
REDDIT_CLIENT_SECRET=
REDDIT_USER_AGENT=FashionIntelligence/1.0 (by /u/tu_usuario)
```

---

## SECCIÓN C — MIGRACIONES (Base de datos)

Las migraciones crean todas las tablas en orden. Ejecutarlas en orden numérico es obligatorio
porque hay foreign keys entre tablas.

### `database/migrations/2024_01_01_000001_create_reference_tables.php`

**Crea:** `countries`, `niches`, `silhouettes`, `graphics`, `colors`, `materials`,
`scoring_parameters`, `prompt_versions`

Estas son las tablas de referencia que no cambian frecuentemente. `scoring_parameters`
almacena los pesos de los algoritmos de scoring — modificables desde la BD sin tocar código.
`prompt_versions` guarda los prompts del LLM versionados, para poder hacer A/B testing.

También activa las extensiones de PostgreSQL: `vector` (pgvector) y `uuid-ossp`.

### `database/migrations/2024_01_01_000002_create_sources_tables.php`

**Crea:** `sources`, `source_targets`, `scrape_runs`, `scraping_errors`, `data_quality_logs`

`sources` — cada fuente de datos (Google Trends, Reddit, Web, Apify, etc.)
`source_targets` — qué rastrear en cada fuente (subreddits específicos, keywords, URLs)
`scrape_runs` — log de cada ejecución de scraping con stats, costo, duración, errores
`scraping_errors` — errores individuales por URL para poder reintentar

### `database/migrations/2024_01_01_000003_create_raw_data_tables.php`

**Crea:** `raw_documents`, `raw_posts`, `raw_products`, `raw_images`

Almacena los datos crudos tal como llegan del scraper, antes de cualquier procesamiento.
La filosofía es: nunca destruir la fuente original. Si el NLP se equivoca, puedes reprocesar.
`content_hash` evita duplicados — si ya tienes ese documento, no lo vuelves a guardar.

### `database/migrations/2024_01_01_000004_create_entity_tables.php`

**Crea:** `brands`, `products`, `creators`, `communities`, `entities`, `entity_relationships`,
`price_observations`, `collaborations`

Las entidades del knowledge graph. `entities` y `entity_relationships` implementan el grafo:
Brand → adopts → Trend, Creator → wears → Product, etc.
`price_observations` registra histórico de precios para detectar descuentos (señal de saturación).

### `database/migrations/2024_01_01_000005_create_trend_tables.php`

**Crea:** `trends`, `trend_mentions`, `trend_snapshots`, `trend_lifecycle`,
`brand_trends`, `creator_trends`, `product_trends`, `trend_countries`,
`trend_colors`, `trend_silhouettes`, `trend_graphics`,
`engagement_metrics`, `search_metrics`

El corazón del sistema. `trends` tiene todos los scores (0-100) y el lifecycle stage.
`trend_snapshots` guarda series de tiempo diarias — es lo que alimenta los gráficos.
Las tablas pivot (brand_trends, creator_trends, etc.) registran quién adoptó qué trend y cuándo.

### `database/migrations/2024_01_01_000006_create_intelligence_tables.php`

**Crea:** `commercial_opportunities`, `competitors`, `content_features`,
`advertising_patterns`, `trend_predictions`, `prediction_outcomes`, `model_runs`

La capa de inteligencia. `commercial_opportunities` es la salida del módulo Creative Director
— sugiere nombres de producto, siluetas, colores, precio y audiencia.
`content_features` puede almacenar embeddings vectoriales de imágenes (512 dimensiones)
para búsqueda semántica — se activa automáticamente si pgvector está disponible.
`model_runs` loggea cada llamada al LLM para trazabilidad y backtesting.

**Paso — ejecutar todas las migraciones:**
```bash
php artisan migrate
```

Si hay error de extensión pgvector no disponible, el sistema la omite gracefully y
continúa sin embeddings de imagen.

---

## SECCIÓN D — MODELOS ELOQUENT

Los modelos son la interfaz PHP con la base de datos. Cada uno mapea a una tabla.

### `app/Models/Country.php`
Países con prioridad. El scope `active()` filtra por `active=true` ordenando por `priority`.
`getIso2Attribute()` garantiza que el código ISO siempre sea mayúscula.

### `app/Models/Niche.php`
Nichos de moda (streetwear, athleisure, etc.) con keywords de detección y keywords excluidas.
El scope `active()` ordena por prioridad — streetwear tiene prioridad 100 para el experimento.

### `app/Models/Color.php`, `Silhouette.php`, `Graphic.php`, `Material.php`
Catálogos de referencia. Todos tienen relaciones `BelongsToMany` con `Trend` y `Product`
a través de tablas pivot que guardan `frequency` y `percentage`.

### `app/Models/Brand.php`
Marcas detectadas. Campos clave:
- `price_position`: low/mid/premium/luxury
- `estimated_scale`: micro/small/medium/large/mega
- `entity_hash`: hash para deduplicación (entity resolution)
- `is_demo`: las marcas demo no aparecen en producción (scope `notDemo()`)

### `app/Models/Product.php`
Productos scrapeados. Tiene relaciones con colores, siluetas, gráficas y materiales a través
de tablas pivot. `getDominantColorAttribute()` y `getPrimaryMaterialAttribute()` son
computed properties para el frontend.

### `app/Models/Creator.php`
Influencers y creadores. `getTotalFollowersAttribute()` suma seguidores de todas las
plataformas. `getFollowersOnPlatformAttribute()` prioriza Instagram > TikTok.
`commercial_influence_score` (0-100) y `trend_influence_score` (0-100) son scores
calculados por el Python engine.

### `app/Models/Community.php`
Comunidades online (subreddits, canales, etc.). `getSubredditNameAttribute()` devuelve
el nombre sin el prefijo `r/`.

### `app/Models/Source.php` y `SourceTarget.php`
`Source` = la fuente (Reddit, Google Trends, Zyte).
`SourceTarget` = qué rastrear en esa fuente (subreddit `streetwear`, keyword `oversized hoodie`).
El scope `due()` en `SourceTarget` determina cuáles targets deben correr ahora según su
`frequency` (hourly/daily/weekly/manual).

### `app/Models/ScrapeRun.php`
Log de cada ejecución. Métodos clave: `markAsStarted()`, `markAsCompleted()`,
`markAsFailed()`, `appendLog()`. El UUID se genera automáticamente en `booted()`.
`getSuccessRateAttribute()` calcula `(requests_made - requests_failed) / requests_made`.

### `app/Models/RawDocument.php`, `RawPost.php`, `RawImage.php`
Datos crudos. `RawDocument` tiene métodos `markAsProcessing()`, `markAsProcessed()`,
`markAsFailed()` para el pipeline de procesamiento. `getWordCountAttribute()` es útil
para filtrar documentos muy cortos (probablemente sin valor).

### `app/Models/Trend.php`
El modelo central. Tiene ~15 scores (0-100), el lifecycle_stage, relaciones con todas
las entidades, y scopes muy usados:
- `rising()` — stages EMERGING/EARLY_ADOPTION/ACCELERATING
- `dying()` — stages DECLINING/SATURATED/DEAD
- `highOpportunity($min)` — filtra por commercial_opportunity_score
- `emergent()` — solo trends descubiertos por clustering (no conocidos previamente)
- `forCountry($iso2)` — filtra por país a través de la tabla pivot
- `forNiche($slug)` — filtra por nicho

Computed properties: `lifecycle_label`, `lifecycle_color`, `is_rising`, `is_dying`,
`latest_snapshot`.

`recordSnapshot()` hace upsert del snapshot del día — evita duplicados si corre dos veces.

### `app/Models/TrendSnapshot.php`
Serie de tiempo diaria por trend y opcionalmente por país. `growth_velocity` es el cambio
semana-a-semana en porcentaje. `growth_acceleration` es el delta de la velocidad (si está
acelerando o frenando).

### `app/Models/TrendMention.php`
Cada mención de un trend en un documento. `signal_type` clasifica el tipo de señal:
search, social, community, creator, brand, product, sales_proxy, media.
`sentiment` va de -1 (muy negativo) a 1 (muy positivo).

### `app/Models/CommercialOpportunity.php`
La salida del módulo Creative Director. Contiene el concepto de producto sugerido:
nombre, silueta, colores, gráficas, rango de precio, audiencia objetivo, reasoning y risks.
`getPriceMidpointAttribute()` calcula el punto medio del rango sugerido.

### `app/Models/TrendPrediction.php`
Predicciones del forecaster (Prophet + XGBoost + LLM). Tiene probabilidades para
growth/decline/saturation/death y el `momentum_forecast` (serie de tiempo proyectada).
`getDominantOutcomeAttribute()` devuelve el outcome más probable.

### `app/Models/PromptVersion.php`
Prompts del LLM versionados en BD. `render($variables)` hace interpolación de
`{variable}` en el template. `getActive($key)` es el método más usado — devuelve
el prompt activo para una tarea dada.

### `app/Models/ScrapingError.php`, `PriceObservation.php`, `PredictionOutcome.php`
Modelos auxiliares. `PredictionOutcome` es para backtesting — compara predicciones
pasadas contra lo que realmente ocurrió. `getIsAccurateAttribute()` devuelve true
si el accuracy_score >= 70.

---

## SECCIÓN E — JOBS (Cola de trabajo)

Los jobs se ejecutan en segundo plano via `php artisan queue:work`.

### `app/Jobs/RunExperimentJob.php`

**Qué hace:** Dispara el pipeline completo de un experimento.
1. Lee la config del experimento desde `config/fashion.php`
2. Verifica que el Python engine esté activo
3. Llama a `PythonEngineClient::runExperiment()` — el engine hace todo el scraping y NLP
4. Cuando termina, despacha `CalculateScoresJob` con 30 segundos de delay

**Cómo lanzar manualmente:**
```bash
# Desde artisan (recomendado para el primer experimento)
php artisan experiment:run hoodie_test_01

# Desde código PHP
RunExperimentJob::dispatch('hoodie_test_01')->onQueue('experiments');
```

`$timeout = 3600` — 1 hora máxima, suficiente para el experimento hoodie.
`$tries = 1` — los experimentos no se reintentan automáticamente (son caros).

### `app/Jobs/ProcessDocumentJob.php`

**Qué hace:** Procesa un documento crudo individual (NLP, extracción de entidades).
El Python engine devuelve las menciones de trends, marcas y creators encontradas.
Si falla, reintenta hasta 3 veces en 10 minutos.

```bash
# Normalmente lo despacha el Python engine automáticamente.
# Para procesar uno manualmente:
ProcessDocumentJob::dispatch(123)->onQueue('default');
```

### `app/Jobs/CalculateScoresJob.php`

**Qué hace:** Pide al Python engine que recalcule todos los scores de un trend o experimento.
Después de recibir los resultados, actualiza directamente los campos en `trends`:
momentum_score, growth_score, lifecycle_stage, commercial_opportunity_score, etc.

Procesa los trends en chunks de 50 para no saturar la memoria.

---

## SECCIÓN F — SERVICIOS

### `app/Services/PythonEngineClient.php`

**Qué es:** El cliente HTTP que conecta Laravel con el Python engine.
**Todos los métodos:**
- `runExperiment($key, $config)` — lanza experimento completo
- `processDocument($payload)` — procesa un documento con NLP
- `calculateScores($trendId)` — recalcula scores de un trend
- `forecastTrend($trendId, $countryId)` — genera predicción Prophet+XGBoost+LLM
- `generateProductConcept($opportunityId)` — Creative Director: genera concepto de producto
- `ping()` — health check (3 segundos de timeout)
- `getScrapingStatus()` — modo actual, presupuesto usado, proveedores activos

Todos los métodos lanzan `RuntimeException` si el engine devuelve error, para que los
jobs puedan loggear y reintentar correctamente.

**Registrarlo como singleton (agregar a `AppServiceProvider`):**
```php
$this->app->singleton(PythonEngineClient::class);
```

---

## SECCIÓN G — CONTROLLERS API

La API está bajo `/api/v1`. Todas las rutas excepto login y health requieren token Sanctum.

### `app/Http/Controllers/Api/AuthController.php`

`POST /api/v1/auth/login` — devuelve un Bearer token
`POST /api/v1/auth/logout` — revoca el token actual
`GET  /api/v1/auth/me`    — datos del usuario autenticado

Ejemplo de login:
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@fi.local","password":"changeme123"}'
# Devuelve: {"token":"1|abc...","user":{...}}
```

### `app/Http/Controllers/Api/TrendController.php`

`GET /api/v1/trends` — lista paginada con filtros:
- `?lifecycle_stage=EMERGING` — filtrar por etapa
- `?niche=streetwear` — filtrar por nicho (slug)
- `?country=US` — filtrar por país (ISO2)
- `?min_opportunity=60` — score mínimo de oportunidad
- `?rising=1` — solo trends en alza
- `?q=hoodie` — búsqueda por nombre/keyword
- `?sort_by=momentum_score&sort_dir=desc` — ordenamiento
- `?per_page=20` — paginación (max 100)

`GET /api/v1/trends/{slug}` — detalle completo con snapshots, oportunidades, colores, etc.
`GET /api/v1/trends/{slug}/snapshots?days=90` — serie de tiempo para gráficos
`GET /api/v1/trends/{slug}/opportunities` — oportunidades comerciales del trend
`GET /api/v1/trends/rising` — top 20 trends en alza
`GET /api/v1/trends/opportunities?min_score=60` — todas las oportunidades comerciales

### `app/Http/Controllers/Api/ExperimentController.php`

`GET  /api/v1/experiments` — lista experimentos configurados
`POST /api/v1/experiments/{key}/run` — lanza experimento al queue
`GET  /api/v1/experiments/{key}/runs` — historial de runs paginado
`GET  /api/v1/experiments/{key}/status` — si está corriendo, último run
`POST /api/v1/experiments/{key}/rescore` — recalcula scores sin re-scraping
`GET  /api/v1/scrape-runs/{id}` — detalle de un run con log completo y errores
`GET  /api/v1/system/health` — estado del sistema (engine, queue)

### `app/Http/Controllers/Api/SourceController.php`

`GET   /api/v1/sources` — lista fuentes con conteo de targets activos
`POST  /api/v1/sources` — crear nueva fuente
`PATCH /api/v1/sources/{id}` — actualizar fuente (activar/desactivar, cambiar config)
`GET   /api/v1/sources/{id}/targets` — targets de una fuente
`POST  /api/v1/sources/{id}/targets` — agregar subreddit, keyword, URL, etc.
`PATCH /api/v1/sources/targets/{id}` — cambiar frecuencia, prioridad, activar/desactivar

---

## SECCIÓN H — API RESOURCES

Los Resources transforman modelos Eloquent a JSON con estructura consistente.

### `app/Http/Resources/TrendResource.php`
Serializa un Trend con todos sus scores agrupados bajo `"scores"`, lifecycle label y color,
conteos de brands/creators/products, último snapshot, top opportunity, países y colores.
Los campos de relaciones solo aparecen si el modelo fue `->load()`ado (evita N+1).

### `app/Http/Resources/CommercialOpportunityResource.php`
Agrupa los scores bajo `"scores"` y el concepto de producto bajo `"product_concept"`.
`price_midpoint` es un campo calculado que aparece automáticamente.

### `app/Http/Resources/ScrapeRunResource.php`
Agrupa stats de recolección bajo `"stats"` y costos bajo `"cost"`.
`success_rate` es un computed attribute del modelo.

---

## SECCIÓN I — SEEDERS (Datos iniciales)

Los seeders poblan la BD con datos de referencia reales. Orden de ejecución definido
en `DatabaseSeeder.php`.

### `database/seeders/CountrySeeder.php`
15 países con ISO2, ISO3, idioma, moneda y prioridad.
US, GB, CL tienen prioridad 100 (experimento inicial).
Usa `updateOrInsert` para poder re-ejecutar sin duplicar.

### `database/seeders/NicheSeeder.php`
7 nichos con keywords y excluded_keywords.
Streetwear tiene prioridad 100 y keywords específicas para el experimento hoodie.

### `database/seeders/ColorSeeder.php`
40+ colores con familia (neutral/blue/green/etc.), temperatura (warm/cool/neutral) y RGB.
Incluye colores de tendencia: Butter Yellow, Ice Blue, Mushroom, Acid Wash, etc.

### `database/seeders/SilhouetteSeeder.php`
17 siluetas con descripción técnica. Cubre desde Oversized hasta Bomber.

### `database/seeders/GraphicSeeder.php`
18 tipos de gráficas con placement (chest, back, all-over, sleeve).
Incluye: Collegiate Text, Vintage Distressed, Japanese/Kanji, Embroidered Patch, etc.

### `database/seeders/MaterialSeeder.php`
28 materiales. Incluye French Terry, Heavyweight Cotton, Waffle Knit, Sherpa, etc.

### `database/seeders/ScoringParameterSeeder.php`
Todos los pesos de los algoritmos en BD. Cuatro grupos:
- `lifecycle` — umbrales para clasificar la etapa del trend
- `momentum` — pesos del momentum score (sum = 1.0)
- `commercial_opportunity` — pesos del opportunity score (sum = 1.0)
- `convergence` — pesos por fuente (sum = 1.0)
- `signal_reliability` — confiabilidad por tipo de fuente (0-100)

Para ajustar el algoritmo sin tocar código:
```sql
UPDATE scoring_parameters
SET parameter_value = 0.35
WHERE parameter_group = 'momentum' AND parameter_key = 'growth_velocity_weight';
```

### `database/seeders/SourceSeeder.php`
6 fuentes (Google Trends, Reddit, Web, Apify, ScraperAPI, Zyte).
Apify/ScraperAPI/Zyte quedan con `active=false` — se activan cuando tengas las API keys.

También crea los `source_targets` del experimento hoodie:
- 7 subreddits en Reddit
- 11 keywords × 3 países en Google Trends (33 targets en total)

### `database/seeders/PromptVersionSeeder.php`
4 prompts de producción para el LLM:
- `entity_extraction` — extrae marcas, productos, colores, siluetas de un texto
- `lifecycle_classification` — clasifica etapa del ciclo de vida con reasoning
- `commercial_opportunity` — genera concepto de producto concreto
- `trend_forecast` — interpreta predicciones de Prophet+XGBoost en términos de negocio

### `database/seeders/AdminUserSeeder.php`
Crea el usuario administrador usando `ADMIN_EMAIL` y `ADMIN_PASSWORD` del `.env`.

**Ejecutar todos los seeders:**
```bash
php artisan db:seed
```

---

## SECCIÓN J — COMANDO ARTISAN

### `app/Console/Commands/RunExperimentCommand.php`

El comando `php artisan experiment:run` es la herramienta de trabajo principal.

```bash
# Uso básico (despacha al queue)
php artisan experiment:run hoodie_test_01

# Modo síncrono (corre en el terminal, bueno para depurar)
php artisan experiment:run hoodie_test_01 --sync

# Sobrescribir países para un test rápido
php artisan experiment:run hoodie_test_01 --countries=US

# Reducir período para prueba
php artisan experiment:run hoodie_test_01 --days=30 --sync
```

Antes de lanzar verifica que el Python engine responda. Si no está activo, lo dice
claramente y no despacha el job.

### `app/Console/Kernel.php`

Define tres tareas automáticas (cron):
- **Domingos 3am** — re-corre todos los experimentos configurados
- **Cada 6 horas** — recalcula scores de todos los trends
- **Mensual** — pruning de documentos viejos

Para activar el scheduler en desarrollo:
```bash
php artisan schedule:work
```

En producción (Linux), agregar al crontab del servidor:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## SECCIÓN K — LIVEWIRE (Dashboard)

Los componentes Livewire son páginas interactivas sin escribir JavaScript.

### `app/Livewire/TrendDashboard.php` + `resources/views/livewire/trend-dashboard.blade.php`

Dashboard principal con tabla de trends. Características:
- Búsqueda reactiva con debounce de 300ms
- Filtros por lifecycle stage, nicho y sorting
- Toggle "Rising only"
- Paginación con 25 rows por página
- Query string sincronizado con la URL (compartible/bookmarkable)
- Estado vacío con instrucción de primer uso

### `app/Livewire/TrendDetail.php` + `resources/views/livewire/trend-detail.blade.php`

Vista de detalle de un trend. Características:
- 8 score cards con mini-barra visual
- Sparkline de momentum en canvas nativo (sin librerías externas)
- Mapa de países con barras proporcionales
- Swatches de colores con hex
- Siluetas con barras de frecuencia
- Oportunidades comerciales con reasoning y risks
- Botón "Recalculate scores" que despacha CalculateScoresJob

### `app/Livewire/ExperimentPanel.php` + `resources/views/livewire/experiment-panel.blade.php`

Panel de experimentos. Características:
- Cards por experimento con config resumida
- Indicador de "running" en tiempo real
- Botón de lanzamiento con feedback de dispatching
- Tabla de runs recientes con status coloreado, duración y costo

### `resources/views/layouts/app.blade.php`

Layout principal. Incluye:
- Nav con links activos
- Dot de estado del Python engine (ping cada 30s via fetch)
- Tailwind CDN (para MVP — en producción compilar con `npm run build`)
- JetBrains Mono para datos numéricos

### `resources/views/auth/login.blade.php`

Página de login con diseño oscuro. Muestra el email por defecto como hint.

### `resources/views/livewire/pagination.blade.php`

Paginación personalizada con estilo oscuro. Se activa configurando en `AppServiceProvider`:
```php
Livewire::component('livewire.pagination', \Livewire\WithPagination::class);
// O en TrendDashboard usar:
protected $paginationTheme = 'tailwind';
```

---

## SECCIÓN L — RUTAS

### `routes/api.php`

Reemplaza el `routes/api.php` que genera Laravel. Toda la API bajo `/api/v1`.
Rutas públicas: `POST /login`, `GET /system/health`.
Todo lo demás requiere `auth:sanctum`.

### `routes/web.php`

Reemplaza el `routes/web.php`. Rutas de la app Livewire:
- `/` → TrendDashboard
- `/trends/{slug}` → TrendDetail
- `/experiments` → ExperimentPanel
- `/sources` → placeholder (próxima fase)
- `/login`, `POST /logout`

---

## SECCIÓN M — PASOS DE INSTALACIÓN COMPLETOS

Ejecutar en este orden exacto:

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Instalar Sanctum (si no lo hace composer automáticamente)
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# 3. Instalar Livewire
php artisan livewire:publish --config

# 4. Configurar entorno
cp .env.example .env
php artisan key:generate
# Editar .env con tus credenciales de BD, admin, Reddit API

# 5. Crear tablas
php artisan migrate

# 6. Poblar datos de referencia
php artisan db:seed

# 7. Verificar que todo quedó bien
php artisan tinker
>>> App\Models\Country::count()          # debe devolver 15
>>> App\Models\Niche::count()            # debe devolver 7
>>> App\Models\Source::count()           # debe devolver 6
>>> App\Models\SourceTarget::count()     # debe devolver 40+
>>> App\Models\PromptVersion::count()    # debe devolver 4
```

---

## SECCIÓN N — LEVANTAR LOS SERVICIOS

Necesitas 3 terminales para el entorno de desarrollo:

**Terminal 1 — Servidor web:**
```bash
php artisan serve
# App disponible en http://localhost:8000
```

**Terminal 2 — Queue worker:**
```bash
php artisan queue:work --queue=experiments,scoring,default --tries=3
# Procesa los jobs en segundo plano
# Si cierras esta terminal, los experimentos no corren
```

**Terminal 3 — Scheduler (opcional en desarrollo):**
```bash
php artisan schedule:work
# Ejecuta las tareas automáticas (scoring cada 6h, experiments semanales)
```

**Verificar que el sistema funciona:**
```bash
# Health check
curl http://localhost:8000/api/v1/system/health
# Debe devolver: {"status":"degraded","python_engine":false,...}
# "degraded" es normal hasta que el Python engine esté corriendo (Parte 2)

# Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@fashion-intelligence.local","password":"changeme123"}'
# Guarda el token que devuelve

# Listar experiments
curl http://localhost:8000/api/v1/experiments \
  -H "Authorization: Bearer TU_TOKEN"
```

---

## PARTE 3 — PYTHON ENGINE (data-engine)

El Python engine es un servicio FastAPI separado que corre en el puerto 8001.
Laravel lo llama via HTTP; ellos comparten la misma base de datos PostgreSQL.

### 3.1 Estructura del engine

```
data-engine/
├── api/
│   ├── main.py              # FastAPI app, rutas, middleware
│   └── routes/
│       ├── experiments.py   # POST /experiments/run
│       ├── documents.py     # POST /documents/process
│       ├── trends.py        # POST /trends/{id}/scores, /forecast
│       └── opportunities.py # POST /opportunities/{id}/concept
├── scrapers/
│   ├── base.py              # ScrapingProviderInterface
│   ├── local.py             # HTTP requests + BeautifulSoup
│   ├── playwright_scraper.py# JS rendering
│   ├── google_trends.py     # pytrends wrapper
│   ├── reddit.py            # PRAW wrapper
│   └── providers/           # Apify, Zyte, etc.
├── nlp/
│   ├── entity_extractor.py  # spaCy NER + LLM fallback
│   ├── sentiment.py         # análisis de sentimiento
│   └── entity_resolver.py   # deduplicación de entidades
├── scoring/
│   ├── momentum.py          # momentum_score formula
│   ├── lifecycle.py         # lifecycle stage classifier
│   ├── commercial.py        # commercial_opportunity_score
│   └── convergence.py       # convergence_score
├── ml/
│   ├── forecaster.py        # Prophet + XGBoost
│   └── clusterer.py         # emergent trend discovery
├── llm/
│   ├── client.py            # Ollama/OpenAI/Anthropic adapter
│   └── creative_director.py # product concept generation
├── db/
│   └── client.py            # conexión PostgreSQL compartida
├── requirements.txt
└── .env.example
```

### 3.2 Instalación del Python engine

```bash
# Desde la raíz del proyecto
mkdir data-engine && cd data-engine

# Entorno virtual
python -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate

# Dependencias (instalar con el requirements.txt de la Opción B)
pip install -r requirements.txt

# Descargar modelos
python -m spacy download en_core_web_sm
ollama pull qwen3:8b            # ~5GB, solo la primera vez

# Configurar
cp .env.example .env
# Editar .env con las mismas credenciales de BD que el Laravel
```

### 3.3 Variables de entorno del engine

El `.env` del engine debe tener exactamente las mismas credenciales de BD:
```
DATABASE_URL=postgresql://fi_user:tu_password@localhost:5432/fashion_intelligence
PYTHON_ENGINE_SECRET=el_mismo_secret_que_pusiste_en_laravel
LLM_PROVIDER=ollama
LLM_MODEL=qwen3:8b
LLM_BASE_URL=http://localhost:11434
REDDIT_CLIENT_ID=
REDDIT_CLIENT_SECRET=
REDDIT_USER_AGENT=FashionIntelligence/1.0
SCRAPE_MODE=FREE
```

### 3.4 Cómo funciona la integración

```
[Browser/CLI]
     ↓
[Laravel] ←→ [PostgreSQL] ←→ [Python engine]
     ↓                              ↓
[php artisan               [FastAPI en :8001]
 experiment:run]                    ↓
     ↓                    [Scrapers + NLP + ML]
[RunExperimentJob]                  ↓
     ↓               [Escribe en tablas de BD]
[PythonEngineClient              raw_documents,
 POST /experiments/run]          trend_mentions,
                                 trend_snapshots,
                                 commercial_opportunities
```

El flujo completo:
1. Laravel despacha `RunExperimentJob`
2. El job llama `POST /experiments/run` al Python engine
3. El engine hace scraping (Google Trends + Reddit + Web)
4. Guarda los documentos crudos en `raw_documents`
5. Procesa cada documento con spaCy: extrae entidades
6. Escribe `trend_mentions`, actualiza `trend_snapshots`
7. Calcula scores y los escribe en `trends`
8. El LLM genera oportunidades comerciales en `commercial_opportunities`
9. El engine devuelve un resumen a Laravel
10. Laravel muestra los resultados en el dashboard

### 3.5 Iniciar el engine

```bash
cd data-engine
source venv/bin/activate

# Desarrollo (con auto-reload)
uvicorn api.main:app --host 0.0.0.0 --port 8001 --reload

# Verificar
curl http://localhost:8001/health
# {"status":"ok","llm":"ollama/qwen3:8b","db":"connected"}
```

### 3.6 Verificar la integración completa

```bash
# Desde Laravel, verificar que llega al engine
curl http://localhost:8000/api/v1/system/health \
  -H "Authorization: Bearer TU_TOKEN"
# Ahora debe devolver: {"status":"ok","python_engine":true,...}

# Lanzar el primer experimento
php artisan experiment:run hoodie_test_01 --sync

# Ver resultados
php artisan tinker
>>> App\Models\Trend::count()
>>> App\Models\Trend::rising()->orderByDesc('commercial_opportunity_score')->get(['name','lifecycle_stage','commercial_opportunity_score'])
```

---

## PARTE 4 — PROBLEMAS COMUNES

### "Class not found" al carrer los modelos
```bash
composer dump-autoload
```

### "Target class does not exist" en Livewire
```bash
php artisan livewire:discover
```

### La migración falla por pgvector
Si PostgreSQL no tiene pgvector instalado, la migración 000006 logea un warning y continúa.
Los embeddings de imágenes quedan desactivados, todo lo demás funciona normal.

### El queue worker no procesa jobs
Verifica que `QUEUE_CONNECTION=database` en `.env` y que corriste `php artisan migrate`
(crea la tabla `jobs`). Luego:
```bash
php artisan queue:work --verbose
```

### El Python engine no conecta a la BD
Verificar que `DATABASE_URL` en `data-engine/.env` usa el mismo usuario y contraseña
que el Laravel. PostgreSQL debe aceptar conexiones locales.

### "419 CSRF token mismatch" en login
```bash
php artisan config:clear
php artisan cache:clear
```

---

## PARTE 5 — FLUJO DE TRABAJO DIARIO

Una vez instalado, el flujo normal es:

```bash
# Mañana: verificar que todo corrió en la noche
curl http://localhost:8000/api/v1/experiments/hoodie_test_01/status \
  -H "Authorization: Bearer TU_TOKEN"

# Ver trends emergentes del día
curl "http://localhost:8000/api/v1/trends?lifecycle_stage=EMERGING&sort_by=momentum_score" \
  -H "Authorization: Bearer TU_TOKEN"

# Ver top oportunidades comerciales
curl "http://localhost:8000/api/v1/trends/opportunities?min_score=60" \
  -H "Authorization: Bearer TU_TOKEN"

# Si quieres actualizar scores sin re-scraping
curl -X POST http://localhost:8000/api/v1/experiments/hoodie_test_01/rescore \
  -H "Authorization: Bearer TU_TOKEN"
```

O simplemente abre el browser en `http://localhost:8000` y usa el dashboard.
