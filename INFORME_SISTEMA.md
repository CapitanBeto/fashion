# Fashion Intelligence — Informe Técnico del Sistema

## 1. Qué es el proyecto

Plataforma de inteligencia de moda cuyo objetivo central es predecir qué tendencias de streetwear tienen mayor probabilidad de volverse relevantes en Chile en los próximos ~60 días, explicando el porqué con evidencia trazable (no una caja negra). La investigación profunda de marcas/productos existe como insumo para esa predicción, no como fin en sí misma.

Ruta base: `C:\fashion\fashion\`

## 2. Stack tecnológico

| Componente | Tecnología | Notas |
|---|---|---|
| Backend web | Laravel 11 (PHP 8.4.25) | Dashboard + API REST |
| Motor de datos | Python 3.11 + FastAPI | Scraping, NLP, scoring, forecasting |
| Base de datos | PostgreSQL 18 | Compartida entre Laravel y Python |
| LLM | Ollama local, modelo `qwen3:8b` | Gratuito, corre en la máquina, sin dependencia externa |
| Frontend | Livewire 3 + Tailwind CDN | Sin build step |
| Cola de trabajos | Laravel Queue, driver `database` | Sin Redis |
| Servidor local | Laravel Herd | Puerto 8000 (Laravel) / 8001 (Python) |

**Cómo se comunican:** Laravel llama al motor Python vía HTTP (`PythonEngineClient.php` → `http://localhost:8001`), autenticado con un secreto compartido (`X-Engine-Secret`). Ambos servicios leen/escriben la misma base de datos PostgreSQL directamente — no hay una capa de sincronización adicional.

## 3. Esquema de base de datos (tablas principales)

**Referencia:** `countries`, `niches`, `colors`, `silhouettes`, `graphics`, `materials`, `scoring_parameters` (pesos configurables de las fórmulas), `prompt_versions` (prompts del LLM versionados en BD, no hardcodeados).

**Fuentes/ingesta:** `sources`, `source_targets` (URLs/keywords configurados por fuente), `scrape_runs` (log de cada ejecución), `scraping_errors`, `raw_documents` (contenido crudo escrapeado, deduplicado por `content_hash`), `raw_posts`.

**Entidades:** `brands`, `products` + pivots `product_colors`/`product_silhouettes`/`product_graphics`/`product_materials`, `creators`, `communities`, `price_observations`.

**Tendencias:** `trends` (scores globales: momentum, growth, search, convergence, saturation, death_probability, confidence, lifecycle_stage), `trend_mentions` (menciones individuales detectadas), `trend_snapshots` (serie temporal diaria), `trend_countries` (fuerza y etapa del ciclo de vida **por país**), `search_metrics` (interés de Google Trends por keyword/país/fecha).

**Inteligencia:** `commercial_opportunities` (conceptos de producto generados por LLM), `trend_predictions` (pronósticos con horizonte, probabilidad, confianza, razonamiento, evidencia en JSON), `prediction_outcomes` (resultado real comparado contra la predicción, para calibración), `country_transfer_lags` (cuánto tiempo históricamente tarda una tendencia en viajar de un país a otro).

## 4. Pipeline de un experimento (`php artisan experiment:run {key} --sync`)

5 pasos secuenciales, ejecutados por el motor Python (`POST /experiments/run`):

1. **Google Trends** — para cada país configurado, consulta interés de búsqueda real por keyword (vía `pytrends`, librería no oficial).
2. **Reddit** — implementado pero deshabilitado (bloqueo de política de datos de Reddit para este tipo de uso).
3. **Web** — escrapea URLs configuradas en `source_targets` (medios de moda de nicho), respetando `robots.txt`. Guarda en `raw_documents`.
4. **NLP** — cada documento nuevo pasa por extracción de entidades vía LLM (marca, producto, color, silueta, tendencia, sentimiento), y se cruza contra las keywords del experimento (con variantes en español) para crear `trend_mentions`.
5. **Scoring + LLM** — recalcula momentum/convergence/lifecycle por trend, y si el momentum supera un umbral, dispara al "Creative Director" (LLM) para generar un concepto de producto.

Después de esto, Laravel dispara el pronóstico Chile 60 días (`ForecastChileTrendJob` → `POST /trends/{id}/forecast`).

## 5. Módulos del motor Python (`python/`)

```
api/
  main.py              # FastAPI app, middleware de autenticación, /health
  routes/
    experiments.py      # POST /experiments/run — el pipeline completo
    trends.py            # POST /trends/{id}/scores, /forecast, /backtest
    products.py           # POST /products/analyze — deep-dive de producto
scrapers/
  base.py               # LocalScraper genérico (httpx + BeautifulSoup), respeta robots.txt
  google_trends.py       # wrapper de pytrends
  web.py                 # orquesta LocalScraper contra source_targets
nlp/
  entity_extractor.py    # extracción de entidades desde texto editorial (vía LLM)
  product_extractor.py    # extracción de specs de producto (silueta, material, gráfico)
  product_pipeline.py      # resuelve/crea Brand/Silhouette/Material/Graphic/Color reales
scoring/
  momentum.py            # momentum, convergencia, prob. de muerte, clasificación de etapa
forecasting/
  chile_transfer.py       # pronóstico Chile 60 días — baseline explicable, no ML
  backtest.py              # backtest histórico walk-forward (ver sección 7)
llm/
  client.py               # adaptador unificado Ollama/OpenAI/Anthropic
  creative_director.py     # genera conceptos de producto
db/
  client.py                # pool de conexión asyncpg compartido con Laravel
```

## 6. Backend Laravel (`fashion-intelligence-backend/`)

- **Jobs:** `RunExperimentJob`, `CalculateScoresJob`, `ForecastChileTrendJob` — encadenados tras cada experimento.
- **Comandos artisan:** `experiment:run {key} --sync`, `experiment:forecast-chile {key} --sync`, `predictions:evaluate` (backtesting).
- **Livewire (UI):**
  - `ChileFashionRadar` (`/`) — dashboard principal: Now / Next 60 Days / Early Signals / Declining
  - `TrendDashboard` (`/all-trends`) — catálogo completo filtrable
  - `TrendDetail` (`/trends/{slug}`) — panel de pronóstico Chile + atributos del trend
  - `ProductDeepDive` (`/products`) — productos reales con fotos, specs y lectura experta
- **Servicio central:** `app/Services/PythonEngineClient.php` — todas las llamadas HTTP al motor Python pasan por acá.

## 7. Sistema de scoring y pronóstico

**Momentum** — fórmula ponderada (pesos en `scoring_parameters`, editables sin tocar código): velocidad de crecimiento en menciones, aceleración, crecimiento de búsqueda, crecimiento social, adopción de creadores, adopción de marcas, dispersión geográfica.

**Pronóstico Chile 60 días** (`forecast_chile_trend`) — combina: momentum global, momentum específico de Chile (`trend_countries`), convergencia entre fuentes, adopción de creadores/marcas en Chile, crecimiento de búsqueda en Chile, y una señal de "transferencia histórica" (cuánto tardan las tendencias en llegar de otro país a Chile, calculado de `country_transfer_lags` — nunca inventado, solo calculado cuando hay suficiente historial real). Devuelve curva de probabilidad a 7/14/30/60/90 días, evidencia y contradicciones explícitas.

**Backtest histórico** (`run_historical_backtest`) — Google Trends devuelve hasta 12 meses de historial real en una sola consulta. El sistema "se para" en checkpoints pasados cada 2 semanas, calcula qué habría pronosticado con los datos disponibles hasta ese punto, y lo compara contra lo que realmente pasó 60 días después (ya conocido, por ser historia). Esto genera pares predicción/resultado reales sin esperar meses, y produce un número de precisión histórica real (actualmente ~79% sobre 443 casos reales evaluados).

## 8. Fuentes de datos activas hoy

| Fuente | Estado | Notas |
|---|---|---|
| Google Trends | Intermitente | API no oficial (`pytrends`); Google bloquea esporádicamente por volumen de peticiones |
| Scraping web de medios de nicho | Funcional | 11 sitios de moda urbana españoles configurados, respeta robots.txt |
| Páginas de producto (marca→producto) | Funcional | Extrae specs reales + fotos vía `og:image`/JSON-LD |
| Reddit | Implementado, deshabilitado | Bloqueado por política de uso de datos de Reddit para este caso de uso |

## 9. Limitaciones conocidas (honestidad, no marketing)

- El "commercial opportunity score" usa una fórmula simplificada de 3 factores; existe una fórmula de 9 factores diseñada en `scoring_parameters` pero no está conectada al cálculo real.
- De las 7 señales que alimentan momentum, solo 2 (búsqueda + menciones) tienen datos reales fluyendo consistentemente; adopción de creadores/marcas/dispersión geográfica dependen de resolución de entidades que recién se empezó a construir (vía `product_pipeline.py`).
- No existe ninguna fuente de datos de ventas reales — todo "commercial opportunity" es inferencia indirecta (búsqueda + menciones + catálogo de competencia), nunca confirmación de venta.
- El sistema no navega internet de forma autónoma — cada fuente debe configurarse explícitamente en `source_targets`.
