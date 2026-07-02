<?php

namespace App\Service;

interface ProxyPoolServiceInterface
{
    public function getNextProxy(): ?string;
}
