<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        if ('test' === $this->environment) {
            return sys_get_temp_dir() . '/car-coop/cache/' . $this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if ('test' === $this->environment) {
            return sys_get_temp_dir() . '/car-coop/log';
        }

        return parent::getLogDir();
    }
}
