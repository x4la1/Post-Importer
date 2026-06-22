<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class ProxyPoolService
{
    private array $proxies;
    private int $nextIndex;

    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
        $this->nextIndex = 0;
        $proxyString = $_ENV['PROXY'] ?? '';
        $this->proxies = array_filter(explode(';', $proxyString));
    }

    public function getNextProxy(): ?string
    {
        if (empty($this->proxies)) {
            return null;
        }

        $proxy = $this->proxies[$this->nextIndex++];

        if ($this->nextIndex >= count($this->proxies)) {
            $this->nextIndex = 0;
        }

        return $proxy;
    }

}
