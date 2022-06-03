<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use DOMElement;
use Elbformat\SymfonyBehatBundle\Browser\State;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * This Context is adapted from Mink Context but with less overhead by using the Kernel directly.
 * See https://github.com/Behat/MinkExtension/blob/master/src/Behat/MinkExtension/Context/MinkContext.php for more
 * methods
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class BrowserContext implements Context
{
    protected KernelInterface $kernel;
    protected State $state;
    protected string $projectDir;

    public function __construct(KernelInterface $kernel, string $projectDir)
    {
        $this->kernel = $kernel;
        $this->projectDir = $projectDir;
        $this->state = $this->newState();
    }

    /**
     * Opens specified page
     * Example: Given I am on "http://batman.com"
     * Example: And I am on "/articles/isBatmanBruceWayne"
     * Example: When I go to "/articles/isBatmanBruceWayne"
     *
     * @Given /^(?:|I )am on "(?P<page>[^"]+)"$/
     * @When /^(?:|I )go to "(?P<page>[^"]+)"$/
     * @When I navigate to :page
     * @When I visit :page
     */
    public function iVisit(string $page): void
    {
        $this->doRequest(Request::create($page, 'GET', [], $this->state->getCookies()));
    }

    /**
     * @When I send a :method request to :url
     * @When I make a :method request to :url
     */
    public function iSendARequestTo(string $method, string $url, ?PyStringNode $data = null): void
    {
        $server = [];
        if ($data) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        $this->doRequest(Request::create($url, strtoupper($method), [], $this->state->getCookies(), [], $server, $data ? $data->getRaw() : null));
    }

    /**
     * @When I follow the redirect
     */
    public function iFollowtheRedirect(): void
    {
        $response = $this->state->getResponse();
        $code = $response->getStatusCode();
        if ($code >= 400 || $code < 300) {
            throw new \DomainException('No redirect code found: Code '.$code);
        }
        $targetUrl = (string)$response->headers->get('Location');
        // This is not url, not even a path. Not RFC compliant but we need to handle it either way
        if (0 === strpos($targetUrl, '?')) {
            $targetUrl = $this->state->getRequest()
                    ->getUri().$targetUrl;
        }
        $this->doRequest(Request::create($targetUrl, 'GET', [], $this->state->getCookies()));
    }

    /**
     * @When I use form :name
     * @Then the page must contain a form named :name
     */
    public function thePageMustContainAFormNamed(string $name): void
    {
        $crawler = $this->getCrawler();
        $form = $crawler->filter(sprintf('form[name="%s"]', $name));
        if (!$form->count()) {
            throw $this->createNotFoundException('Form', $crawler->filterXPath('//form'));
        }
        $this->state->setLastForm($form);
    }

    /**
     * @When I fill :value into :name
     * @When I select :name radio button with value :value
     */
    public function iFillIntoInput(string $value, string $name): void
    {
        $formField = $this->state->getLastForm()
            ->get($name);
        if (!$formField instanceof FormField) {
            throw new \DomainException(sprintf('%s is not a form field', $name));
        }
        $formField->setValue($value);
    }

    /**
     * @When I check :name checkbox
     */
    public function iCheckCheckbox(string $name): void
    {
        /** @var ChoiceFormField $cb */
        $formField = $this->state->getLastForm()
            ->get($name);
        if (!$formField instanceof ChoiceFormField) {
            throw new \DomainException(sprintf('%s is not a choice form field', $name));
        }
        $formField->tick();
    }

    /**
     * @When I select :value from :name
     */
    public function iSelectFrom(string $value, string $name): void
    {
        $select = $this->state->getLastForm()
            ->get($name);
        if (!$select instanceof ChoiceFormField) {
            throw new \DomainException(sprintf('%s is not a choice form field', $name));
        }
        $select->select($value);
    }

    /**
     * @When I submit the form( with extra data)
     */
    public function iSubmitTheForm(TableNode $table = null): void
    {
        $form = $this->state->getLastForm();

        if (null !== $table) {
            // Convert array to deep structure
            parse_str(http_build_query($table->getRowsHash()), $extraData);
            $form->setValues($extraData);
        }
        $this->doRequest(Request::create($form->getUri(), $form->getMethod(), $form->getPhpValues(), $this->state->getCookies()));
    }

    /**
     * @When I select :fixture upload at :name
     */
    public function iSelectUploadfixture(string $fixture, string $name): void
    {
        $field = $this->state->getLastForm()
            ->get($name);
        if (!$field instanceof FileFormField) {
            throw new \DomainException(sprintf('%s is not a file form field', $name));
        }
        $field->upload($this->projectDir.'/'.$fixture);
    }

    /**
     * Checks, that current page response status is equal to specified
     * Example: Then the response status code should be 200
     * Example: And the response status code should be 400
     *
     * @Then /^the response status code should be (?P<code>\d+)$/
     */
    public function theResponseStatusCodeShouldBe(string $code): void
    {
        $response = $this->state->getResponse();
        if ($response->getStatusCode() !== (int)$code) {
            throw new \RuntimeException('Received '.$response->getStatusCode());
        }
    }

    /**
     * Checks, that page contains specified text
     * Example: Then I should see "Who is the Batman?"
     * Example: And I should see "Who is the Batman?"
     *
     * @Then /^(?:|I )should see "(?P<text>(?:[^"]|\\")*)"$/
     */
    public function iShouldSeeText(string $text): void
    {
        $regex = '/'.preg_quote($text, '/').'/ui';
        $actual = (string)$this->state->getResponse()
            ->getContent();
        if (!preg_match($regex, $actual)) {
            throw new \DomainException('Text not found');
        }
    }

    /**
     * @Then /^(?:|I )should not see "(?P<text>(?:[^"]|\\")*)"$/
     */
    public function iShouldNotSeeText(string $text): void
    {
        try {
            $this->iShouldSeeText($text);
        } catch (\DomainException $e) {
            return;
        }
        throw new \DomainException('Text found');
    }

    /**
     * @Then I should see a(n) :tag tag
     * @Then I should see a(n) :tag tag :content
     */
    public function ishouldSeeATag(string $tag, ?TableNode $table = null, ?string $content = null, ?PyStringNode $multiLineContent = null): void
    {
        $this->mustContainTag($tag, $this->getTableData($table), $multiLineContent ? $multiLineContent->getRaw() : $content);
    }

    /**
     * @Then I should not see a(n) :tag tag
     * @Then I should not see a(n) :tag tag :content
     */
    public function ishouldNotSeeATag(string $tag, ?TableNode $table = null, ?string $content = null, ?PyStringNode $multiLineContent = null): void
    {
        try {
            $this->mustContainTag($tag, $this->getTableData($table), $multiLineContent ? $multiLineContent->getRaw() : $content);
        } catch (\DomainException $e) {
            return;
        }
        throw new \DomainException('Tag found');
    }

    /**
     * @Then the form must contain an input field
     */
    public function theFormMustContainAnInputField(TableNode $attribs): void
    {
        $inputs = $this->state->getLastFormCrawler()
            ->filterXPath('//input');

        /** @var DOMElement $input */
        foreach ($inputs as $input) {
            foreach ($this->getTableData($attribs) as $attrName => $attrVal) {
                if ($input->getAttribute($attrName) !== $attrVal) {
                    continue 2;
                }
            }

            return;
        }

        throw $this->createNotFoundException('input', $inputs);
    }

    /*************/
    /* Internals */
    /*************/

    protected function getCrawler(): Crawler
    {
        return new Crawler((string)$this->state->getResponse()
            ->getContent(), $this->state->getRequest()
            ->getUri());
    }

    /** @param array<string,string> $attr */
    protected function mustContainTag(string $tagName, array $attr = [], ?string $content = null): void
    {
        $crawler = $this->getCrawler();
        $xPath = '//'.$tagName;
        foreach ($attr as $attrName => $attrVal) {
            $xPath .= sprintf('[@%s="%s"]', $attrName, $attrVal);
        }
        $elements = $crawler->filterXPath($xPath);

        if (!$elements->count()) {
            throw $this->createNotFoundException('Tag', $crawler->filterXPath('//'.$tagName));
        }

        // Check content
        if (null !== $content) {
            $content = trim($content);
            /** @var DOMElement $elem */
            foreach ($elements as $elem) {
                if ($content === trim($elem->textContent)) {
                    return;
                }
            }
            throw $this->createNotFoundException('Tag with content', $crawler->filterXPath('//'.$tagName));
        }
    }

    protected function doRequest(Request $request): void
    {
        // Reboot kernel
        $this->kernel->shutdown();
        $response = $this->kernel->handle($request);
        $this->state->update($request, $response);
    }

    protected function newState(): State
    {
        return new State();
    }

    protected function createNotFoundException(string $what, ?Crawler $fallbacks = null): \DomainException
    {
        $errMsg = sprintf('%s not found.', $what);
        if (null !== $fallbacks) {
            $names = [];
            foreach ($fallbacks as $fallback) {
                $doc = $fallback->ownerDocument;
                if (null === $doc) {
                    throw new \DomainException('Error generating error message: no xml document');
                }
                $names[] = $doc->saveXML($fallback);
            }
            switch (\count($names)) {
                case 0:
                    break;
                case 1:
                    $errMsg .= sprintf(' Did you mean "%s"?', $names[0]);
                    break;
                default:
                    $errMsg .= sprintf(" Did you mean one of the following?\n%s", implode("\n", $names));
                    break;
            }
        }

        return new \DomainException($errMsg);
    }

    /**
     * @return array<string,string>
     * @psalm-suppress MixedReturnTypeCoercion
     */
    protected function getTableData(?TableNode $table): array
    {
        if (null === $table) {
            return [];
        }

        return $table->getRowsHash();
    }
}
