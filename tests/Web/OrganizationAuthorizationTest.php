<?php

namespace App\Tests\Web;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\Organization\Organization;
use App\Entity\Organization\Partner;
use App\Entity\Settings\UpdateOrganizationSettingsCommand;
use App\Entity\User\CreateUserCommand;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Authorization for organization records.
 *
 * A signed-in user may read and change only an organization they belong to,
 * unless they are an administrator. These tests fail while show, edit,
 * settings, and delete accept any id and the list endpoint returns every id.
 */
class OrganizationAuthorizationTest extends WebTestCase
{
    private const PLACEHOLDER_PASSWORD = 'placeholder-1!';

    private const VICTIM_ACCOUNT = 'SI56VICTIM000000001';
    private const VICTIM_BIC = 'VICTBIC01';
    private const VICTIM_TAX = '81000001';
    private const VICTIM_NAME = 'Victim Company ZX91';
    private const VICTIM_PREFIX = 'KEEP-LEDGER-ZX91';

    private const ATTACKER_ACCOUNT = 'SI56ATTACKER0000001';
    private const ATTACKER_BIC = 'ATKRBIC01';
    private const ATTACKER_TAX = '81000002';
    private const ATTACKER_NAME = 'Attacker Company ZX91';
    private const ATTACKER_PREFIX = 'MINE-LEDGER-ZX91';

    private const REPLACEMENT_ACCOUNT = 'SI56TAKEN0000000001';
    private const REPLACEMENT_BIC = 'TAKENBIC1';
    private const REPLACEMENT_TAX = '81000099';
    private const REPLACEMENT_PREFIX = 'TAKEN-LEDGER-ZX91';

    private const DISPOSABLE_ACCOUNT = 'SI56DISPOSE00000001';
    private const DISPOSABLE_BIC = 'DISPBIC01';
    private const DISPOSABLE_TAX = '81000004';
    private const DISPOSABLE_NAME = 'Disposable Company ZX91';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    private User $attacker;
    private User $victimUser;
    private User $admin;
    private Organization $attackerOrg;
    private Organization $victimOrg;
    private Organization $disposableOrg;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client = static::createClient();
            $this->em = static::getContainer()->get(EntityManagerInterface::class);
            $this->em->getConnection()->executeQuery('SELECT 1');
            $this->seed();
        } catch (DbalException|\PDOException $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }
    }

    public function testIndexListsOnlyOrganizationsTheUserBelongsTo(): void
    {
        $this->client->loginUser($this->attacker);

        $this->client->request('GET', '/dashboard/organization');
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        $this->assertStringContainsString(self::ATTACKER_NAME, $content);
        $this->assertStringNotContainsString(self::VICTIM_NAME, $content);
        $this->assertStringNotContainsString(self::VICTIM_TAX, $content);
        $this->assertStringNotContainsString(self::DISPOSABLE_NAME, $content);
    }

    public function testOrganizationListDoesNotRevealForeignIds(): void
    {
        $this->client->loginUser($this->attacker);

        $this->client->request('GET', '/dashboard/organization/list');
        $this->assertResponseIsSuccessful();

        $ids = $this->organizationIdsFromList((string) $this->client->getResponse()->getContent());
        $this->assertContains($this->attackerOrg->getId()->toString(), $ids);
        $this->assertNotContains(
            $this->victimOrg->getId()->toString(),
            $ids,
            'The list endpoint returned an organization id the signed-in user does not belong to.'
        );
        $this->assertNotContains($this->disposableOrg->getId()->toString(), $ids);
    }

    public function testAnonymousUserCannotListOrganizations(): void
    {
        $this->client->request('GET', '/dashboard/organization/list');
        $this->assertResponseRedirects();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString($this->victimOrg->getId()->toString(), $content);
        $this->assertStringNotContainsString(self::VICTIM_ACCOUNT, $content);
    }

    public function testNonMemberCannotReadBankDetails(): void
    {
        $this->client->loginUser($this->attacker);

        $this->client->request('GET', '/dashboard/organization/'.$this->victimOrg->getId()->toString());
        $this->assertResponseStatusCodeSame(403);
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString(self::VICTIM_ACCOUNT, $content);
        $this->assertStringNotContainsString(self::VICTIM_BIC, $content);
        $this->assertStringNotContainsString(self::VICTIM_PREFIX, $content);
    }

    public function testMemberCanReadOwnBankDetails(): void
    {
        $this->client->loginUser($this->attacker);

        $this->client->request('GET', '/dashboard/organization/'.$this->attackerOrg->getId()->toString());
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(self::ATTACKER_ACCOUNT, $content);
        $this->assertStringContainsString(self::ATTACKER_BIC, $content);
    }

    public function testAdministratorCanReadOrganizationTheyDoNotBelongTo(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/dashboard/organization/'.$this->victimOrg->getId()->toString());
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString(self::VICTIM_ACCOUNT, $content);
        $this->assertStringContainsString(self::VICTIM_BIC, $content);
    }

    public function testNonMemberCannotReadTaxNumberFromEditForm(): void
    {
        $this->client->loginUser($this->attacker);

        $this->client->request('GET', '/dashboard/organization/'.$this->victimOrg->getId()->toString().'/edit');
        $this->assertResponseStatusCodeSame(403);
        $this->assertStringNotContainsString(
            self::VICTIM_TAX,
            (string) $this->client->getResponse()->getContent()
        );
    }

    public function testNonMemberCannotReplaceBankBicOrTaxNumber(): void
    {
        $this->client->loginUser($this->attacker);
        $values = $this->organizationFormValues($this->attackerOrg->getId()->toString());
        $root = $this->rootKey($values);
        $values[$root]['accountNumber'] = self::REPLACEMENT_ACCOUNT;
        $values[$root]['bic'] = self::REPLACEMENT_BIC;
        $values[$root]['taxNumber'] = self::REPLACEMENT_TAX;

        $this->client->request(
            'POST',
            '/dashboard/organization/'.$this->victimOrg->getId()->toString().'/edit',
            $values
        );

        $this->assertOrganizationUnchanged($this->victimOrg->getId()->toString(), self::VICTIM_ACCOUNT, self::VICTIM_BIC, self::VICTIM_TAX, self::VICTIM_NAME);
        $this->assertOrganizationUnchanged($this->attackerOrg->getId()->toString(), self::ATTACKER_ACCOUNT, self::ATTACKER_BIC, self::ATTACKER_TAX, self::ATTACKER_NAME);
        $this->assertResponseStatusCodeSame(403);

        $invoice = $this->reload(Invoice::class, $this->invoice->getId()->toString());
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(
            self::VICTIM_ACCOUNT,
            $invoice->getIssuer()->getAccountNumber(),
            'The account read by later invoices and by the payment QR must stay the company account.'
        );
    }

    public function testMemberCanReplaceOwnBankDetails(): void
    {
        $this->client->loginUser($this->attacker);
        $values = $this->organizationFormValues($this->attackerOrg->getId()->toString());
        $root = $this->rootKey($values);
        $values[$root]['accountNumber'] = self::REPLACEMENT_ACCOUNT;
        $values[$root]['bic'] = self::REPLACEMENT_BIC;
        $values[$root]['taxNumber'] = self::REPLACEMENT_TAX;

        $this->client->request(
            'POST',
            '/dashboard/organization/'.$this->attackerOrg->getId()->toString().'/edit',
            $values
        );

        $this->assertResponseRedirects();
        $this->assertOrganizationUnchanged(
            $this->attackerOrg->getId()->toString(),
            self::REPLACEMENT_ACCOUNT,
            self::REPLACEMENT_BIC,
            self::REPLACEMENT_TAX,
            self::ATTACKER_NAME
        );
        $this->assertOrganizationUnchanged($this->victimOrg->getId()->toString(), self::VICTIM_ACCOUNT, self::VICTIM_BIC, self::VICTIM_TAX, self::VICTIM_NAME);
    }

    public function testNonMemberCannotReplaceOrganizationSettings(): void
    {
        $this->client->loginUser($this->attacker);

        $values = $this->settingsFormValues($this->attackerOrg->getId()->toString());
        $values = $this->withLedgerPrefix($values, self::REPLACEMENT_PREFIX);

        $this->client->request(
            'POST',
            '/dashboard/organization/'.$this->victimOrg->getId()->toString().'/settings',
            $values
        );

        $this->assertSame(self::VICTIM_PREFIX, $this->prefixOf($this->victimOrg->getId()->toString()));
        $this->assertSame(self::ATTACKER_PREFIX, $this->prefixOf($this->attackerOrg->getId()->toString()));
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/dashboard/organization/'.$this->victimOrg->getId()->toString().'/settings');
        $this->assertResponseStatusCodeSame(403);
        $this->assertStringNotContainsString(
            self::VICTIM_PREFIX,
            (string) $this->client->getResponse()->getContent()
        );
    }

    public function testMemberCanReplaceOwnOrganizationSettings(): void
    {
        $this->client->loginUser($this->attacker);
        $values = $this->withLedgerPrefix(
            $this->settingsFormValues($this->attackerOrg->getId()->toString()),
            self::REPLACEMENT_PREFIX
        );

        $this->client->request(
            'POST',
            '/dashboard/organization/'.$this->attackerOrg->getId()->toString().'/settings',
            $values
        );

        $this->assertResponseRedirects();
        $this->assertSame(self::REPLACEMENT_PREFIX, $this->prefixOf($this->attackerOrg->getId()->toString()));
        $this->assertSame(self::VICTIM_PREFIX, $this->prefixOf($this->victimOrg->getId()->toString()));
    }

    public function testDeleteTokenFromAnotherOrganizationDoesNotDeleteTheTarget(): void
    {
        $this->client->loginUser($this->attacker);
        $token = $this->deleteToken($this->attackerOrg->getId()->toString());

        $this->client->request('POST', '/dashboard/organization/'.$this->disposableOrg->getId()->toString().'/delete', [
            'token' => $token,
        ]);

        $this->assertInstanceOf(
            Organization::class,
            $this->reload(Organization::class, $this->disposableOrg->getId()->toString()),
            'A delete token taken from another organization removed this company.'
        );
        $this->assertResponseStatusCodeSame(403);
        $this->assertInstanceOf(
            Organization::class,
            $this->reload(Organization::class, $this->attackerOrg->getId()->toString())
        );
        $this->assertOrganizationUnchanged(
            $this->disposableOrg->getId()->toString(),
            self::DISPOSABLE_ACCOUNT,
            self::DISPOSABLE_BIC,
            self::DISPOSABLE_TAX,
            self::DISPOSABLE_NAME
        );
    }

    public function testMemberCanDeleteOwnOrganizationWithItsDeleteToken(): void
    {
        $this->client->loginUser($this->attacker);
        $token = $this->deleteToken($this->attackerOrg->getId()->toString());

        $this->client->request('POST', '/dashboard/organization/'.$this->attackerOrg->getId()->toString().'/delete', [
            'token' => $token,
        ]);

        $this->assertResponseRedirects('/dashboard/organization');
        $this->assertNull($this->reload(Organization::class, $this->attackerOrg->getId()->toString()));
        $this->assertInstanceOf(
            Organization::class,
            $this->reload(Organization::class, $this->victimOrg->getId()->toString())
        );
    }

    /**
     * @return list<string>
     */
    private function organizationIdsFromList(string $json): array
    {
        $payload = json_decode($json, true);
        $this->assertIsArray($payload);
        $organizations = $payload[0]['data']['organizations'] ?? null;
        $this->assertIsArray($organizations);

        $ids = [];
        foreach ($organizations as $organization) {
            $this->assertIsArray($organization);
            $ids[] = strtolower((string) $organization['id']);
        }

        return $ids;
    }

    private function organizationFormValues(string $id): array
    {
        $crawler = $this->client->request('GET', '/dashboard/organization/'.$id.'/edit');
        $this->assertResponseIsSuccessful();

        return $this->selectForm($crawler, 'organization')->getPhpValues();
    }

    private function settingsFormValues(string $id): array
    {
        $crawler = $this->client->request('GET', '/dashboard/organization/'.$id.'/settings');
        $this->assertResponseIsSuccessful();

        return $this->selectForm($crawler, 'organization_settings')->getPhpValues();
    }

    private function deleteToken(string $id): string
    {
        $crawler = $this->client->request('GET', '/dashboard/organization/'.$id);
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('#delete-form input[name="token"]')->attr('value');
        $this->assertNotSame('', $token);

        return $token;
    }

    private function selectForm(Crawler $crawler, string $name): Form
    {
        $node = $crawler->filter('form[name="'.$name.'"]');
        $this->assertGreaterThan(0, $node->count(), 'Missing form named '.$name.'.');

        return $node->form();
    }

    private function rootKey(array $values): string
    {
        $root = array_key_first($values);
        $this->assertIsString($root);

        return $root;
    }

    private function withLedgerPrefix(array $values, string $prefix): array
    {
        $root = $this->rootKey($values);
        $values[$root]['invoicePrefix'] = $prefix;
        $values[$root]['defaultPaymentDueIn'] = '15';
        $values[$root]['referenceModel'] = 'SI00';
        $values[$root]['travelExpenseRate'] = '0.430';
        $values[$root]['perDiemValue'] = '10.00';
        $values[$root]['lunchValue'] = '6.12';

        return $values;
    }

    private function assertOrganizationUnchanged(string $id, string $account, string $bic, string $tax, string $name): void
    {
        $organization = $this->reload(Organization::class, $id);
        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertSame($account, $organization->getAccountNumber());
        $this->assertSame($bic, $organization->getBic());
        $this->assertSame($tax, (string) $organization->getTaxNumber());
        $this->assertSame($name, $organization->getName());
    }

    private function prefixOf(string $id): ?string
    {
        $organization = $this->reload(Organization::class, $id);
        $this->assertInstanceOf(Organization::class, $organization);

        return $organization->getOrganizationSettings()?->getInvoicePrefix();
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

        return $em->getRepository($class)->find($id);
    }

    private function seed(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = $this->bootstrapUser('admin-'.$suffix, true);
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $this->em->persist($admin);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        $attacker = $admin->createUser($this->userCommand('attacker-'.$suffix, 'attacker-'.$suffix.'@example.test'), $hasher);
        $victimUser = $admin->createUser($this->userCommand('victim-'.$suffix, 'victim-'.$suffix.'@example.test'), $hasher);
        $this->em->persist($attacker);
        $this->em->persist($victimUser);

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenija';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $admin->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = 'Ljubljana';
        $post = $country->createPost($postCommand, $admin);

        $victimAddress = $post->createAddress($this->addressCommand('Victim street '.$suffix), $admin);
        $attackerAddress = $post->createAddress($this->addressCommand('Attacker street '.$suffix), $admin);

        $victimOrg = $admin->createOrganization($this->organizationCommand(
            'v'.$suffix,
            self::VICTIM_NAME,
            self::VICTIM_TAX,
            self::VICTIM_ACCOUNT,
            self::VICTIM_BIC,
            $victimAddress
        ));
        $attackerOrg = $admin->createOrganization($this->organizationCommand(
            'a'.$suffix,
            self::ATTACKER_NAME,
            self::ATTACKER_TAX,
            self::ATTACKER_ACCOUNT,
            self::ATTACKER_BIC,
            $attackerAddress
        ));
        $disposableOrg = $admin->createOrganization($this->organizationCommand(
            'd'.$suffix,
            self::DISPOSABLE_NAME,
            self::DISPOSABLE_TAX,
            self::DISPOSABLE_ACCOUNT,
            self::DISPOSABLE_BIC,
            $attackerAddress
        ));
        $this->setPrefix($victimOrg, self::VICTIM_PREFIX, $admin);
        $this->setPrefix($attackerOrg, self::ATTACKER_PREFIX, $admin);

        $victimUser->addOrganization($victimOrg, $admin);
        $attacker->addOrganization($attackerOrg, $admin);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = 'p'.$suffix;
        $partnerCommand->name = 'Partner '.$suffix;
        $partnerCommand->taxNumber = '81000003';
        $partnerCommand->taxable = true;
        $partnerCommand->address = $victimAddress;
        $partnerCommand->isClient = true;
        $partnerCommand->isSupplier = false;
        $partner = $admin->createPartner($partnerCommand);

        $invoiceCommand = new CreateInvoiceCommand();
        $invoiceCommand->dateOfIssue = new \DateTime('today');
        $invoiceCommand->dateServiceRenderedFrom = new \DateTime('-7 days');
        $invoiceCommand->dateServiceRenderedTo = new \DateTime('-1 days');
        $invoiceCommand->dueDate = new \DateTime('+10 days');
        $invoiceCommand->discount = 0;
        $invoiceCommand->number = '1001';
        $invoiceCommand->issuer = $victimOrg;
        $invoiceCommand->recepient = $partner;
        $invoice = $victimUser->createInvoice($invoiceCommand);

        $this->em->persist($country);
        $this->em->persist($post);
        $this->em->persist($victimAddress);
        $this->em->persist($attackerAddress);
        $this->em->persist($victimOrg);
        $this->em->persist($attackerOrg);
        $this->em->persist($disposableOrg);
        $this->em->persist($partner);
        $this->em->persist($invoice);
        $this->em->flush();

        $this->admin = $admin;
        $this->attacker = $attacker;
        $this->victimUser = $victimUser;
        $this->attackerOrg = $attackerOrg;
        $this->victimOrg = $victimOrg;
        $this->disposableOrg = $disposableOrg;
        $this->invoice = $invoice;
    }

    private function setPrefix(Organization $organization, string $prefix, User $editor): void
    {
        $command = new UpdateOrganizationSettingsCommand();
        $command->invoicePrefix = $prefix;
        $command->defaultPaymentDueIn = 15;
        $command->referenceModel = 'SI00';
        $organization->updateOrganizationSettings($command, $editor);
    }

    private function userCommand(string $username, string $email): CreateUserCommand
    {
        $command = new CreateUserCommand();
        $command->username = $username;
        $command->firstName = 'Test';
        $command->lastName = 'User';
        $command->email = $email;
        $command->password = self::PLACEHOLDER_PASSWORD;
        $command->isRoleAdmin = false;
        $command->signatureFilename = '';

        return $command;
    }

    private function addressCommand(string $line1): CreateAddressCommand
    {
        $command = new CreateAddressCommand();
        $command->line1 = $line1;
        $command->line2 = '';

        return $command;
    }

    private function organizationCommand(
        string $code,
        string $name,
        string $tax,
        string $account,
        string $bic,
        object $address,
    ): CreateOrganizationCommand {
        $command = new CreateOrganizationCommand();
        $command->code = $code;
        $command->name = $name;
        $command->shortName = $name;
        $command->taxNumber = $tax;
        $command->taxable = true;
        $command->address = $address;
        $command->accountNumber = $account;
        $command->bic = $bic;

        return $command;
    }

    private function bootstrapUser(string $username, bool $admin): User
    {
        $ref = new ReflectionClass(User::class);
        $user = $ref->newInstanceWithoutConstructor();
        $this->set($user, 'id', Uuid::uuid6());
        $this->set($user, 'createdOn', new \DateTime());
        $this->set($user, 'createdBy', $user);
        $this->set($user, 'isActive', true);
        $this->set($user, 'organizations', new ArrayCollection());
        $this->set($user, 'username', $username);
        $this->set($user, 'firstName', $admin ? 'Admin' : 'Test');
        $this->set($user, 'lastName', 'User');
        $this->set($user, 'email', $username.'@example.test');
        $this->set($user, 'mobile', null);
        $this->set($user, 'phone', null);
        $this->set($user, 'signatureFilename', '');
        $this->set($user, 'position', $admin ? 'Administrator' : 'Tester');
        $this->set($user, 'totpEnabled', false);
        $this->set($user, 'totpSecret', null);
        $this->set($user, 'roles', [$admin ? 'ROLE_ADMIN' : 'ROLE_USER']);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->set($user, 'password', $hasher->hashPassword($user, self::PLACEHOLDER_PASSWORD));

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
