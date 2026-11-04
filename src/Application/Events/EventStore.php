<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Events;

interface EventStore
{
    /** @return array<string,string|null> */
    public function find(string $type, int $id): array;
    /** @return list<array<string,string|null>> */
    public function matching(string $type, string $field, int $value): array;
    /**
     * @param array<string,string|null> $old
     * @param array<string,string|null> $values
     */
    public function write(string $type, array $old, array $values): int;
    /** @param array<string,string|null> $old */
    public function remove(string $type, array $old): void;
    /**
     * @param callable():array<string,mixed> $operation
     * @return array<string,mixed>
     */
    public function exclusive(callable $operation, bool $multirow): array;
}
