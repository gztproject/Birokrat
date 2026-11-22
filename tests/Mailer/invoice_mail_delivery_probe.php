<?php

use App\Kernel;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Runtime\SymfonyRuntime;

require dirname(__DIR__, 2).'/vendor/autoload.php';

final class InvoiceDeliveryProbeKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if ($container->hasAlias(MailerInterface::class)) {
                    $container->getAlias(MailerInterface::class)->setPublic(true);
                }
                if ($container->hasAlias('event_dispatcher')) {
                    $container->getAlias('event_dispatcher')->setPublic(true);
                }
                if ($container->hasDefinition('event_dispatcher')) {
                    $container->getDefinition('event_dispatcher')->setPublic(true);
                }
            }
        });
    }
}

try {
    fwrite(STDOUT, json_encode(deliverProbedInvoice(), JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode(['error' => $e::class.': '.$e->getMessage()], JSON_THROW_ON_ERROR));
    exit(1);
}

/**
 * Boots through SymfonyRuntime the same way public/index.php does, then sends
 * the same kind of message InvoiceCommandController::send() hands to the mailer.
 *
 * @return array{app_env: string, queued: bool}
 */
function deliverProbedInvoice(): array
{
    $projectDir = dirname(__DIR__, 2);

    new SymfonyRuntime(['project_dir' => $projectDir]);

    if ('service-default' === getenv('PROBE_MODE')) {
        unset($_SERVER['MAILER_OVERRIDE_TO'], $_ENV['MAILER_OVERRIDE_TO']);
        putenv('MAILER_OVERRIDE_TO');
    }

    $kernel = new InvoiceDeliveryProbeKernel((string) $_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
    $kernel->boot();

    $queued = false;
    $dispatcher = $kernel->getContainer()->get('event_dispatcher');
    $dispatcher->addListener(MessageEvent::class, function (MessageEvent $event) use (&$queued): void {
        $queued = $event->isQueued();
    }, PHP_INT_MIN);

    $email = (new Email())
        ->from('birokrat@gzt.si')
        ->to('customer@example.test')
        ->cc('accounts@example.test')
        ->replyTo('clerk@example.test')
        ->subject('Invoice')
        ->html('<p>Invoice body</p>')
        ->attach('invoice-pdf-marker', 'invoice.pdf', 'application/pdf');

    $kernel->getContainer()->get(MailerInterface::class)->send($email);

    return [
        'app_env' => $kernel->getEnvironment(),
        'queued' => $queued,
    ];
}
