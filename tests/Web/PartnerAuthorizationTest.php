<?php

namespace App\Tests\Web;

use App\Entity\Geography\Address;
use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\Partner;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Ordinary signed-in users are not administrators and do not own every counterparty.
 * They must not read or rewrite another partner's tax id, bank account, or email,
 * and they must not delete that partner. A partner id the caller is allowed to
 * manage, sent beside the route id, must not authorize the other record.
 *
 * partner_new is not covered: it does not take an existing partner id.
 */
class PartnerAuthorizationTest extends WebTestCase
{
    private const VICTIM_TAX = '11223344';
    private const VICTIM_ACCOUNT = 'SI56000000000001111';
    private const VICTIM_EMAIL = 'victim-keep@example.test';
    private const DECOY_TAX = '55667788';
    private const DECOY_ACCOUNT = 'SI56000000000002222';
    private const DECOY_EMAIL = 'decoy-keep@example.test';
    private const SUBSTITUTE_TAX = '99887766';
    private const SUBSTITUTE_ACCOUNT = 'SI56000000000009999';
    private const SUBSTITUTE_EMAIL = 'substituted@example.test';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $attacker;
    private string $victimId;
    private string $decoyId;
    private string $organizationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeQuery('SELECT 1');

        $suffix = bin2hex(random_bytes(4));
        $owner = $this->user('owner-'.$suffix, ['ROLE_ADMIN']);
        $this->attacker = $this->user('staff-'.$suffix, ['ROLE_USER']);
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($owner);
        $this->em->persist($this->attacker);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $owner->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $owner);

        $addressCommand = new CreateAddressCommand();
        $addressCommand->line1 = 'Trg '.$suffix;
        $addressCommand->line2 = '';
        $address = $post->createAddress($addressCommand, $owner);

        $organizationCommand = new CreateOrganizationCommand();
        $organizationCommand->code = 'O'.$suffix;
        $organizationCommand->name = 'Books '.$suffix;
        $organizationCommand->taxNumber = '10000001';
        $organizationCommand->taxable = true;
        $organizationCommand->address = $address;
        $organization = $owner->createOrganization($organizationCommand);
        $this->attacker->addOrganization($organization, $owner);

        $victim = $this->partner($owner, $address, 'V'.$suffix, 'Victim d.o.o.', self::VICTIM_TAX, self::VICTIM_ACCOUNT, self::VICTIM_EMAIL);
        $decoy = $this->partner($this->attacker, $address, 'D'.$suffix, 'Decoy d.o.o.', self::DECOY_TAX, self::DECOY_ACCOUNT, self::DECOY_EMAIL);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($address);
        $this->em->persist($organization);
        $this->em->persist($victim);
        $this->em->persist($decoy);
        $this->em->flush();

        $this->victimId = $victim->getId()->toString();
        $this->decoyId = $decoy->getId()->toString();
        $this->organizationId = $organization->getId()->toString();
    }

    public function testAnonymousRequestDoesNotRewriteOrRemovePartner(): void
    {
        $this->client->request('POST', '/dashboard/partner/'.$this->victimId.'/edit', [
            'partner' => [
                'name' => 'Victim d.o.o.',
                'taxNumber' => self::SUBSTITUTE_TAX,
                'accountNumber' => self::SUBSTITUTE_ACCOUNT,
            ],
            'id' => $this->decoyId,
        ]);
        $this->client->request('POST', '/dashboard/partner/'.$this->victimId.'/delete', [
            'token' => 'not-a-session-token',
            'id' => $this->decoyId,
        ]);
        $this->client->request('POST', '/dashboard/partner/'.$this->victimId.'/emails/merge', [
            'to' => self::SUBSTITUTE_EMAIL,
            'cc' => 'cc-substituted@example.test',
            'id' => $this->decoyId,
        ]);

        $this->assertPartnerUntouched($this->victimId, self::VICTIM_TAX, self::VICTIM_ACCOUNT, self::VICTIM_EMAIL);
        $this->assertPartnerUntouched($this->decoyId, self::DECOY_TAX, self::DECOY_ACCOUNT, self::DECOY_EMAIL);
    }

    public function testOrdinaryUserCannotReadAnotherPartnersTaxOnTheList(): void
    {
        $this->client->loginUser($this->attacker);
        $this->client->request('GET', '/dashboard/partner');
        $this->assertSensitiveFieldsHidden();
    }

    public function testOrdinaryUserCannotReadAnotherPartnersBankOnTheShowPage(): void
    {
        $this->client->loginUser($this->attacker);
        $this->client->request('GET', '/dashboard/partner/'.$this->victimId);
        $this->assertSensitiveFieldsHidden();
    }

    public function testOrdinaryUserCannotReadAnotherPartnersBankOnTheEditForm(): void
    {
        $this->client->loginUser($this->attacker);
        $this->client->request('GET', '/dashboard/partner/'.$this->victimId.'/edit');
        $this->assertSensitiveFieldsHidden();
    }

    public function testOrdinaryUserCannotReplaceAnotherPartnersTaxOrBank(): void
    {
        $this->client->loginUser($this->attacker);
        $crawler = $this->client->request('GET', '/dashboard/partner/'.$this->victimId.'/edit');
        $forms = $crawler->filter('form[name="partner"]');
        if ($forms->count() === 0) {
            $this->assertPartnerUntouched($this->victimId, self::VICTIM_TAX, self::VICTIM_ACCOUNT, self::VICTIM_EMAIL);
            $this->assertPartnerUntouched($this->decoyId, self::DECOY_TAX, self::DECOY_ACCOUNT, self::DECOY_EMAIL);

            return;
        }
        $form = $forms->form();
        $values = $form->getPhpValues();
        $values['partner']['taxNumber'] = self::SUBSTITUTE_TAX;
        $values['partner']['accountNumber'] = self::SUBSTITUTE_ACCOUNT;
        $values['id'] = $this->decoyId;

        $this->client->request($form->getMethod(), $form->getUri(), $values);

        $this->assertPartnerUntouched($this->victimId, self::VICTIM_TAX, self::VICTIM_ACCOUNT, self::VICTIM_EMAIL);
        $this->assertPartnerUntouched($this->decoyId, self::DECOY_TAX, self::DECOY_ACCOUNT, self::DECOY_EMAIL);
    }

    public function testOrdinaryUserCannotDeleteAnotherPartner(): void
    {
        $this->client->loginUser($this->attacker);
        $token = $this->deleteCsrfToken();

        $this->client->request('POST', '/dashboard/partner/'.$this->victimId.'/delete', [
            'token' => $token,
            'id' => $this->decoyId,
        ]);

        $this->assertPartnerUntouched($this->victimId, self::VICTIM_TAX, self::VICTIM_ACCOUNT, self::VICTIM_EMAIL);
        $this->assertPartnerUntouched($this->decoyId, self::DECOY_TAX, self::DECOY_ACCOUNT, self::DECOY_EMAIL);
    }

    public function testOrdinaryUserCannotMergeAnotherPartnersEmail(): void
    {
        $this->client->loginUser($this->attacker);

        $this->client->request('POST', '/dashboard/partner/'.$this->victimId.'/emails/merge', [
            'to' => self::SUBSTITUTE_EMAIL,
            'cc' => 'cc-substituted@example.test',
            'id' => $this->decoyId,
        ]);

        $this->assertPartnerUntouched($this->victimId, self::VICTIM_TAX, self::VICTIM_ACCOUNT, self::VICTIM_EMAIL);
        $this->assertPartnerUntouched($this->decoyId, self::DECOY_TAX, self::DECOY_ACCOUNT, self::DECOY_EMAIL);
        $victim = $this->reload(Partner::class, $this->victimId);
        $this->assertCount(0, $victim->getExtraEmails());
    }

    private function assertSensitiveFieldsHidden(): void
    {
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertContains($status, [200, 301, 302, 303, 401, 403], 'Unexpected status '.$status);
        $body = (string) $this->client->getResponse()->getContent();
        $exposed = str_contains($body, self::VICTIM_TAX) || str_contains($body, self::VICTIM_ACCOUNT);
        $this->assertFalse($exposed, 'Response '.$status.' included the other partner tax number or bank account.');
    }

    private function assertPartnerUntouched(string $id, string $tax, string $account, string $email): void
    {
        $partner = $this->reload(Partner::class, $id);
        $this->assertNotNull($partner, 'Partner '.$id.' was removed.');
        $this->assertSame($tax, (string) $partner->getTaxNumber());
        $this->assertSame($account, (string) $partner->getAccountNumber());
        $this->assertSame($email, (string) $partner->getEmail());
    }

    private function deleteCsrfToken(): string
    {
        $crawler = $this->client->request('GET', '/dashboard/organization/'.$this->organizationId);
        if ($crawler->filter('input[name="token"]')->count() > 0) {
            return (string) $crawler->filter('input[name="token"]')->attr('value');
        }

        /** @var CsrfTokenManagerInterface $tokens */
        $tokens = $this->client->getContainer()->get('security.csrf.token_manager');

        return $tokens->getToken('delete')->getValue();
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    private function reload(string $class, string $id): ?object
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return $em->find($class, $id);
    }

    private function partner(User $creator, Address $address, string $code, string $name, string $tax, string $account, string $email): Partner
    {
        $command = new CreatePartnerCommand();
        $command->code = $code;
        $command->name = $name;
        $command->taxNumber = $tax;
        $command->taxable = true;
        $command->address = $address;
        $command->isClient = true;
        $command->isSupplier = true;
        $command->accountNumber = $account;
        $command->email = $email;

        return $creator->createPartner($command);
    }

    /**
     * @param list<string> $roles
     */
    private function user(string $username, array $roles): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new \Doctrine\Common\Collections\ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', 'Staff');
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', 'Bookkeeper');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', $roles);
        $this->set($user, 'password', 'unused');

        return $user;
    }

    private function set(object $object, string $name, mixed $value): void
    {
        $class = new ReflectionClass($object);
        while ($class instanceof ReflectionClass && !$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setValue($object, $value);
    }
}
