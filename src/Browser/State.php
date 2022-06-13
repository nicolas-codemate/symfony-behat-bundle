<?php

namespace Elbformat\SymfonyBehatBundle\Browser;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Representation of the browser state with last Request and Response for BrowserContext
 */
class State
{
    protected ?Response $response = null;
    protected ?Request $request = null;
    /** @var array<string,string|null> */
    protected array $cookies = [];
    protected ?Form $lastForm = null;
    protected ?Crawler $lastFormCrawler = null;

    public function update(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = $cookie->getValue();
        }
    }

    public function getResponse(): Response
    {
        if (null === $this->response) {
            throw new \DomainException('No request was made yet');
        }
        return $this->response;
    }

    public function getRequest(): Request
    {
        if (null === $this->request) {
            throw new \DomainException('No request was made yet');
        }
        return $this->request;
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function setLastForm(Crawler $form): void
    {
        $this->lastForm = $form->form();
        $this->lastFormCrawler = $form->first();
    }

    public function getLastForm(): Form
    {
        if (null === $this->lastForm) {
            throw new \DomainException('No form was queried yet');
        }
        return $this->lastForm;
    }

    public function getLastFormCrawler(): Crawler
    {
        if (null === $this->lastFormCrawler) {
            throw new \DomainException('No form was queried yet');
        }
        return $this->lastFormCrawler;
    }
}
