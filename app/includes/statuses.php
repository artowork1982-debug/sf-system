<?php
/**
 * SafetyFlash – Status definitions and helpers
 */

if (!defined('SAFETYFLASH_STATUSES_LOADED')) {
    define('SAFETYFLASH_STATUSES_LOADED', true);
}

/**
 * Palauttaa kaikki statusmäärittelyt assosiatiivisena taulukkona.
 *
 * @return array<string,array<string,mixed>>
 */
function sf_status_definitions(): array
{
    static $definitions = null;

    if ($definitions !== null) {
        return $definitions;
    }

    $definitions = [
        'draft' => [
            'key'   => 'draft',
            'group' => 'open',
            'level' => 'info',
            'labels' => [
                'fi' => 'Luonnos',
                'en' => 'Draft',
            ],
            'descriptions' => [
                'fi' => 'Tiedotetta valmistellaan, ei vielä arvioitavana.',
                'en' => 'Flash is being prepared, not yet under review.',
            ],
            'badge_class' => 'sf-status sf-status--draft',
        ],

        'pending_review' => [
            'key'   => 'pending_review',
            'group' => 'open',
            'level' => 'warning',
            'labels' => [
                'fi' => 'Hyväksyttävänä',
                'en' => 'Pending review',
            ],
            'descriptions' => [
                'fi' => 'Tiedote odottaa vastuuhenkilön tarkistusta.',
                'en' => 'Flash is awaiting responsible person’s review.',
            ],
            'badge_class' => 'sf-status sf-status--pending',
        ],

        'request_info' => [
            'key'   => 'request_info',
            'group' => 'open',
            'level' => 'warning',
            'labels' => [
                'fi' => 'Lisätietoa pyydetty',
                'en' => 'More information requested',
            ],
            'descriptions' => [
                'fi' => 'Vastuuhenkilö on pyytänyt lisätietoa tekijältä.',
                'en' => 'Reviewer has requested more information from the creator.',
            ],
            'badge_class' => 'sf-status sf-status--request-info',
        ],

        'in_investigation' => [
            'key'   => 'in_investigation',
            'group' => 'open',
            'level' => 'info',
            'labels' => [
                'fi' => 'Tutkinnassa',
                'en' => 'In investigation',
            ],
            'descriptions' => [
                'fi' => 'Tapausta tutkitaan tarkemmin (tutkintatiedote työn alla).',
                'en' => 'Case is under deeper investigation (investigation flash in progress).',
            ],
            'badge_class' => 'sf-status sf-status--investigation',
        ],

        'to_final_approver' => [
            'key'   => 'to_final_approver',
            'group' => 'open',
            'level' => 'info',
            'labels' => [
                'fi' => 'Lopullisella hyväksyjällä',
                'en' => 'With final approver',
            ],
            'descriptions' => [
                'fi' => 'Tiedote on lähetetty lopulliselle hyväksyjälle.',
                'en' => 'Flash has been sent to final approver.',
            ],
            'badge_class' => 'sf-status sf-status--final-approver',
        ],

        'to_comms' => [
            'key'   => 'to_comms',
            'group' => 'open',
            'level' => 'info',
            'labels' => [
                'fi' => 'Viestinnällä',
                'en' => 'In communications',
            ],
            'descriptions' => [
                'fi' => 'Tiedote on viestinnän käsiteltävänä.',
                'en' => 'Flash is being handled by communications.',
            ],
            'badge_class' => 'sf-status sf-status--comms',
        ],

        'published' => [
            'key'   => 'published',
            'group' => 'closed',
            'level' => 'success',
            'labels' => [
                'fi' => 'Julkaistu',
                'en' => 'Published',
            ],
            'descriptions' => [
                'fi' => 'Tiedote on julkaistu SafetyFlash-näytöille.',
                'en' => 'Flash has been published to SafetyFlash screens.',
            ],
            'badge_class' => 'sf-status sf-status--published',
        ],

        'rejected' => [
            'key'   => 'rejected',
            'group' => 'closed',
            'level' => 'danger',
            'labels' => [
                'fi' => 'Hylätty',
                'en' => 'Rejected',
            ],
            'descriptions' => [
                'fi' => 'Tiedotetta ei hyväksytty julkaistavaksi.',
                'en' => 'Flash was not approved for publishing.',
            ],
            'badge_class' => 'sf-status sf-status--rejected',
        ],

        'archived' => [
            'key'   => 'archived',
            'group' => 'closed',
            'level' => 'info',
            'labels' => [
                'fi' => 'Arkistoitu',
                'en' => 'Archived',
            ],
            'descriptions' => [
                'fi' => 'Tiedote on suljettu ja siirretty arkistoon.',
                'en' => 'Flash has been closed and moved to archive.',
            ],
            'badge_class' => 'sf-status sf-status--archived',
        ],

        'closed' => [
            'key'   => 'closed',
            'group' => 'closed',
            'level' => 'success',
            'labels' => [
                'fi' => 'Suljettu',
                'en' => 'Closed',
            ],
            'descriptions' => [
                'fi' => 'Tapaus on käsitelty loppuun.',
                'en' => 'Case has been fully handled.',
            ],
            'badge_class' => 'sf-status sf-status--closed',
        ],
    ];

    return $definitions;
}

/** Alias vanhalle nimelle. */
function sf_statuses(): array
{
    return sf_status_definitions();
}

function sf_status_exists(string $key): bool
{
    $defs = sf_status_definitions();
    return isset($defs[$key]);
}

/**
 * @return array<string,mixed>|null
 */
function sf_status_get(string $key): ?array
{
    $defs = sf_status_definitions();
    return $defs[$key] ?? null;
}

function sf_status_label(string $key, string $lang = 'fi'): string
{
    $def = sf_status_get($key);
    if (!$def) {
        return $key;
    }

    $labels = $def['labels'] ?? [];
    if (isset($labels[$lang])) {
        return $labels[$lang];
    }

    if (isset($labels['fi'])) {
        return $labels['fi'];
    }
    if (isset($labels['en'])) {
        return $labels['en'];
    }

    return $key;
}

function sf_status_description(string $key, string $lang = 'fi'): string
{
    $def = sf_status_get($key);
    if (!$def) {
        return '';
    }

    $descriptions = $def['descriptions'] ?? [];
    if (isset($descriptions[$lang])) {
        return $descriptions[$lang];
    }
    if (isset($descriptions['fi'])) {
        return $descriptions['fi'];
    }
    if (isset($descriptions['en'])) {
        return $descriptions['en'];
    }

    return '';
}

function sf_status_badge(string $key, string $lang = 'fi', string $extraClass = ''): string
{
    $def = sf_status_get($key);
    $label = sf_status_label($key, $lang);

    $badgeClass = $def['badge_class'] ?? 'sf-status';
    $classes = trim($badgeClass . ' ' . $extraClass);

    return '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">' .
        htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
        '</span>';
}

/**
 * Listan riviluokka – yhteensopivuus vanhan koodin kanssa.
 * list.php todennäköisesti kutsuu: sf_status_list_class($flash['status'])
 */
function sf_status_list_class(string $key): string
{
    $def = sf_status_get($key);
    if (!$def) {
        return 'sf-row sf-row--status-unknown';
    }

    $group = $def['group'] ?? 'open';
    $level = $def['level'] ?? 'info';

    $classes = [
        'sf-row',
        'sf-row--group-' . $group,
        'sf-row--level-' . $level,
        'sf-row--status-' . $key,
    ];

    return implode(' ', $classes);
}

/**
 * Placeholderien korvaus lokiteksteissä: [status:draft] → badge.
 */
function sf_status_inject_badges(string $text, string $lang = 'fi'): string
{
    return preg_replace_callback(
        '/\[status:([a-z0-9_]+)\]/i',
        function (array $matches) use ($lang): string {
            $key = $matches[1] ?? '';
            if (!sf_status_exists($key)) {
                return $matches[0];
            }
            return sf_status_badge($key, $lang);
        },
        $text
    );
}
/**
 * Korvaa lokiteksteissä olevat status-avainsanat luettaviksi labeleiksi. 
 * Esim. "Tila: draft" → "Tila: Luonnos"
 * Tai "[status:draft]" → status badge. 
 *
 * @param string $text  Lokirivin description-teksti
 * @param string $lang  Käyttöliittymän kieli (fi/en)
 * @return string       HTML-turvallinen teksti, jossa statukset korvattu
 */
function sf_log_status_replace(string $text, string $lang = 'fi'): string
{
    // Ensin suojataan HTML
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Korvataan [status:xxx] placeholderit badgeiksi
    $safe = sf_status_inject_badges($safe, $lang);
    
    // Korvataan myös "Tila: xxx" -muotoiset tekstit, joissa xxx on status-avain
    $definitions = sf_status_definitions();
    $statusKeys = array_keys($definitions);
    
    foreach ($statusKeys as $key) {
        $label = sf_status_label($key, $lang);
        // Korvaa esim. "Tila: draft" → "Tila: Luonnos"
        $safe = preg_replace(
            '/\b(Tila|Status|State):\s*' . preg_quote($key, '/') . '\b/i',
            '$1: ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            $safe
        );
    }
    
    return $safe;
}