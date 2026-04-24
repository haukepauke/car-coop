<?php

namespace App\Tests\Unit\Service;

use App\Service\FileUploaderService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

class FileUploaderServiceTest extends TestCase
{
    public function testAllowsOnlyConfiguredRasterMimeTypes(): void
    {
        $service = new FileUploaderService(sys_get_temp_dir(), sys_get_temp_dir() . '/messages-private', new AsciiSlugger());

        $jpeg = $this->createUploadMock('image/jpeg', 'jpg');
        $png = $this->createUploadMock('image/png', 'png');
        $gif = $this->createUploadMock('image/gif', 'gif');
        $svg = $this->createUploadMock('image/svg+xml', 'svg');

        $this->assertTrue($service->isAllowedRasterImage($jpeg));
        $this->assertTrue($service->isAllowedRasterImage($png));
        $this->assertTrue($service->isAllowedRasterImage($gif));
        $this->assertFalse($service->isAllowedRasterImage($svg));
    }

    public function testUploadRejectsDisallowedFormats(): void
    {
        $service = new FileUploaderService(sys_get_temp_dir(), sys_get_temp_dir() . '/messages-private', new AsciiSlugger());
        $svg = $this->createUploadMock('image/svg+xml', 'svg');

        $this->expectException(\InvalidArgumentException::class);
        $service->upload($svg, 'messages');
    }

    public function testAllowsPdfForMessageAttachmentsOnly(): void
    {
        $service = new FileUploaderService(sys_get_temp_dir(), sys_get_temp_dir() . '/messages-private', new AsciiSlugger());
        $pdf = $this->createUploadMock('application/pdf', 'pdf');

        $this->assertFalse($service->isAllowedRasterImage($pdf));
        $this->assertTrue($service->isAllowedMessageAttachment($pdf));
    }

    public function testUploadUsesCanonicalRasterExtension(): void
    {
        $service = new FileUploaderService(sys_get_temp_dir(), sys_get_temp_dir() . '/messages-private', new AsciiSlugger());
        $jpeg = $this->getMockBuilder(UploadedFile::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMimeType', 'guessExtension', 'getClientOriginalName', 'move'])
            ->getMock();

        $jpeg->method('getMimeType')->willReturn('image/jpeg');
        $jpeg->method('guessExtension')->willReturn('jpeg');
        $jpeg->method('getClientOriginalName')->willReturn('My Photo.jpeg');
        $jpeg->expects($this->once())
            ->method('move')
            ->with(
                sys_get_temp_dir() . '/messages',
                $this->matchesRegularExpression('/\.jpg$/')
            );

        $fileName = $service->upload($jpeg, 'messages');

        $this->assertMatchesRegularExpression('/^My-Photo-.*\.jpg$/', $fileName);
    }

    public function testMessageAttachmentUploadAcceptsPdf(): void
    {
        $service = new FileUploaderService(sys_get_temp_dir(), sys_get_temp_dir() . '/messages-private', new AsciiSlugger());
        $pdf = $this->getMockBuilder(UploadedFile::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMimeType', 'guessExtension', 'getClientOriginalName', 'move'])
            ->getMock();

        $pdf->method('getMimeType')->willReturn('application/pdf');
        $pdf->method('guessExtension')->willReturn('pdf');
        $pdf->method('getClientOriginalName')->willReturn('Manual.pdf');
        $pdf->expects($this->once())
            ->method('move')
            ->with(
                sys_get_temp_dir() . '/messages-private/messages',
                $this->matchesRegularExpression('/\.pdf$/')
            );

        $fileName = $service->uploadMessageAttachment($pdf, 'messages');

        $this->assertMatchesRegularExpression('/^Manual-.*\.pdf$/', $fileName);
    }

    public function testFindAndDeleteMessageAttachmentUsePrivateDirectory(): void
    {
        $baseDir = sys_get_temp_dir() . '/car-coop-file-uploader-' . uniqid();
        $publicDir = $baseDir . '/public';
        $privateDir = $baseDir . '/private/messages';
        mkdir($publicDir, 0777, true);
        mkdir($privateDir, 0777, true);

        $service = new FileUploaderService($publicDir, $privateDir, new AsciiSlugger());
        $path = $privateDir . '/messages/test.pdf';
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'pdf');

        $this->assertSame($path, $service->findMessageAttachmentPath('test.pdf', 'messages'));

        $service->deleteMessageAttachment('test.pdf', 'messages');

        $this->assertNull($service->findMessageAttachmentPath('test.pdf', 'messages'));
    }

    private function createUploadMock(string $mimeType, string $guessedExtension): UploadedFile
    {
        $file = $this->getMockBuilder(UploadedFile::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMimeType', 'guessExtension'])
            ->getMock();

        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('guessExtension')->willReturn($guessedExtension);

        return $file;
    }
}
