<?php

// src/EventSubscriber/LocaleSubscriber.php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

class LocaleSubscriber implements EventSubscriberInterface {
	private $defaultLocale;
	private $logger;
	public function __construct($defaultLocale = 'en', LoggerInterface $logger) {
		$this->logger = $logger;
		$this->defaultLocale = $defaultLocale;
	}
	
	public function onKernelRequest(RequestEvent $event) {
		$request = $event->getRequest ();
		if (! $request->hasPreviousSession ()) {
			$this->logger->debug("Not setting locale on an existing session.");
			return;
		}
	
		// try to see if the locale has been set as a _locale routing parameter
		if ($locale = $request->get ( '_locale' )) {
			$this->logger->debug("Setting requested locale: ". $locale);
			$request->getSession ()->set ( '_locale', $locale );
			$request->setLocale ( $locale );
		} 
		else {
			// if no explicit locale has been set on this request, use one from the session
			$request->setLocale ( $request->getSession ()->get ( '_locale', $this->defaultLocale ) );
			$this->logger->debug("Settting default locale: ". $this->defaultLocale);
		}
	}
	public static function getSubscribedEvents() {
		return [ 
				// must be registered before (i.e. with a higher priority than) the default Locale listener
				KernelEvents::REQUEST => [ 
						[ 
								'onKernelRequest',
								20
						]
				]
		];
	}
}