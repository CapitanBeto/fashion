<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Internalizes real facts and analytical framework from the report
 * "La Cadena de Influencia — Del Streetwear Global al Chileno, Pasando por
 * España" (Sept 2026) — user-supplied document, read and extracted verbatim,
 * not fabricated.
 *
 * Every field here is either a fact the report states (founders, founding
 * year, GSM, follower counts, drop cadence) or the report's own stated
 * assessment (lifecycle positioning) — clearly attributed via `metadata`,
 * never presented as something this system computed from live scraping.
 */
class StreetwearChileEspanaKnowledgeSeeder extends Seeder
{
    private const SOURCE = 'streetwear_chile_espana.docx (informe del usuario, Sept 2026)';

    public function run(): void
    {
        $niche = DB::table('niches')->where('slug', 'streetwear')->value('id');
        $es = DB::table('countries')->where('iso2', 'ES')->value('id');
        $cl = DB::table('countries')->where('iso2', 'CL')->value('id');

        $brands = [
            // ── España ──────────────────────────────────────────────────
            [
                'name' => 'Nude Project', 'country_id' => $es,
                'website' => 'https://nudeproject.com',
                'price_position' => 'mid', 'estimated_scale' => 'medium',
                'metadata' => [
                    'source' => self::SOURCE,
                    'founded' => 2019, 'founders' => ['Bruno Casanovas', 'Àlex Benlloch'],
                    'origin' => 'Barcelona',
                    'facts' => [
                        'Inversión inicial: 600 EUR', 'Drops cada 3 semanas',
                        'Ticket medio 80 EUR, CAC 8-10 EUR', 'Canal online 70% de facturación',
                        '130 empleados (2024)', 'Colaboraciones: Rauw Alejandro, Kidd Keo',
                    ],
                    'reported_position' => 'Desplazado del primer puesto del ranking de hype en España por Scuffers (2025-2026), tras fase de introducción/crecimiento acelerado.',
                ],
            ],
            [
                'name' => 'Scuffers', 'country_id' => $es,
                'website' => 'https://scuffers.com',
                'price_position' => 'premium', 'estimated_scale' => 'medium',
                'metadata' => [
                    'source' => self::SOURCE,
                    'founded' => 2018, 'founders' => ['Jaime Cruz', 'Javier López'],
                    'origin' => 'Madrid',
                    'facts' => [
                        'Sudaderas 400-450 GSM', 'Tiendas en Madrid, Valencia, Barcelona (2)',
                        '~1.9M seguidores en redes sociales (2025-2026)',
                        'Colaboración/impulso de notoriedad: Arón Piper',
                    ],
                    'quote' => '"Nos fijamos en la estética más que en el producto como tal. Nos gusta la dirección artística de marcas como Stüssy, Jacquemus, diseñadores como Rhuigi o directores creativos como Gordon Von Steiner." — Jaime Cruz, co-founder, en Vogue',
                    'reported_position' => 'Cima de fase de crecimiento en 2025-2026, desplazó a Nude Project del primer puesto del ranking de hype en España.',
                ],
            ],
            ['name' => 'Eme Studios', 'country_id' => $es, 'price_position' => null, 'estimated_scale' => null,
                'metadata' => ['source' => self::SOURCE, 'facts' => ['Apuesta por knitwear y siluetas vanguardistas.']]],
            ['name' => 'Cold Culture', 'country_id' => $es, 'price_position' => null, 'estimated_scale' => null,
                'metadata' => ['source' => self::SOURCE, 'facts' => ['Inspirada en "thinking worldwide", propuesta urbana-global.']]],
            ['name' => 'Sisyphe', 'country_id' => $es, 'price_position' => null, 'estimated_scale' => null,
                'metadata' => ['source' => self::SOURCE, 'facts' => ['Mezcla estética turista de Benidorm con cine clásico y código de vestimenta de calle española — irónica, culta.']]],

            // ── Chile ───────────────────────────────────────────────────
            [
                'name' => 'Stodak', 'country_id' => $cl,
                'website' => 'https://stodak.cl',
                'price_position' => 'mid', 'estimated_scale' => 'small',
                'metadata' => [
                    'source' => self::SOURCE,
                    'origin' => 'Franklin, Santiago',
                    'facts' => [
                        'Origen: grupo de amigos diseñando desde un garage en Franklin',
                        'Primeras piezas: poleras acid wash',
                        'Trabaja con costureras y tintorerías locales',
                        'Adoptado primero por freestylers/traperos, luego por Milo J (Festival de Viña)',
                    ],
                    'reported_position' => 'Transición entre Early Adopters y Early Majority (modelo de Rogers). Riesgo principal: dilución de identidad en la masificación.',
                ],
            ],
            [
                'name' => 'Treino', 'country_id' => $cl,
                'website' => 'https://treinoficial.cl',
                'price_position' => 'mid', 'estimated_scale' => 'medium',
                'metadata' => [
                    'source' => self::SOURCE,
                    'origin' => 'Santiago (tienda en Providencia)',
                    'facts' => [
                        'Dirección creativa: Jano Gebert',
                        'Piezas icónicas: zip hoodie, polerón con cierre, jeans baggy',
                        'Drop HITOH con Alexander Azukar: totebags con paneles anti-scanner, chaquetas de taslan',
                    ],
                    'reported_position' => 'Early Majority (modelo de Rogers) — la marca chilena más cercana al modelo de negocio español. Riesgo: expectativa de novedad permanente.',
                ],
            ],
            [
                'name' => 'Human Mob', 'country_id' => $cl,
                'website' => 'https://humanmob.cl',
                'price_position' => null, 'estimated_scale' => 'small',
                'metadata' => [
                    'source' => self::SOURCE,
                    'facts' => [
                        'Estilo oversize, colores neutros, diseño minimalista, fit boxy',
                        'Lema: "el espacio donde el arte y el estilo de vida de un incógnito tiene voz"',
                    ],
                    'reported_position' => 'Early Adopters (modelo de Rogers), prioriza distinción por sobre volumen — riesgo si cede a presión de escala.',
                ],
            ],
        ];

        foreach ($brands as $b) {
            DB::table('brands')->updateOrInsert(
                ['slug' => str($b['name'])->slug()],
                [
                    'name' => $b['name'],
                    'country_id' => $b['country_id'],
                    'niche_id' => $niche,
                    'website' => $b['website'] ?? null,
                    'price_position' => $b['price_position'],
                    'estimated_scale' => $b['estimated_scale'],
                    'confidence' => 70, // fact-sourced from a written report, not live-verified by scraping
                    'is_demo' => false,
                    'metadata' => json_encode($b['metadata']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command?->info('Seeded 8 real Spain/Chile streetwear brands from the report.');
    }
}
