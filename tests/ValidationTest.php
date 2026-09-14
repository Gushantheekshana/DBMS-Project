<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class ValidationTest extends TestCase
{
 public function testGmailDomainValidation():void
 {
  self::assertTrue(gmail_address(' Student.Name+gym@GMAIL.COM '));
  self::assertFalse(gmail_address('student@outlook.com'));
  self::assertFalse(gmail_address('student@gmail.com.example'));
 }
 public function testOutputEscaping():void {self::assertSame('&lt;script&gt;',e('<script>'));}
}
