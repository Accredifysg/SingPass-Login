<?php

namespace Accredifysg\SingPassLogin\Interfaces;

interface SingPassLoginInterface
{
    public function handleCallback(string $code, string $state): void;
}
