<?php

namespace App\Tests\Organization;

use App\Entity\Geography\Address;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\CreatePartnerEmailCommand;
use App\Entity\Organization\Partner;
use App\Entity\User\User;
use App\Mailer\RecipientListParser;
use PHPUnit\Framework\TestCase;

class PartnerEmailMergeTest extends TestCase
{
    public function testPrimaryListAndCcFormatting(): void
    {
        $partner = $this->partner('Name1 <a@x.com>; b@y.com');
        $extra = new CreatePartnerEmailCommand();
        $extra->email = 'acc@z.com';
        $extra->role = 'Računovodstvo';
        $partner->createExtraEmail($extra, $partner->getCreatedBy());

        $this->assertSame('Name1 <a@x.com>; b@y.com', $partner->formatToList());
        $this->assertSame('Računovodstvo <acc@z.com>', $partner->formatCcList());
    }

    public function testMergeUpdatesPrimaryAndAddsCcExtras(): void
    {
        $partner = $this->partner('old@x.com');
        $existing = new CreatePartnerEmailCommand();
        $existing->email = 'keep@z.com';
        $existing->role = 'invoices';
        $partner->createExtraEmail($existing, $partner->getCreatedBy());

        $parser = new RecipientListParser();
        $partner->applyRecipientMerge(
            $parser->parse('New <new@x.com>; other@x.com'),
            $parser->parse('Keep <KEEP@z.com>; extra@z.com'),
            $partner->getCreatedBy(),
        );

        $this->assertSame('New <new@x.com>; other@x.com', $partner->getEmail());
        $this->assertCount(2, $partner->getExtraEmails());
        $mailboxes = [];
        foreach ($partner->getExtraEmails() as $extraEmail) {
            $mailboxes[] = strtolower($extraEmail->getEmail());
            if (strtolower($extraEmail->getEmail()) === 'keep@z.com') {
                $this->assertSame('invoices', $extraEmail->getRole());
                $this->assertSame('Keep', $extraEmail->getName());
            }
        }
        sort($mailboxes);
        $this->assertSame(['extra@z.com', 'keep@z.com'], $mailboxes);
    }

    public function testEmailsDifferFromComparesMailboxSets(): void
    {
        $partner = $this->partner('a@x.com');
        $parser = new RecipientListParser();
        $this->assertTrue($partner->emailsDifferFrom($parser->parse('a@x.com'), $parser->parse('cc@z.com')));
        $this->assertFalse($partner->emailsDifferFrom($parser->parse('a@x.com'), []));
    }

    private function partner(string $email): Partner
    {
        $c = new CreatePartnerCommand();
        $c->code = '1';
        $c->name = 'Partner d.o.o.';
        $c->taxNumber = '123';
        $c->taxable = false;
        $c->address = $this->createMock(Address::class);
        $c->isClient = true;
        $c->isSupplier = false;
        $c->email = $email;

        return new Partner($c, $this->createMock(User::class));
    }
}
