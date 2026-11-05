<?php

declare(strict_types=1);

namespace Cor\Mgmt\Application\Results;

interface ResultStore
{
    /** @param callable():array<string,mixed> $operation
     * @return array<string,mixed>
     */
    public function atomic(callable $operation): array;
    public function context(int $route): ResultContext;
    public function race(int $race): ResultContext;
    public function enable(int $race): void;
    /** @param array<int,array{rank:int,one:int,fixed:int,variable:int,points:int}> $entries
     * @param array<int,int> $totals
     */
    public function save(ResultContext $context, int $stage, array $entries, array $totals, int $actor, int $time): void;
}
