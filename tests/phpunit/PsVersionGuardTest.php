<?php

class PsVersionGuardTest extends DatabaseTestCase
{
    public function testModuleExitsWithoutPsVersionConstant()
    {
        $php = getenv('PHP_BINARY') ?: 'php';
        $script = dirname(__DIR__) . '/scripts/ps_version_guard_probe.php';
        $command = escapeshellarg($php) . ' ' . escapeshellarg($script);
        exec($command . ' 2>&1', $output, $exitCode);
        $joined = implode("\n", $output);
        $this->assertSame(0, $exitCode);
        $this->assertSame('GUARD_BEFORE', $joined);
        $this->assertNotContains('GUARD_AFTER', $joined);
    }
}
