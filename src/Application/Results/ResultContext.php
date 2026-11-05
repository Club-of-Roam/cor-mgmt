<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Results;

final readonly class ResultContext
{
    /** @param array<string,string|null> $race
     * @param array<string,string|null> $route
     * @param list<array<string,string|null>> $stages
     * @param list<array<string,string|null>> $teams
     * @param list<array<string,string|null>> $results
     */
    public function __construct(public array $race, public array $route, public array $stages, public array $teams, public array $results, public string $version, public string $generation = '0')
    {
    }
    public function revision(): string
    {
        return hash('sha256', json_encode(get_object_vars($this), JSON_THROW_ON_ERROR));
    }
    /** @return array<string,mixed> */
    public function rules(): array
    {
        $rules = json_decode($this->race['setting'] ?? '', true);
        if (!is_array($rules) || !array_key_exists('status', $rules)) {
            throw new ResultException('invalid_rules');
        }
        return $rules;
    }
}
