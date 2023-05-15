<?php

declare(strict_types=1);

namespace tests;

use TestCaseSymconValidation;

include_once __DIR__ . '/stubs/Validator.php';

class SymconHueValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Bridge(): void
    {
        $this->validateModule(__DIR__ . '/../Bridge');
    }

    public function testValidateModule_Configurator(): void
    {
        $this->validateModule(__DIR__ . '/../DeviceConfigurator');
    }

    public function testValidateModule_Discovery(): void
    {
        $this->validateModule(__DIR__ . '/../Discovery');
    }

    public function testValidateModule_Group(): void
    {
        $this->validateModule(__DIR__ . '/../GroupedLight');
    }

    public function testValidateModule_Light(): void
    {
        $this->validateModule(__DIR__ . '/../Light');
    }
}