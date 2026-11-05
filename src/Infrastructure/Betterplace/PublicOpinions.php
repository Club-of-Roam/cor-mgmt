<?php

declare(strict_types=1);

namespace Cor\Mgmt\Infrastructure\Betterplace;

use Cor\Mgmt\Application\Contracts\HttpClient;
use Cor\Mgmt\Application\Donations\DonationException;
use Cor\Mgmt\Application\Donations\DonationProof;
use Cor\Mgmt\Domain\EuroAmount;

/** Bounded compatibility adapter for the existing public-comment assignment workflow. */
final class PublicOpinions
{
    public function __construct(private readonly HttpClient $http)
    {
    }
    public function find(string $event, string $token, int $created): DonationProof
    {
        if (!preg_match('/^[1-9][0-9]*$/D', $event) || !preg_match('/^t[1-9][0-9]*-[a-f0-9]{32}$/D', $token) || $created < 1) {
            throw new DonationException('invalid_reference');
        }
        $found = [];
        $seen = [];
        $finished = false;
        $previousTime = PHP_INT_MAX;
        for ($page = 1; $page <= 5; $page++) {
            $url = 'https://api.betterplace.org/de/api_v4/fundraising_events/' . $event . '/opinions.json?' . http_build_query(['facets' => 'has_message:true', 'order' => 'confirmed_at:DESC', 'per_page' => 100, 'page' => $page]);
            try {
                $response = $this->http->get($url);
            } catch (\Cor\Mgmt\Application\TransportException) {
                throw new DonationException('provider_unavailable');
            }
            if ($response->status !== 200) {
                throw new DonationException('provider_unavailable');
            }
            try {
                $data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new DonationException('invalid_provider_response');
            }
            if (
                !is_array($data) || !is_array($data['data'] ?? null) || !array_is_list($data['data'])
                || !is_int($data['total_pages'] ?? null) || !is_int($data['current_page'] ?? null)
                || $data['current_page'] !== $page || $data['total_pages'] < 0 || count($data['data']) > 100
            ) {
                throw new DonationException('invalid_provider_response');
            }
            $older = false;
            foreach ($data['data'] as $row) {
                if (
                    !is_array($row) || !is_int($row['id'] ?? null) || $row['id'] < 1 || !is_string($row['confirmed_at'] ?? null)
                    || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/D', $row['confirmed_at'])
                ) {
                    throw new DonationException('invalid_provider_response');
                }
                try {
                    $time = (new \DateTimeImmutable($row['confirmed_at']))->getTimestamp();
                    $errors = \DateTimeImmutable::getLastErrors();
                    if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
                        throw new DonationException('invalid_provider_response');
                    }
                } catch (\Exception) {
                    throw new DonationException('invalid_provider_response');
                }
                if ($time > $previousTime) {
                    throw new DonationException('invalid_provider_response');
                }
                $previousTime = $time;
                $older = $older || $time < $created;
                if ($time < $created || isset($seen[$row['id']])) {
                    continue;
                }
                $seen[$row['id']] = true;
                $message = $row['message'] ?? null;
                if (!is_string($message) || !preg_match('/(?<![A-Za-z0-9_-])' . preg_quote($token, '/') . '(?![A-Za-z0-9_-])/', $message)) {
                    continue;
                }
                if (!is_int($row['donated_amount_in_cents'] ?? null) || $row['donated_amount_in_cents'] < 1) {
                    throw new DonationException('amount_unavailable');
                }
                $found[] = new DonationProof((string) $row['id'], new EuroAmount($row['donated_amount_in_cents']), $time);
            }
            if (count($found) > 1) {
                throw new DonationException('ambiguous_donation');
            }
            if ($older || $page >= $data['total_pages']) {
                $finished = true;
                break;
            }
            if ($data['data'] === []) {
                throw new DonationException('invalid_provider_response');
            }
        }
        if (!$finished) {
            throw new DonationException('search_incomplete');
        }
        return $found[0] ?? throw new DonationException('donation_not_found');
    }
}
