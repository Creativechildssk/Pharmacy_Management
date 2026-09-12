<?php

declare(strict_types=1);

namespace Tests\OfflineBundle;

use PHPUnit\Framework\TestCase;

final class BundleContractTest extends TestCase
{
    public function test_offline_bundle_builder_and_install_documentation_exist(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root . '/scripts/build_offline_bundle.ps1');
        self::assertFileExists($root . '/scripts/install_offline_windows.ps1');
        self::assertFileExists($root . '/docs/OFFLINE_INSTALL.md');
    }

    public function test_builder_packages_runtime_dependencies_and_database_setup_files(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/scripts/build_offline_bundle.ps1');
        self::assertIsString($script);

        foreach ([
            'composer install --no-dev',
            'public/assets/vendor',
            'database/schema.sql',
            'database/seed.sql',
            'database/audit_triggers.sql',
            'SHA256SUMS.txt',
            'OFFLINE_INSTALL.md',
        ] as $required) {
            self::assertStringContainsString($required, $script);
        }
    }

    public function test_builder_excludes_production_secrets_and_development_metadata(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/scripts/build_offline_bundle.ps1');
        self::assertIsString($script);

        foreach (['config/app.php', '.git', '.github', 'tests'] as $excluded) {
            self::assertStringContainsString($excluded, $script);
        }
    }

    public function test_builder_normalizes_output_directory_before_checksum_relative_paths(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root . '/scripts/build_offline_bundle.ps1');
        self::assertIsString($script);
        self::assertStringContainsString('$OutputDirectory = (Resolve-Path -LiteralPath $OutputDirectory).Path', $script);
        self::assertStringContainsString('$relative = $_.FullName.Substring($BundleRoot.Length + 1)', $script);
    }

    public function test_offline_install_doc_requires_no_network_on_target_machine(): void
    {
        $root = dirname(__DIR__, 2);
        $doc = file_get_contents($root . '/docs/OFFLINE_INSTALL.md');
        self::assertIsString($doc);
        self::assertStringContainsString('no internet or network access', strtolower($doc));
        self::assertStringContainsString('USB', $doc);
        self::assertStringContainsString('schema.sql', $doc);
        self::assertStringContainsString('audit_triggers.sql', $doc);
    }

    public function test_github_workflow_builds_and_uploads_offline_zip(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = $root . '/.github/workflows/offline-usb-bundle.yml';
        self::assertFileExists($workflow);

        $yaml = file_get_contents($workflow);
        self::assertIsString($yaml);
        self::assertStringContainsString('windows-latest', $yaml);
        self::assertStringContainsString('build_offline_bundle.ps1', $yaml);
        self::assertStringContainsString('actions/upload-artifact@v4', $yaml);
        self::assertStringContainsString('Pharmacy_Management_Offline_USB_*.zip', $yaml);
    }
}
