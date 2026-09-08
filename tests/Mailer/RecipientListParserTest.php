<?php

namespace App\Tests\Mailer;

use App\Mailer\RecipientListParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RecipientListParserTest extends TestCase
{
    private RecipientListParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RecipientListParser();
    }

    public function testParsesNamedList(): void
    {
        $addresses = $this->parser->parse('Name1<mail1@server1.com>;Name2<mail2@server2.com>');

        $this->assertSame(['mail1@server1.com', 'mail2@server2.com'], $this->parser->mailboxSet($addresses));
        $this->assertSame('Name1', $addresses[0]->getName());
        $this->assertSame('Name2', $addresses[1]->getName());
    }

    public function testParsesBareList(): void
    {
        $addresses = $this->parser->parse('mail1@server1.com;mail2@server2.com');

        $this->assertSame('mail1@server1.com', $addresses[0]->getAddress());
        $this->assertSame('', $addresses[0]->getName());
        $this->assertSame('mail2@server2.com', $addresses[1]->getAddress());
    }

    public function testParsesMixAndWhitespace(): void
    {
        $addresses = $this->parser->parse('Name1 <mail1@a.com> ; mail2@b.com;Name 2<mail3@c.com>');

        $this->assertCount(3, $addresses);
        $this->assertSame('Name1 <mail1@a.com>; mail2@b.com; Name 2 <mail3@c.com>', $this->parser->formatList($addresses));
    }

    public function testIgnoresEmptyTokens(): void
    {
        $addresses = $this->parser->parse('a@x.com;; ;b@y.com;');

        $this->assertCount(2, $addresses);
    }

    public function testQuotedNameMayContainSemicolon(): void
    {
        $addresses = $this->parser->parse('"Foo; Bar" <a@b.com>;c@d.com');

        $this->assertSame('a@b.com', $addresses[0]->getAddress());
        $this->assertStringContainsString('Foo', $addresses[0]->getName());
        $this->assertSame('c@d.com', $addresses[1]->getAddress());
    }

    public function testRejectsInvalidToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->parse('not-an-email;ok@example.com');
    }

    public function testEmptyStringIsEmptyList(): void
    {
        $this->assertSame([], $this->parser->parse('  '));
    }
}
