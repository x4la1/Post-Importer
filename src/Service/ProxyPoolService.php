<?php

namespace App\Service;

class ProxyPoolService implements ProxyPoolServiceInterface
{
    private array $proxies;

    public function __construct()
    {
        $proxyString = $_ENV['PROXY'] ?? '';
        $this->proxies = array_filter(explode(';', $proxyString));
    }

    public function getNextProxy(): ?string
    {
        if (empty($this->proxies)) {
            return null;
        }

        $proxy = array_shift($this->proxies);
        $this->proxies[] = $proxy;

        return $proxy;
    }
}
