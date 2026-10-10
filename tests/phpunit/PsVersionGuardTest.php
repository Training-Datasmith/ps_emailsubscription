<?php

class PsVersionGuardTest extends DatabaseTestCase
{
    public function testModuleExitsWithoutPsVersionConstant()
    {
        $php = getenv('PHP_BINARY') ?: 'php';
        $script = dirname(__DIR__) . '/scripts/ps_version_guard_probe.php';
        $command = escapeshellarg($php) . ' ' . escapeshellarg($script);
        exec($command, $output, $exitCode);
        $joined = implode("\n", $output);
        $this->assertContains('GUARD_BEFORE', $joined);
        $this->assertNotContains('GUARD_AFTER', $joined);
        $this->assertSame(0, $exitCode);
    }
}
