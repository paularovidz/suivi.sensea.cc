<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Document;

/**
 * Tests de sécurité pour le système de documents
 * Données paramédicales sensibles - sécurité critique
 */
class DocumentSecurityTest extends TestCase
{
    /**
     * @dataProvider allowedMimeTypesProvider
     */
    public function testAllowedMimeTypes(string $mimeType, bool $expected): void
    {
        $this->assertSame($expected, Document::isAllowedMimeType($mimeType));
    }

    public static function allowedMimeTypesProvider(): array
    {
        return [
            // Types autorisés
            'jpeg' => ['image/jpeg', true],
            'png' => ['image/png', true],
            'gif' => ['image/gif', true],
            'webp' => ['image/webp', true],
            'pdf' => ['application/pdf', true],

            // Types dangereux - doivent être refusés
            'javascript' => ['application/javascript', false],
            'html' => ['text/html', false],
            'php' => ['application/x-php', false],
            'exe' => ['application/x-msdownload', false],
            'script' => ['application/x-sh', false],
            'svg' => ['image/svg+xml', false], // SVG peut contenir du JS
            'xml' => ['application/xml', false],
            'zip' => ['application/zip', false],
            'octet_stream' => ['application/octet-stream', false],
            'text' => ['text/plain', false],
        ];
    }

    /**
     * @dataProvider allowedExtensionsProvider
     */
    public function testAllowedExtensions(string $extension, bool $expected): void
    {
        $this->assertSame($expected, Document::isAllowedExtension($extension));
    }

    public static function allowedExtensionsProvider(): array
    {
        return [
            // Extensions autorisées
            'jpg' => ['jpg', true],
            'jpeg' => ['jpeg', true],
            'png' => ['png', true],
            'gif' => ['gif', true],
            'webp' => ['webp', true],
            'pdf' => ['pdf', true],
            'JPG_uppercase' => ['JPG', true], // Case insensitive
            'PDF_uppercase' => ['PDF', true],

            // Extensions dangereuses - doivent être refusées
            'php' => ['php', false],
            'phtml' => ['phtml', false],
            'php5' => ['php5', false],
            'php7' => ['php7', false],
            'js' => ['js', false],
            'html' => ['html', false],
            'htm' => ['htm', false],
            'exe' => ['exe', false],
            'sh' => ['sh', false],
            'bat' => ['bat', false],
            'svg' => ['svg', false],
            'xml' => ['xml', false],
            'htaccess' => ['htaccess', false],
        ];
    }

    /**
     * @dataProvider fileSizeProvider
     */
    public function testFileSizeValidation(int $size, bool $expected): void
    {
        $this->assertSame($expected, Document::isAllowedSize($size));
    }

    public static function fileSizeProvider(): array
    {
        $maxSize = 10 * 1024 * 1024; // 10 MB

        return [
            'small_file' => [1024, true], // 1 KB
            'medium_file' => [1024 * 1024, true], // 1 MB
            'just_under_limit' => [$maxSize - 1, true],
            'at_limit' => [$maxSize, true],
            'just_over_limit' => [$maxSize + 1, false],
            'way_over_limit' => [$maxSize * 2, false],
            'zero_size' => [0, true], // Edge case
        ];
    }

    public function testDocumentableTypesIncludesSession(): void
    {
        $this->assertContains('session', Document::DOCUMENTABLE_TYPES);
        $this->assertContains('user', Document::DOCUMENTABLE_TYPES);
        $this->assertContains('person', Document::DOCUMENTABLE_TYPES);
    }

    public function testGenerateFilenameIsUnique(): void
    {
        $filename1 = Document::generateFilename('pdf');
        $filename2 = Document::generateFilename('pdf');

        $this->assertNotEquals($filename1, $filename2);
        $this->assertStringEndsWith('.pdf', $filename1);
        $this->assertStringEndsWith('.pdf', $filename2);
    }

    public function testGenerateFilenamePreservesExtension(): void
    {
        $extensions = ['pdf', 'jpg', 'png', 'gif', 'webp'];

        foreach ($extensions as $ext) {
            $filename = Document::generateFilename($ext);
            $this->assertStringEndsWith('.' . $ext, $filename);
        }
    }

    public function testGenerateFilenameUsesUuid(): void
    {
        $filename = Document::generateFilename('pdf');

        // UUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.pdf
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.pdf$/',
            $filename
        );
    }

    public function testMaxFileSizeConstant(): void
    {
        // 10 MB en bytes
        $this->assertEquals(10 * 1024 * 1024, Document::MAX_FILE_SIZE);
    }

    public function testFormatSizeBytes(): void
    {
        $this->assertEquals('500 B', Document::formatSize(500));
    }

    public function testFormatSizeKilobytes(): void
    {
        $this->assertEquals('1.5 KB', Document::formatSize(1536));
    }

    public function testFormatSizeMegabytes(): void
    {
        $this->assertEquals('2.5 MB', Document::formatSize(2621440));
    }

    /**
     * Test que les extensions dangereuses avec double extension sont refusées
     */
    public function testDoubleExtensionAttack(): void
    {
        // Ces patterns d'attaque doivent être refusés par la validation d'extension
        $dangerousPatterns = [
            'php',   // fichier.pdf.php -> extension = php
            'phtml',
            'html',
            'js',
        ];

        foreach ($dangerousPatterns as $ext) {
            $this->assertFalse(
                Document::isAllowedExtension($ext),
                "Extension '$ext' should be rejected (double extension attack)"
            );
        }
    }

    /**
     * Test que les types MIME spoofés sont gérés
     * (la validation MIME doit être faite sur le contenu réel du fichier)
     */
    public function testMimeTypeSpoofingPrevention(): void
    {
        // Un fichier PHP déguisé en image aurait le mauvais MIME type
        $dangerousMimeTypes = [
            'application/x-php',
            'application/x-httpd-php',
            'text/x-php',
        ];

        foreach ($dangerousMimeTypes as $mimeType) {
            $this->assertFalse(
                Document::isAllowedMimeType($mimeType),
                "MIME type '$mimeType' should be rejected"
            );
        }
    }

    /**
     * Test la liste complète des types autorisés
     */
    public function testAllowedMimeTypesConstant(): void
    {
        $expected = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
        ];

        $this->assertEquals($expected, Document::ALLOWED_MIME_TYPES);
    }

    /**
     * Test la liste complète des extensions autorisées
     */
    public function testAllowedExtensionsConstant(): void
    {
        $expected = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

        $this->assertEquals($expected, Document::ALLOWED_EXTENSIONS);
    }
}
