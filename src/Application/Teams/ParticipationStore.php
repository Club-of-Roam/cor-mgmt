<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Teams;

interface ParticipationStore
{
    /** @return array<string,string|null> */
    public function find(string $type, int $id): array;
    /** @return list<array<string,string|null>> */
    public function related(string $type, string $field, string $value): array;
    /** @param array<string,string|null> $values */
    public function insert(string $type, array $values): int;
    /**
     * @param array<string,string|null> $old
     * @param array<string,string|null> $values
     */
    public function update(string $type, array $old, array $values): void;
    /** @param array<string,string|null> $old */
    public function remove(string $type, array $old): void;
    /** @return array<string,string> */
    public function profile(int $user): array;
    /** @param array<string,string> $values */
    public function saveProfile(int $user, array $values): void;
    /**
     * @param callable():array<string,mixed> $operation
     * @return array<string,mixed>
     */
    public function atomic(callable $operation): array;
}
