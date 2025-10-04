<?php

namespace App\Contracts;

interface ReadinessCheckerInterface
{
    /**
     * @return array{ok:bool, reasons:array<string,string>, details:array<string,array{status:string,error?:string}>}
     */
    public function check(): array;
}
