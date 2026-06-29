<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/joyeria_timezone.php';
require_once __DIR__ . '/list_filters.php';

/**
 * Valor para input type="date": POST > registro en edicion > default en alta.
 *
 * @param string|null $defaultOnCreate null = hoy (joyeria_today_ymd); '' = vacio (opcional)
 */
function joyeria_form_date_value(
    ?string $fromPost,
    ?string $fromRecord,
    bool $isEdit,
    ?string $defaultOnCreate = null
): string {
    if ($fromPost !== null && trim($fromPost) !== '') {
        $parsed = joyeria_parse_date_ymd($fromPost);

        return $parsed ?? trim($fromPost);
    }

    if ($isEdit) {
        $stored = trim((string) ($fromRecord ?? ''));
        if ($stored === '') {
            return '';
        }
        $parsed = joyeria_parse_date_ymd($stored);

        return $parsed ?? $stored;
    }

    if ($defaultOnCreate === '') {
        return '';
    }

    if ($defaultOnCreate !== null && trim($defaultOnCreate) !== '') {
        $parsed = joyeria_parse_date_ymd($defaultOnCreate);

        return $parsed ?? trim($defaultOnCreate);
    }

    return joyeria_today_ymd();
}
