<?php

namespace Context;

use Behat\Gherkin\Node\PyStringNode;
use Elbformat\SymfonyBehatBundle\Browser\State;
use Elbformat\SymfonyBehatBundle\Browser\StateFactory;
use Elbformat\SymfonyBehatBundle\Context\BrowserContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class BrowserContextTest extends TestCase
{
    protected ?KernelInterface $kernel = null;
    protected ?BrowserContext $browserContext = null;
    protected ?StateFactory $stateFactory = null;
    protected ?State $state = null;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->state = new State();
        $this->stateFactory = $this->getMockBuilder(StateFactory::class)->getMock();
        $this->stateFactory->method('newState')->willReturn($this->state);
        $this->browserContext = new BrowserContext($this->kernel, $this->stateFactory, '');
    }

    public function testIVisit()
    {
        $this->kernel->expects($this->once())->method('shutdown');
        $this->kernel->expects($this->once())->method('handle')->with($this->callback(function (Request $request) {
            return '/test' === $request->getPathInfo();
        }))->willReturn(new Response(''));
        $this->browserContext->iVisit('/test');
    }

    public function testISendARequestTo()
    {
        $postData = [
            '{',
            '  lorem":"ipsum"',
            '}',
        ];

        $this->kernel->expects($this->once())->method('shutdown');
        $this->kernel->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Request $request) use ($postData) {
                if ('/test' !== $request->getPathInfo()) {
                    return false;
                }
                if ('POST' !== $request->getMethod()) {
                    return false;
                }
                if ('json' !== $request->getContentType()) {
                    return false;
                }
                if (implode("\n", $postData) !== $request->getContent()) {
                    return false;
                }

                return true;
            }))
            ->willReturn(new Response(''));

        $this->browserContext->iSendARequestTo('POST', '/test', new PyStringNode($postData, 1));
    }

    public function testIFollowTheRedirect()
    {
        $this->state->update(new Request(), new Response('', 302, ['Location' => '/target']));
        $this->kernel->method('handle')->with($this->callback(function (Request $request) {
            return '/target' === $request->getPathInfo();
        }))->willReturn(new Response('Redirect Target'));
        $this->browserContext->iFollowTheRedirect();
        $this->assertEquals('Redirect Target', $this->state->getResponse()->getContent());
    }

    public function testThePageContainsAFormNamed()
    {
        $this->state->update(new Request(), new Response('<form name="hello"></form>', 302));
        $this->browserContext->thePageContainsAFormNamed('hello');
        $this->assertInstanceOf(Form::class, $this->state->getLastForm());
        $this->assertInstanceOf(Crawler::class, $this->state->getLastFormCrawler());
    }
}
