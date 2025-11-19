<?php

declare(strict_types=1);

namespace Waffle\Commons\Runtime;

use Waffle\Interface\KernelInterface;

interface RuntimeInterface
{
    public function run(KernelInterface $kernel): void;
}
