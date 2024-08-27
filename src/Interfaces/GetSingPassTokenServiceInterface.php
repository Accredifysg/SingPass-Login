<?php

namespace Accredifysg\SingPassLogin\Interfaces;

interface GetSingPassTokenServiceInterface
{
    public function getToken(string $code): string;
}
