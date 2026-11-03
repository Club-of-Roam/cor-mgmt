<?php

/** WordPress HTTP adapter; core filters and isolation policies remain effective. @package CorMgmt */

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\WordPress;

use Cor\Mgmt\Application\Contracts\HttpClient;
use Cor\Mgmt\Application\HttpResponse;
use Cor\Mgmt\Application\TransportException;

final class WordPressHttpClient implements HttpClient
{
    /** @param array<string, mixed> $options Request options. */
    public function get(string $url, array $options = []): HttpResponse
    {
        $options = array_replace($options, ['timeout' => 10, 'redirection' => 0, 'limit_response_size' => 2097152]);
        $response = wp_safe_remote_get($url, $options);
        if (is_wp_error($response)) {
            throw new TransportException('HTTP transport failed.');
        }
        return new HttpResponse((int) wp_remote_retrieve_response_code($response), wp_remote_retrieve_body($response));
    }
}
